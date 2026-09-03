<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/alexa.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function alexaOauthError(string $error, string $descripcion, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['error' => $error, 'error_description' => $descripcion], JSON_UNESCAPED_SLASHES);
    exit;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    alexaOauthError('invalid_request', 'Metodo no permitido', 405);
}

try {
    idindAlexaRequerirMigracion($pdo);
    $alexa = idindAlexaRequerirConfig($configLocal);
    $clientId = trim((string) ($_POST['client_id'] ?? ''));
    $clientSecret = trim((string) ($_POST['client_secret'] ?? ''));
    $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (preg_match('/^Basic\s+(.+)$/i', $authorization, $match)) {
        $basic = base64_decode($match[1], true);
        if (is_string($basic) && strpos($basic, ':') !== false) {
            [$clientIdBasic, $clientSecretBasic] = explode(':', $basic, 2);
            $clientId = rawurldecode($clientIdBasic);
            $clientSecret = rawurldecode($clientSecretBasic);
        }
    }
    if (!hash_equals($alexa['client_id'], $clientId) || !hash_equals($alexa['client_secret'], $clientSecret)) {
        alexaOauthError('invalid_client', 'Credenciales OAuth invalidas', 401);
    }

    $grantType = trim((string) ($_POST['grant_type'] ?? ''));
    $accessToken = idindAlexaTokenAleatorio(48);
    $expiresIn = 3600;
    $scope = 'smart_home';
    $refreshToken = '';
    $usuarioId = 0;

    if ($grantType === 'authorization_code') {
        $codigo = trim((string) ($_POST['code'] ?? ''));
        $redirectUri = trim((string) ($_POST['redirect_uri'] ?? ''));
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT * FROM alexa_oauth_codes
             WHERE code_hash = :code_hash AND client_id = :client_id
               AND usado_en IS NULL AND expira_en > UTC_TIMESTAMP()
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['code_hash' => hash('sha256', $codigo), 'client_id' => $clientId]);
        $registro = $stmt->fetch();
        if (!$registro || !hash_equals((string) $registro['redirect_uri'], $redirectUri)) {
            $pdo->rollBack();
            alexaOauthError('invalid_grant', 'Codigo invalido, vencido o ya utilizado');
        }
        if (!empty($registro['code_challenge'])) {
            $verifier = trim((string) ($_POST['code_verifier'] ?? ''));
            $challenge = idindAlexaBase64Url(hash('sha256', $verifier, true));
            if ($verifier === '' || !hash_equals((string) $registro['code_challenge'], $challenge)) {
                $pdo->rollBack();
                alexaOauthError('invalid_grant', 'Verificacion PKCE invalida');
            }
        }
        $usuarioId = (int) $registro['usuario_id'];
        $scope = (string) $registro['scope'];
        $refreshToken = idindAlexaTokenAleatorio(48);
        $pdo->prepare('UPDATE alexa_oauth_codes SET usado_en = UTC_TIMESTAMP() WHERE id = :id')
            ->execute(['id' => (int) $registro['id']]);
        $pdo->prepare(
            'UPDATE alexa_oauth_tokens SET revocado_en = UTC_TIMESTAMP()
             WHERE usuario_id = :usuario_id AND client_id = :client_id AND revocado_en IS NULL'
        )->execute(['usuario_id' => $usuarioId, 'client_id' => $clientId]);
        $pdo->prepare(
            'INSERT INTO alexa_oauth_tokens (
               usuario_id, client_id, access_token_hash, refresh_token_hash, scope,
               access_expira_en, refresh_expira_en
             ) VALUES (
               :usuario_id, :client_id, :access_hash, :refresh_hash, :scope,
               UTC_TIMESTAMP() + INTERVAL 1 HOUR, UTC_TIMESTAMP() + INTERVAL 180 DAY
             )'
        )->execute([
            'usuario_id' => $usuarioId,
            'client_id' => $clientId,
            'access_hash' => hash('sha256', $accessToken),
            'refresh_hash' => hash('sha256', $refreshToken),
            'scope' => $scope,
        ]);
        $pdo->commit();
    } elseif ($grantType === 'refresh_token') {
        $refreshToken = trim((string) ($_POST['refresh_token'] ?? ''));
        $stmt = $pdo->prepare(
            "SELECT t.*, u.cliente_id FROM alexa_oauth_tokens t
             INNER JOIN usuarios u ON u.id = t.usuario_id
             WHERE t.refresh_token_hash = :refresh_hash AND t.client_id = :client_id
               AND t.revocado_en IS NULL AND t.refresh_expira_en > UTC_TIMESTAMP()
               AND u.estado = 'ACTIVO' AND u.rol IN ('ADMIN', 'OPERADOR') LIMIT 1"
        );
        $stmt->execute(['refresh_hash' => hash('sha256', $refreshToken), 'client_id' => $clientId]);
        $registro = $stmt->fetch();
        if (!$registro) {
            alexaOauthError('invalid_grant', 'Refresh token vencido o revocado');
        }
        $usuarioId = (int) $registro['usuario_id'];
        $scope = (string) $registro['scope'];
        $pdo->prepare(
            'UPDATE alexa_oauth_tokens
             SET access_token_hash = :access_hash,
                 access_expira_en = UTC_TIMESTAMP() + INTERVAL 1 HOUR,
                 ultimo_uso = UTC_TIMESTAMP()
             WHERE id = :id'
        )->execute(['access_hash' => hash('sha256', $accessToken), 'id' => (int) $registro['id']]);
    } else {
        alexaOauthError('unsupported_grant_type', 'Grant type no soportado');
    }

    $stmt = $pdo->prepare('SELECT cliente_id FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $usuarioId]);
    $clienteId = (int) $stmt->fetchColumn();
    $pdo->prepare(
        "INSERT INTO integraciones_domoticas (
           cliente_id, proveedor, nombre, estado, ultima_sincronizacion
         ) VALUES (:cliente_id, 'ALEXA', 'Amazon Alexa', 'CONFIGURADA', UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE estado = 'CONFIGURADA', ultima_sincronizacion = UTC_TIMESTAMP()"
    )->execute(['cliente_id' => $clienteId]);

    echo json_encode([
        'access_token' => $accessToken,
        'token_type' => 'Bearer',
        'expires_in' => $expiresIn,
        'refresh_token' => $refreshToken,
        'scope' => $scope,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ID Industrial Alexa OAuth: ' . $error->getMessage());
    alexaOauthError('server_error', 'No fue posible completar la vinculacion', 500);
}


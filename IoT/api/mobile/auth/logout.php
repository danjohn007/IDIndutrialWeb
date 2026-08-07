<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/mobile_auth.php';
requerirMetodo('POST');

$usuario = requerirTokenMovil();
$stmt = $pdo->prepare(
    'UPDATE tokens_moviles
     SET revocado_en = UTC_TIMESTAMP()
     WHERE id = :id AND token_hash = :token_hash'
);
$stmt->execute([
    'id' => (int) $usuario['token_id'],
    'token_hash' => (string) $usuario['token_hash'],
]);

try {
    $stmtPush = $pdo->prepare(
        'UPDATE moviles_push
         SET activo = 0
         WHERE sesion_movil_id = :sesion_movil_id'
    );
    $stmtPush->execute(['sesion_movil_id' => (int) $usuario['token_id']]);
} catch (Throwable $error) {
    // El cierre de sesion sigue siendo valido antes de instalar la migracion push.
    error_log('ID Industrial logout push: ' . $error->getMessage());
}

responderJson(200, ['ok' => true, 'data' => ['sesion_cerrada' => true]]);

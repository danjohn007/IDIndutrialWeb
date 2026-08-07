<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
requerirMetodo('POST');
$usuario = requerirTokenMovil(['ADMIN', 'OPERADOR']);
$data = obtenerJson();
$alertaId = filter_var($data['alerta_id'] ?? null, FILTER_VALIDATE_INT);
$accion = strtoupper(trim((string) ($data['accion'] ?? '')));
$comentario = trim((string) ($data['comentario'] ?? ''));
$responsable = (string) $usuario['nombre'];
$textLength = static function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
};

if ($alertaId === false || $alertaId < 1) {
    responderJson(422, ['ok' => false, 'error' => 'La alerta no es valida']);
}
if (!in_array($accion, ['RECONOCER', 'RESOLVER'], true)) {
    responderJson(422, ['ok' => false, 'error' => 'La accion no es valida']);
}
if ($textLength($comentario) > 500) {
    responderJson(422, ['ok' => false, 'error' => 'El comentario supera 500 caracteres']);
}

try {
    $pdo->beginTransaction();

    $stmtAlerta = $pdo->prepare(
        'SELECT a.id, a.atendida
         FROM alertas a
         INNER JOIN dispositivos d ON d.id = a.dispositivo_id
         WHERE a.id = :id
           AND d.cliente_id = :cliente_id
         LIMIT 1
         FOR UPDATE'
    );
    $stmtAlerta->execute([
        'id' => $alertaId,
        'cliente_id' => (int) $usuario['cliente_id'],
    ]);
    $alerta = $stmtAlerta->fetch();

    if (!$alerta) {
        $pdo->rollBack();
        responderJson(404, ['ok' => false, 'error' => 'La alerta no existe']);
    }
    if ((int) $alerta['atendida'] === 1) {
        $pdo->rollBack();
        responderJson(409, ['ok' => false, 'error' => 'La alerta ya fue resuelta']);
    }

    $stmtUltimaGestion = $pdo->prepare(
        'SELECT accion
         FROM alerta_gestiones
         WHERE alerta_id = :alerta_id
         ORDER BY fecha_hora DESC, id DESC
         LIMIT 1'
    );
    $stmtUltimaGestion->execute(['alerta_id' => $alertaId]);
    $ultimaGestion = $stmtUltimaGestion->fetch();

    if ($accion === 'RECONOCER' && ($ultimaGestion['accion'] ?? '') === 'RECONOCER') {
        $pdo->rollBack();
        responderJson(409, ['ok' => false, 'error' => 'La alerta ya fue reconocida']);
    }

    $stmtGestion = $pdo->prepare(
        'INSERT INTO alerta_gestiones (
            alerta_id, accion, responsable, comentario
         ) VALUES (
            :alerta_id, :accion, :responsable, :comentario
         )'
    );
    $stmtGestion->execute([
        'alerta_id' => $alertaId,
        'accion' => $accion,
        'responsable' => $responsable,
        'comentario' => $comentario === '' ? null : $comentario,
    ]);

    if ($accion === 'RESOLVER') {
        $stmtResolver = $pdo->prepare(
            'UPDATE alertas
             SET atendida = 1
             WHERE id = :id'
        );
        $stmtResolver->execute(['id' => $alertaId]);
    }

    $pdo->commit();
    responderJson(200, [
        'ok' => true,
        'data' => [
            'alerta_id' => $alertaId,
            'estado_atencion' => $accion === 'RESOLVER' ? 'RESUELTA' : 'RECONOCIDA',
            'responsable' => $responsable,
        ],
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ID Industrial app alertas: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible actualizar la alerta']);
}

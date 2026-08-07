<?php
declare(strict_types=1);

const IDIND_ALERTA_DESCONEXION = 'Dispositivo sin conexion';

function idindBuscarAlertaDesconexionAbierta(PDO $pdo, string $dispositivoId): ?int
{
    $stmt = $pdo->prepare(
        "SELECT id
         FROM alertas
         WHERE dispositivo_id = :dispositivo_id
           AND tipo_alerta = :tipo_alerta
           AND atendida = 0
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([
        'dispositivo_id' => $dispositivoId,
        'tipo_alerta' => IDIND_ALERTA_DESCONEXION,
    ]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

function idindCrearAlertaDesconexion(PDO $pdo, string $dispositivoId): ?int
{
    $stmtUltima = $pdo->prepare(
        "SELECT id, atendida, fecha_hora
         FROM alertas
         WHERE dispositivo_id = :dispositivo_id
           AND tipo_alerta = :tipo_alerta
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmtUltima->execute([
        'dispositivo_id' => $dispositivoId,
        'tipo_alerta' => IDIND_ALERTA_DESCONEXION,
    ]);
    $ultima = $stmtUltima->fetch();
    if ($ultima) {
        $stmtConexion = $pdo->prepare(
            'SELECT ultima_conexion FROM dispositivos WHERE id = :id LIMIT 1'
        );
        $stmtConexion->execute(['id' => $dispositivoId]);
        $ultimaConexion = $stmtConexion->fetchColumn();
        $alertaAbierta = (int) $ultima['atendida'] === 0;
        $sinReconexion = $ultimaConexion === false || $ultimaConexion === null
            || strtotime((string) $ultima['fecha_hora'])
                >= strtotime((string) $ultimaConexion);
        if ($alertaAbierta || $sinReconexion) {
            return null;
        }
    }

    $stmt = $pdo->prepare(
        "INSERT INTO alertas (
            dispositivo_id, tipo_alerta, valor_sensor, severidad
         ) VALUES (
            :dispositivo_id, :tipo_alerta, NULL, 'PRECAUCION'
         )"
    );
    $stmt->execute([
        'dispositivo_id' => $dispositivoId,
        'tipo_alerta' => IDIND_ALERTA_DESCONEXION,
    ]);
    return (int) $pdo->lastInsertId();
}

function idindResolverAlertasDesconexion(
    PDO $pdo,
    string $dispositivoId,
    string $comentario = 'Conexion restablecida automaticamente.'
): array {
    $stmt = $pdo->prepare(
        "SELECT id
         FROM alertas
         WHERE dispositivo_id = :dispositivo_id
           AND tipo_alerta = :tipo_alerta
           AND atendida = 0
         ORDER BY id ASC"
    );
    $stmt->execute([
        'dispositivo_id' => $dispositivoId,
        'tipo_alerta' => IDIND_ALERTA_DESCONEXION,
    ]);
    $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    if ($ids === []) {
        return [];
    }

    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare(
        "UPDATE alertas SET atendida = 1 WHERE id IN ({$marcadores})"
    )->execute($ids);

    $stmtGestion = $pdo->prepare(
        "INSERT INTO alerta_gestiones (
            alerta_id, accion, responsable, comentario
         ) VALUES (
            :alerta_id, 'RESOLVER', 'Sistema', :comentario
         )"
    );
    foreach ($ids as $id) {
        $stmtGestion->execute([
            'alerta_id' => $id,
            'comentario' => substr($comentario, 0, 500),
        ]);
    }
    return $ids;
}

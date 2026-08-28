<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'No encontrado']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/conectividad.php';
require_once __DIR__ . '/lib/alertas_notificaciones.php';
require_once __DIR__ . '/lib/push_notificaciones.php';

function idindSalidaConectividad(array $datos, int $codigo = 0): void
{
    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
    exit($codigo);
}

$lockObtenido = false;
try {
    $lockObtenido = (int) $pdo->query(
        "SELECT GET_LOCK('idind_cron_conectividad', 0)"
    )->fetchColumn() === 1;
    if (!$lockObtenido) {
        idindSalidaConectividad(['ok' => true, 'omitido' => 'Otra ejecucion sigue activa']);
    }

    $pdo->beginTransaction();
    $dispositivos = $pdo->query(
        "SELECT
            id,
            CASE
                WHEN ultima_conexion IS NULL
                  AND creado_en < UTC_TIMESTAMP() - INTERVAL 5 MINUTE THEN 1
                WHEN ultima_conexion < UTC_TIMESTAMP() - INTERVAL 2 MINUTE THEN 1
                ELSE 0
            END AS offline
         FROM dispositivos
         WHERE estado = 'Activo'
         ORDER BY id
         FOR UPDATE"
    )->fetchAll();

    $nuevas = [];
    $resueltas = [];
    $offline = 0;
    foreach ($dispositivos as $dispositivo) {
        $id = (string) $dispositivo['id'];
        if ((int) $dispositivo['offline'] === 1) {
            $offline++;
            $alertaId = idindCrearAlertaDesconexion($pdo, $id);
            if ($alertaId !== null) {
                $nuevas[] = $alertaId;
            }
            continue;
        }
        $resueltas = array_merge(
            $resueltas,
            idindResolverAlertasDesconexion($pdo, $id)
        );
    }
    $pdo->commit();

    $push = ['ok' => true, 'procesadas' => 0, 'enviadas' => 0];
    if ($nuevas !== []) {
        idindEncolarNotificacionesAlertas($pdo, $nuevas, ['PRECAUCION']);
        $push = idindPushEnviarPendientesPorAlertas($pdo, $nuevas, $configLocal);
    }

    idindSalidaConectividad([
        'ok' => true,
        'dispositivos_revisados' => count($dispositivos),
        'dispositivos_offline' => $offline,
        'alertas_creadas' => count($nuevas),
        'alertas_ids_creadas' => $nuevas,
        'alertas_resueltas' => count($resueltas),
        'alertas_ids_resueltas' => $resueltas,
        'push' => $push,
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ID Industrial cron conectividad: ' . $error->getMessage());
    idindSalidaConectividad(['ok' => false, 'error' => 'Fallo la revision de conectividad'], 1);
} finally {
    if ($lockObtenido) {
        try {
            $pdo->query("SELECT RELEASE_LOCK('idind_cron_conectividad')");
        } catch (Throwable $error) {
            error_log('ID Industrial liberar lock conectividad: ' . $error->getMessage());
        }
    }
}

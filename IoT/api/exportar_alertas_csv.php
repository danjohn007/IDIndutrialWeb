<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/alertas_filtros.php';
requerirMetodo('GET');

$usuario = requerirSesion();
$filtros = construirFiltrosAlertas($_GET, (int) $usuario['cliente_id']);

$stmt = $pdo->prepare(
    "SELECT
        a.fecha_hora,
        a.dispositivo_id,
        d.ubicacion,
        a.tipo_alerta,
        a.valor_sensor,
        a.severidad,
        CASE
            WHEN a.atendida = 1 THEN 'RESUELTA'
            WHEN g.accion = 'RECONOCER' THEN 'RECONOCIDA'
            ELSE 'NUEVA'
        END AS estado_atencion,
        g.responsable,
        g.comentario,
        g.fecha_hora AS gestion_fecha
     FROM alertas a
     INNER JOIN dispositivos d ON d.id = a.dispositivo_id
     LEFT JOIN alerta_gestiones g
       ON g.id = (
          SELECT g2.id
          FROM alerta_gestiones g2
          WHERE g2.alerta_id = a.id
          ORDER BY g2.fecha_hora DESC, g2.id DESC
          LIMIT 1
       )
     {$filtros['where']}
     ORDER BY a.fecha_hora DESC, a.id DESC
     LIMIT 5000"
);
enlazarParametrosAlerta($stmt, $filtros['params']);
$stmt->execute();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="alertas_id_industrial.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$salida = fopen('php://output', 'w');
if ($salida === false) {
    exit;
}

echo "\xEF\xBB\xBF";
fputcsv($salida, [
    'fecha_hora_utc',
    'dispositivo_id',
    'ubicacion',
    'tipo_alerta',
    'valor_sensor',
    'severidad',
    'estado_atencion',
    'responsable',
    'comentario',
    'gestion_fecha_utc',
]);
while ($fila = $stmt->fetch()) {
    fputcsv($salida, $fila);
}
fclose($salida);

<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/rutinas.php';

requerirMetodo('POST');
$usuario = requerirTokenMovil(['ADMIN']);
$data = obtenerJson();
$accion = strtoupper(trim((string) ($data['accion'] ?? 'CREAR')));

try {
    idindRutinasRequerirMigracion($pdo);
    if (!in_array($accion, ['CREAR', 'ACTUALIZAR'], true)) {
        throw new IdindRutinaException('Accion de administracion no valida');
    }
    $clienteId = (int) $usuario['cliente_id'];
    $rutinaId = (int) ($data['id'] ?? 0);
    if ($accion === 'ACTUALIZAR' && !idindRutinaObtener($pdo, $clienteId, $rutinaId)) {
        responderJson(404, ['ok' => false, 'error' => 'Rutina no encontrada']);
    }
    $rutina = idindRutinaValidar($pdo, $data, $clienteId);
    $pdo->beginTransaction();
    if ($accion === 'CREAR') {
        $stmt = $pdo->prepare(
            'INSERT INTO rutinas (
               cliente_id, nombre, descripcion, tipo_disparador, hora_local,
               dias_semana, zona_horaria, activa, creado_por
             ) VALUES (
               :cliente_id, :nombre, :descripcion, :tipo, :hora,
               :dias, :zona, :activa, :usuario
             )'
        );
        $stmt->execute([
            'cliente_id' => $clienteId,
            'nombre' => $rutina['nombre'],
            'descripcion' => $rutina['descripcion'],
            'tipo' => $rutina['tipo_disparador'],
            'hora' => $rutina['hora_local'],
            'dias' => $rutina['dias_semana'],
            'zona' => $rutina['zona_horaria'],
            'activa' => $rutina['activa'],
            'usuario' => (int) $usuario['id'],
        ]);
        $rutinaId = (int) $pdo->lastInsertId();
    } else {
        $stmt = $pdo->prepare(
            'UPDATE rutinas SET nombre = :nombre, descripcion = :descripcion,
               tipo_disparador = :tipo, hora_local = :hora, dias_semana = :dias,
               zona_horaria = :zona, activa = :activa
             WHERE id = :id AND cliente_id = :cliente_id'
        );
        $stmt->execute([
            'nombre' => $rutina['nombre'],
            'descripcion' => $rutina['descripcion'],
            'tipo' => $rutina['tipo_disparador'],
            'hora' => $rutina['hora_local'],
            'dias' => $rutina['dias_semana'],
            'zona' => $rutina['zona_horaria'],
            'activa' => $rutina['activa'],
            'id' => $rutinaId,
            'cliente_id' => $clienteId,
        ]);
        $pdo->prepare('DELETE FROM rutina_acciones WHERE rutina_id = :rutina_id')
            ->execute(['rutina_id' => $rutinaId]);
    }
    $stmtAccion = $pdo->prepare(
        'INSERT INTO rutina_acciones (rutina_id, orden, actuador_id, accion)
         VALUES (:rutina_id, :orden, :actuador_id, :accion)'
    );
    foreach ($rutina['acciones'] as $item) {
        $stmtAccion->execute([
            'rutina_id' => $rutinaId,
            'orden' => $item['orden'],
            'actuador_id' => $item['actuador_id'],
            'accion' => $item['accion'],
        ]);
    }
    $pdo->commit();
    responderJson($accion === 'CREAR' ? 201 : 200, [
        'ok' => true,
        'data' => ['rutina' => idindRutinaObtener($pdo, $clienteId, $rutinaId)],
    ]);
} catch (IdindRutinaException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responderJson(422, ['ok' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ID Industrial guardar rutina: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible guardar la rutina']);
}

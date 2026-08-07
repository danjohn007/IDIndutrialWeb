<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/shelly_admin.php';

requerirMetodo('POST');
$usuario = requerirTokenMovil(['ADMIN']);
$data = obtenerJson();
$accion = strtoupper(trim((string) ($data['accion'] ?? 'CREAR')));

try {
    idindShellyAdminRequerirMigracion($pdo);
    if (!in_array($accion, ['CREAR', 'ACTUALIZAR'], true)) {
        throw new IdindShellyAdminException('Accion de administracion no valida');
    }
    $clienteId = (int) $usuario['cliente_id'];
    $id = idindShellyAdminId($data['id'] ?? '');
    $actual = idindShellyAdminObtener($pdo, $clienteId, $id);
    $nuevo = $accion === 'CREAR';
    if ($nuevo && $actual) {
        responderJson(409, ['ok' => false, 'error' => 'El ID interno ya esta registrado']);
    }
    if (!$nuevo && !$actual) {
        responderJson(404, ['ok' => false, 'error' => 'Dispositivo Shelly no encontrado']);
    }

    $configuracion = idindShellyAdminConfiguracion($pdo, $data, $clienteId, $actual ?: []);
    idindShellyAdminValidarUnico(
        $pdo,
        $clienteId,
        $id,
        $configuracion['shelly_device_id'],
        $configuracion['canal'],
        $nuevo
    );

    $parametros = $configuracion + ['id' => $id, 'cliente_id' => $clienteId];
    if ($nuevo) {
        $stmt = $pdo->prepare(
            'INSERT INTO actuadores_shelly (
               id, cliente_id, nombre, ubicacion, dispositivo_vinculado_id,
               shelly_device_id, modelo, generacion, ip_local, canal, funcion,
               categoria, tipo_carga, corriente_max_a, potencia_max_w,
               tiempo_max_encendido_s, apagado_automatico, permite_rutinas,
               requiere_confirmacion, descripcion, modo_control, estado
             ) VALUES (
               :id, :cliente_id, :nombre, :ubicacion, :dispositivo_vinculado_id,
               :shelly_device_id, :modelo, :generacion, :ip_local, :canal, :funcion,
               :categoria, :tipo_carga, :corriente_max_a, :potencia_max_w,
               :tiempo_max_encendido_s, :apagado_automatico, :permite_rutinas,
               :requiere_confirmacion, :descripcion, :modo_control, :estado
             )'
        );
    } else {
        $stmt = $pdo->prepare(
            'UPDATE actuadores_shelly SET
               nombre = :nombre, ubicacion = :ubicacion,
               dispositivo_vinculado_id = :dispositivo_vinculado_id,
               shelly_device_id = :shelly_device_id, modelo = :modelo,
               generacion = :generacion, ip_local = :ip_local, canal = :canal,
               funcion = :funcion, categoria = :categoria, tipo_carga = :tipo_carga,
               corriente_max_a = :corriente_max_a, potencia_max_w = :potencia_max_w,
               tiempo_max_encendido_s = :tiempo_max_encendido_s,
               apagado_automatico = :apagado_automatico,
               permite_rutinas = :permite_rutinas,
               requiere_confirmacion = :requiere_confirmacion,
               descripcion = :descripcion, modo_control = :modo_control, estado = :estado
             WHERE id = :id AND cliente_id = :cliente_id'
        );
    }
    $stmt->execute($parametros);
    idindShellyAdminEvento(
        $pdo,
        $id,
        $nuevo ? 'CONFIGURACION_CREADA' : 'CONFIGURACION_ACTUALIZADA',
        [
            'usuario_id' => (int) $usuario['id'],
            'nombre' => $configuracion['nombre'],
            'categoria' => $configuracion['categoria'],
            'canal' => $configuracion['canal'],
        ]
    );

    responderJson($nuevo ? 201 : 200, [
        'ok' => true,
        'data' => ['actuador' => idindShellyAdminObtener($pdo, $clienteId, $id)],
    ]);
} catch (IdindShellyAdminException $error) {
    responderJson(422, ['ok' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('ID Industrial guardar Shelly movil: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible guardar el dispositivo Shelly']);
}

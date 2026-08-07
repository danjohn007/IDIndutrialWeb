<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$usuarioActual = requerirSesion(['ADMIN']);
$clienteId = (int) $usuarioActual['cliente_id'];

function dispositivoIdValido(string $id): string
{
    $id = trim($id);
    if (strlen($id) < 3 || strlen($id) > 64 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $id)) {
        responderJson(422, [
            'ok' => false,
            'error' => 'El ID debe tener entre 3 y 64 caracteres y usar solo letras, numeros, punto, guion o guion bajo',
        ]);
    }
    return $id;
}

function dispositivoUbicacionValida(string $ubicacion): string
{
    $ubicacion = trim($ubicacion);
    if (strlen($ubicacion) < 2 || strlen($ubicacion) > 160) {
        responderJson(422, ['ok' => false, 'error' => 'La ubicacion debe tener entre 2 y 160 caracteres']);
    }
    return $ubicacion;
}

function dispositivoEstadoValido(string $estado): string
{
    $estados = ['ACTIVO' => 'Activo', 'MANTENIMIENTO' => 'Mantenimiento', 'INACTIVO' => 'Inactivo'];
    $clave = strtoupper(trim($estado));
    if (!isset($estados[$clave])) {
        responderJson(422, ['ok' => false, 'error' => 'Estado de dispositivo invalido']);
    }
    return $estados[$clave];
}

function dispositivoTipoValido(string $tipo): string
{
    $tipo = strtoupper(trim($tipo));
    if (!in_array($tipo, ['ESP32', 'SHELLY'], true)) {
        responderJson(422, ['ok' => false, 'error' => 'Tipo de dispositivo invalido']);
    }
    return $tipo;
}

function valorEnumShelly(string $valor, array $permitidos, string $campo): string
{
    $valor = strtoupper(trim($valor));
    if (!in_array($valor, $permitidos, true)) {
        responderJson(422, ['ok' => false, 'error' => $campo . ' de Shelly invalido']);
    }
    return $valor;
}

function textoShellyValido(string $valor, string $campo, int $minimo, int $maximo): string
{
    $valor = trim($valor);
    if (strlen($valor) < $minimo || strlen($valor) > $maximo) {
        responderJson(422, [
            'ok' => false,
            'error' => $campo . ' debe tener entre ' . $minimo . ' y ' . $maximo . ' caracteres',
        ]);
    }
    return $valor;
}

function tablaShellyDisponible(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM actuadores_shelly LIMIT 0');
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function tablaEstadoShellyDisponible(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM estado_shelly LIMIT 0');
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function requerirTablaShelly(PDO $pdo): void
{
    if (!tablaShellyDisponible($pdo)) {
        responderJson(503, [
            'ok' => false,
            'error' => 'Falta importar database/migracion_shelly_mysql57.sql en phpMyAdmin',
        ]);
    }
}

function obtenerEsp32Cliente(PDO $pdo, string $id, int $clienteId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, cliente_id, ubicacion, estado, ultima_conexion, creado_en
         FROM dispositivos WHERE id = :id AND cliente_id = :cliente_id LIMIT 1'
    );
    $stmt->execute(['id' => $id, 'cliente_id' => $clienteId]);
    return $stmt->fetch() ?: null;
}

function obtenerShellyCliente(PDO $pdo, string $id, int $clienteId): ?array
{
    if (!tablaShellyDisponible($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT id, cliente_id, ubicacion, dispositivo_vinculado_id,
                shelly_device_id, modelo, generacion, ip_local, canal, funcion,
                modo_control, estado, estado_salida, ultima_conexion, creado_en
         FROM actuadores_shelly
         WHERE id = :id AND cliente_id = :cliente_id LIMIT 1'
    );
    $stmt->execute(['id' => $id, 'cliente_id' => $clienteId]);
    return $stmt->fetch() ?: null;
}

function esp32Publico(array $dispositivo): array
{
    return [
        'tipo' => 'ESP32',
        'id' => (string) $dispositivo['id'],
        'ubicacion' => (string) $dispositivo['ubicacion'],
        'estado' => (string) $dispositivo['estado'],
        'ultima_conexion' => $dispositivo['ultima_conexion'],
        'creado_en' => $dispositivo['creado_en'],
    ];
}

function shellyPublico(array $dispositivo): array
{
    return [
        'tipo' => 'SHELLY',
        'id' => (string) $dispositivo['id'],
        'ubicacion' => (string) $dispositivo['ubicacion'],
        'estado' => (string) $dispositivo['estado'],
        'ultima_conexion' => $dispositivo['ultima_conexion'],
        'creado_en' => $dispositivo['creado_en'],
        'dispositivo_vinculado_id' => $dispositivo['dispositivo_vinculado_id'],
        'shelly_device_id' => (string) $dispositivo['shelly_device_id'],
        'modelo' => (string) $dispositivo['modelo'],
        'generacion' => (string) $dispositivo['generacion'],
        'ip_local' => $dispositivo['ip_local'],
        'canal' => (int) $dispositivo['canal'],
        'funcion' => (string) $dispositivo['funcion'],
        'modo_control' => (string) $dispositivo['modo_control'],
        'estado_salida' => $dispositivo['estado_salida'] === null ? null : (bool) $dispositivo['estado_salida'],
        'conexion' => (string) ($dispositivo['conexion'] ?? 'SIN_DATOS'),
        'online' => (int) ($dispositivo['online'] ?? 0),
        'salida_encendida' => isset($dispositivo['salida_encendida'])
            ? (int) $dispositivo['salida_encendida']
            : null,
        'potencia_w' => $dispositivo['potencia_w'] ?? null,
        'voltaje_v' => $dispositivo['voltaje_v'] ?? null,
        'corriente_a' => $dispositivo['corriente_a'] ?? null,
        'temperatura_c' => $dispositivo['temperatura_c'] ?? null,
        'sincronizado_en' => $dispositivo['sincronizado_en'] ?? null,
        'ultimo_error' => $dispositivo['ultimo_error'] ?? null,
    ];
}

function idEquipoDisponible(PDO $pdo, string $id): bool
{
    $stmt = $pdo->prepare('SELECT id FROM dispositivos WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    if ($stmt->fetchColumn()) {
        return false;
    }
    if (tablaShellyDisponible($pdo)) {
        $stmt = $pdo->prepare('SELECT id FROM actuadores_shelly WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        if ($stmt->fetchColumn()) {
            return false;
        }
    }
    return true;
}

function validarVinculoEsp32(PDO $pdo, string $id, int $clienteId): ?string
{
    $id = trim($id);
    if ($id === '') {
        return null;
    }
    $id = dispositivoIdValido($id);
    if (!obtenerEsp32Cliente($pdo, $id, $clienteId)) {
        responderJson(422, ['ok' => false, 'error' => 'El ESP32 asociado no pertenece al cliente']);
    }
    return $id;
}

function configuracionShellyValida(PDO $pdo, array $data, int $clienteId, array $actual = []): array
{
    $deviceId = textoShellyValido(
        (string) ($data['shelly_device_id'] ?? ($actual['shelly_device_id'] ?? '')),
        'El ID del dispositivo Shelly', 3, 100
    );
    $modelo = textoShellyValido(
        (string) ($data['modelo'] ?? ($actual['modelo'] ?? '')),
        'El modelo', 2, 80
    );
    $generacion = valorEnumShelly(
        (string) ($data['generacion'] ?? ($actual['generacion'] ?? 'GEN2_PLUS')),
        ['GEN1', 'GEN2_PLUS'], 'Generacion'
    );
    $funcion = valorEnumShelly(
        (string) ($data['funcion'] ?? ($actual['funcion'] ?? 'SIRENA')),
        ['SIRENA', 'BALIZA', 'VENTILACION', 'CONTACTOR', 'OTRO'], 'Funcion'
    );
    $modoControl = valorEnumShelly(
        (string) ($data['modo_control'] ?? ($actual['modo_control'] ?? 'HIBRIDO')),
        ['LOCAL', 'CLOUD', 'HIBRIDO'], 'Modo de control'
    );
    $ipLocal = trim((string) ($data['ip_local'] ?? ($actual['ip_local'] ?? '')));
    if (strlen($ipLocal) > 255 || ($ipLocal !== '' && !preg_match('/^[A-Za-z0-9.:-]+$/', $ipLocal))) {
        responderJson(422, ['ok' => false, 'error' => 'La IP o host local de Shelly no es valido']);
    }
    $canalTexto = (string) ($data['canal'] ?? ($actual['canal'] ?? '0'));
    if (!preg_match('/^\d+$/', $canalTexto) || (int) $canalTexto > 31) {
        responderJson(422, ['ok' => false, 'error' => 'El canal de Shelly debe estar entre 0 y 31']);
    }
    $vinculado = validarVinculoEsp32(
        $pdo,
        (string) ($data['dispositivo_vinculado_id'] ?? ($actual['dispositivo_vinculado_id'] ?? '')),
        $clienteId
    );
    return [
        'shelly_device_id' => $deviceId,
        'modelo' => $modelo,
        'generacion' => $generacion,
        'ip_local' => $ipLocal === '' ? null : $ipLocal,
        'canal' => (int) $canalTexto,
        'funcion' => $funcion,
        'modo_control' => $modoControl,
        'dispositivo_vinculado_id' => $vinculado,
    ];
}

function validarCanalShellyUnico(PDO $pdo, int $clienteId, string $deviceId, int $canal, string $idActual = ''): void
{
    $stmt = $pdo->prepare(
        'SELECT id FROM actuadores_shelly
         WHERE cliente_id = :cliente_id AND shelly_device_id = :device_id
           AND canal = :canal AND id <> :id_actual LIMIT 1'
    );
    $stmt->execute([
        'cliente_id' => $clienteId,
        'device_id' => $deviceId,
        'canal' => $canal,
        'id_actual' => $idActual,
    ]);
    if ($stmt->fetchColumn()) {
        responderJson(409, ['ok' => false, 'error' => 'Ese canal del dispositivo Shelly ya esta registrado']);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT id, cliente_id, ubicacion, estado, ultima_conexion, creado_en
         FROM dispositivos WHERE cliente_id = :cliente_id
         ORDER BY FIELD(estado, 'Activo', 'Mantenimiento', 'Inactivo'), ubicacion, id"
    );
    $stmt->execute(['cliente_id' => $clienteId]);
    $equipos = array_map('esp32Publico', $stmt->fetchAll());
    $shellyDisponible = tablaShellyDisponible($pdo);
    $shellyOperacionDisponible = $shellyDisponible && tablaEstadoShellyDisponible($pdo);
    if ($shellyDisponible) {
        $stmt = $pdo->prepare(
            "SELECT id, cliente_id, ubicacion, dispositivo_vinculado_id,
                    shelly_device_id, modelo, generacion, ip_local, canal, funcion,
                    modo_control, estado, estado_salida, ultima_conexion, creado_en
             FROM actuadores_shelly WHERE cliente_id = :cliente_id
             ORDER BY FIELD(estado, 'Activo', 'Mantenimiento', 'Inactivo'), ubicacion, id"
        );
        $stmt->execute(['cliente_id' => $clienteId]);
        $filasShelly = $stmt->fetchAll();
        if ($shellyOperacionDisponible && $filasShelly !== []) {
            $stmtEstado = $pdo->prepare(
                "SELECT actuador_id, online, salida_encendida, potencia_w,
                        voltaje_v, corriente_a, temperatura_c, sincronizado_en,
                        ultimo_error,
                        CASE
                          WHEN sincronizado_en IS NULL THEN 'SIN_DATOS'
                          WHEN sincronizado_en < UTC_TIMESTAMP() - INTERVAL 3 MINUTE
                            THEN 'DESACTUALIZADO'
                          WHEN online = 1 THEN 'ONLINE'
                          ELSE 'OFFLINE'
                        END AS conexion
                 FROM estado_shelly
                 WHERE actuador_id IN (
                    SELECT id FROM actuadores_shelly WHERE cliente_id = :cliente_id
                 )"
            );
            $stmtEstado->execute(['cliente_id' => $clienteId]);
            $estados = [];
            foreach ($stmtEstado->fetchAll() as $estadoShelly) {
                $estados[(string) $estadoShelly['actuador_id']] = $estadoShelly;
            }
            foreach ($filasShelly as &$filaShelly) {
                $filaShelly = array_merge(
                    $filaShelly,
                    $estados[(string) $filaShelly['id']] ?? []
                );
            }
            unset($filaShelly);
        }
        $equipos = array_merge($equipos, array_map('shellyPublico', $filasShelly));
    }
    usort($equipos, static function (array $a, array $b): int {
        return [$a['ubicacion'], $a['tipo'], $a['id']] <=> [$b['ubicacion'], $b['tipo'], $b['id']];
    });
    responderJson(200, [
        'ok' => true,
        'data' => [
            'dispositivos' => $equipos,
            'shelly_disponible' => $shellyDisponible,
            'shelly_operacion_disponible' => $shellyOperacionDisponible,
        ],
    ]);
}

requerirMetodo('POST');
requerirCsrf($usuarioActual);
$data = obtenerJson();
$accion = strtolower(trim((string) ($data['accion'] ?? '')));
$tipo = dispositivoTipoValido((string) ($data['tipo'] ?? 'ESP32'));
$id = dispositivoIdValido((string) ($data['id'] ?? ''));

if ($accion === 'crear') {
    $ubicacion = dispositivoUbicacionValida((string) ($data['ubicacion'] ?? ''));
    $estado = dispositivoEstadoValido((string) ($data['estado'] ?? 'Activo'));
    if (!idEquipoDisponible($pdo, $id)) {
        responderJson(409, ['ok' => false, 'error' => 'Ese identificador de dispositivo no esta disponible']);
    }
    if ($tipo === 'ESP32') {
        $stmt = $pdo->prepare(
            'INSERT INTO dispositivos (id, cliente_id, ubicacion, estado)
             VALUES (:id, :cliente_id, :ubicacion, :estado)'
        );
        $stmt->execute(['id' => $id, 'cliente_id' => $clienteId, 'ubicacion' => $ubicacion, 'estado' => $estado]);
        responderJson(201, ['ok' => true, 'data' => ['dispositivo' => esp32Publico(obtenerEsp32Cliente($pdo, $id, $clienteId) ?: [])]]);
    }
    requerirTablaShelly($pdo);
    $config = configuracionShellyValida($pdo, $data, $clienteId);
    validarCanalShellyUnico($pdo, $clienteId, $config['shelly_device_id'], $config['canal']);
    $stmt = $pdo->prepare(
        'INSERT INTO actuadores_shelly (
           id, cliente_id, ubicacion, dispositivo_vinculado_id, shelly_device_id,
           modelo, generacion, ip_local, canal, funcion, modo_control, estado
         ) VALUES (
           :id, :cliente_id, :ubicacion, :vinculado, :device_id,
           :modelo, :generacion, :ip_local, :canal, :funcion, :modo_control, :estado
         )'
    );
    $stmt->execute([
        'id' => $id, 'cliente_id' => $clienteId, 'ubicacion' => $ubicacion,
        'vinculado' => $config['dispositivo_vinculado_id'], 'device_id' => $config['shelly_device_id'],
        'modelo' => $config['modelo'], 'generacion' => $config['generacion'],
        'ip_local' => $config['ip_local'], 'canal' => $config['canal'],
        'funcion' => $config['funcion'], 'modo_control' => $config['modo_control'], 'estado' => $estado,
    ]);
    responderJson(201, ['ok' => true, 'data' => ['dispositivo' => shellyPublico(obtenerShellyCliente($pdo, $id, $clienteId) ?: [])]]);
}

if ($accion === 'actualizar') {
    if ($tipo === 'ESP32') {
        $actual = obtenerEsp32Cliente($pdo, $id, $clienteId);
        if (!$actual) {
            responderJson(404, ['ok' => false, 'error' => 'ESP32 no encontrado']);
        }
        $ubicacion = dispositivoUbicacionValida((string) ($data['ubicacion'] ?? $actual['ubicacion']));
        $estado = dispositivoEstadoValido((string) ($data['estado'] ?? $actual['estado']));
        $stmt = $pdo->prepare(
            'UPDATE dispositivos SET ubicacion = :ubicacion, estado = :estado
             WHERE id = :id AND cliente_id = :cliente_id'
        );
        $stmt->execute(['ubicacion' => $ubicacion, 'estado' => $estado, 'id' => $id, 'cliente_id' => $clienteId]);
        responderJson(200, ['ok' => true, 'data' => ['dispositivo' => esp32Publico(obtenerEsp32Cliente($pdo, $id, $clienteId) ?: [])]]);
    }
    requerirTablaShelly($pdo);
    $actual = obtenerShellyCliente($pdo, $id, $clienteId);
    if (!$actual) {
        responderJson(404, ['ok' => false, 'error' => 'Actuador Shelly no encontrado']);
    }
    $ubicacion = dispositivoUbicacionValida((string) ($data['ubicacion'] ?? $actual['ubicacion']));
    $estado = dispositivoEstadoValido((string) ($data['estado'] ?? $actual['estado']));
    $config = configuracionShellyValida($pdo, $data, $clienteId, $actual);
    validarCanalShellyUnico($pdo, $clienteId, $config['shelly_device_id'], $config['canal'], $id);
    $stmt = $pdo->prepare(
        'UPDATE actuadores_shelly
         SET ubicacion = :ubicacion, dispositivo_vinculado_id = :vinculado,
             shelly_device_id = :device_id, modelo = :modelo, generacion = :generacion,
             ip_local = :ip_local, canal = :canal, funcion = :funcion,
             modo_control = :modo_control, estado = :estado
         WHERE id = :id AND cliente_id = :cliente_id'
    );
    $stmt->execute([
        'ubicacion' => $ubicacion, 'vinculado' => $config['dispositivo_vinculado_id'],
        'device_id' => $config['shelly_device_id'], 'modelo' => $config['modelo'],
        'generacion' => $config['generacion'], 'ip_local' => $config['ip_local'],
        'canal' => $config['canal'], 'funcion' => $config['funcion'],
        'modo_control' => $config['modo_control'], 'estado' => $estado,
        'id' => $id, 'cliente_id' => $clienteId,
    ]);
    responderJson(200, ['ok' => true, 'data' => ['dispositivo' => shellyPublico(obtenerShellyCliente($pdo, $id, $clienteId) ?: [])]]);
}

responderJson(422, ['ok' => false, 'error' => 'Accion de dispositivo no reconocida']);

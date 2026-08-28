<?php
declare(strict_types=1);

function idindHikvisionDisponible(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM equipos_hikvision LIMIT 0');
        $pdo->query('SELECT 1 FROM estado_hikvision LIMIT 0');
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function idindHikvisionRequerirTablas(PDO $pdo): void
{
    if (!idindHikvisionDisponible($pdo)) {
        responderJson(503, [
            'ok' => false,
            'error' => 'Falta importar database/migracion_hikvision_mysql57.sql en phpMyAdmin',
        ]);
    }
}

function idindHikvisionConexion(?string $sincronizadoEn, int $online): string
{
    if (!$sincronizadoEn) {
        return 'SIN_DATOS';
    }
    $timestamp = strtotime($sincronizadoEn . ' UTC');
    if ($timestamp === false || $timestamp < time() - 120) {
        return 'DESACTUALIZADO';
    }
    return $online === 1 ? 'ONLINE' : 'OFFLINE';
}

function idindHikvisionPublico(array $fila): array
{
    $online = (int) ($fila['online'] ?? 0);
    $sincronizado = $fila['sincronizado_en'] ?? null;
    return [
        'tipo' => 'HIKVISION',
        'id' => (string) $fila['id'],
        'nombre' => (string) ($fila['nombre'] ?? $fila['id']),
        'ubicacion' => (string) $fila['ubicacion'],
        'categoria' => (string) ($fila['categoria'] ?? 'OTRO'),
        'modelo' => $fila['modelo'] ?? null,
        'numero_serie' => $fila['numero_serie'] ?? null,
        'ip_local' => (string) ($fila['ip_local'] ?? ''),
        'puerto' => (int) ($fila['puerto'] ?? 80),
        'protocolo' => (string) ($fila['protocolo'] ?? 'HTTP'),
        'estado' => (string) $fila['estado'],
        'ultima_conexion' => $fila['ultima_conexion'] ?? null,
        'creado_en' => $fila['creado_en'] ?? null,
        'online' => $online,
        'conexion' => idindHikvisionConexion($sincronizado, $online),
        'nombre_detectado' => $fila['nombre_detectado'] ?? null,
        'modelo_detectado' => $fila['modelo_detectado'] ?? null,
        'serial_detectado' => $fila['serial_detectado'] ?? null,
        'firmware' => $fila['firmware'] ?? null,
        'mac' => $fila['mac'] ?? null,
        'uptime_s' => isset($fila['uptime_s']) ? (int) $fila['uptime_s'] : null,
        'fuente' => $fila['fuente'] ?? null,
        'ultimo_error' => $fila['ultimo_error'] ?? null,
        'sincronizado_en' => $sincronizado,
    ];
}

function idindHikvisionCliente(PDO $pdo, int $clienteId, ?string $id = null): array
{
    $sql = "SELECT h.*, eh.online, eh.nombre_detectado, eh.modelo_detectado,
                   eh.serial_detectado, eh.firmware, eh.mac, eh.uptime_s,
                   eh.fuente, eh.ultimo_error, eh.sincronizado_en
            FROM equipos_hikvision h
            LEFT JOIN estado_hikvision eh ON eh.equipo_id = h.id
            WHERE h.cliente_id = :cliente_id";
    $parametros = ['cliente_id' => $clienteId];
    if ($id !== null) {
        $sql .= ' AND h.id = :id';
        $parametros['id'] = $id;
    }
    $sql .= " ORDER BY FIELD(h.estado, 'Activo', 'Mantenimiento', 'Inactivo'), h.ubicacion, h.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    return array_map('idindHikvisionPublico', $stmt->fetchAll());
}

function idindHikvisionTexto(string $valor, string $campo, int $min, int $max): string
{
    $valor = trim($valor);
    if (strlen($valor) < $min || strlen($valor) > $max) {
        responderJson(422, ['ok' => false, 'error' => $campo . ' debe tener entre ' . $min . ' y ' . $max . ' caracteres']);
    }
    return $valor;
}

function idindHikvisionConfiguracion(array $data, array $actual = []): array
{
    $nombre = idindHikvisionTexto((string) ($data['nombre'] ?? ($actual['nombre'] ?? '')), 'El nombre', 2, 100);
    $ip = trim((string) ($data['ip_local'] ?? ($actual['ip_local'] ?? '')));
    if ($ip === '' || strlen($ip) > 255 || !preg_match('/^[A-Za-z0-9.:-]+$/', $ip)) {
        responderJson(422, ['ok' => false, 'error' => 'La IP o host local de Hikvision no es valido']);
    }
    $categoria = strtoupper(trim((string) ($data['categoria'] ?? ($actual['categoria'] ?? 'OTRO'))));
    if (!in_array($categoria, ['CAMARA', 'NVR_DVR', 'CONTROL_ACCESO', 'INTERCOM', 'OTRO'], true)) {
        responderJson(422, ['ok' => false, 'error' => 'Categoria Hikvision invalida']);
    }
    $protocolo = strtoupper(trim((string) ($data['protocolo'] ?? ($actual['protocolo'] ?? 'HTTP'))));
    if (!in_array($protocolo, ['HTTP', 'HTTPS'], true)) {
        responderJson(422, ['ok' => false, 'error' => 'Protocolo Hikvision invalido']);
    }
    $puerto = (int) ($data['puerto'] ?? ($actual['puerto'] ?? ($protocolo === 'HTTPS' ? 443 : 80)));
    if ($puerto < 1 || $puerto > 65535) {
        responderJson(422, ['ok' => false, 'error' => 'El puerto Hikvision debe estar entre 1 y 65535']);
    }
    $modelo = trim((string) ($data['modelo'] ?? ($actual['modelo'] ?? '')));
    $serial = trim((string) ($data['numero_serie'] ?? ($actual['numero_serie'] ?? '')));
    if (strlen($modelo) > 100 || strlen($serial) > 100) {
        responderJson(422, ['ok' => false, 'error' => 'Modelo o numero de serie demasiado largo']);
    }
    return [
        'nombre' => $nombre,
        'categoria' => $categoria,
        'modelo' => $modelo === '' ? null : $modelo,
        'numero_serie' => $serial === '' ? null : $serial,
        'ip_local' => $ip,
        'puerto' => $puerto,
        'protocolo' => $protocolo,
    ];
}

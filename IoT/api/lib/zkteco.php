<?php
declare(strict_types=1);

function idindZktecoDisponible(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM equipos_zkteco LIMIT 0');
        $pdo->query('SELECT 1 FROM estado_zkteco LIMIT 0');
        $pdo->query('SELECT 1 FROM eventos_zkteco LIMIT 0');
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function idindZktecoRequerirTablas(PDO $pdo): void
{
    if (!idindZktecoDisponible($pdo)) {
        responderJson(503, [
            'ok' => false,
            'error' => 'Falta importar database/migracion_zkteco_mysql57.sql en phpMyAdmin',
        ]);
    }
}

function idindZktecoConexion(?string $sincronizadoEn, int $online): string
{
    if (!$sincronizadoEn) return 'SIN_DATOS';
    $timestamp = strtotime($sincronizadoEn . ' UTC');
    if ($timestamp === false || $timestamp < time() - 180) return 'DESACTUALIZADO';
    return $online === 1 ? 'ONLINE' : 'OFFLINE';
}

function idindZktecoPublico(array $fila): array
{
    $online = (int) ($fila['online'] ?? 0);
    $sincronizado = $fila['sincronizado_en'] ?? null;
    return [
        'tipo' => 'ZKTECO',
        'id' => (string) $fila['id'],
        'nombre' => (string) ($fila['nombre'] ?? $fila['id']),
        'ubicacion' => (string) $fila['ubicacion'],
        'categoria' => (string) ($fila['categoria'] ?? 'ASISTENCIA'),
        'modelo' => $fila['modelo'] ?? null,
        'numero_serie' => $fila['numero_serie'] ?? null,
        'ip_local' => $fila['ip_local'] ?? null,
        'puerto' => (int) ($fila['puerto'] ?? 4370),
        'protocolo' => (string) ($fila['protocolo'] ?? 'PULL_4370'),
        'numero_maquina' => (int) ($fila['numero_maquina'] ?? 1),
        'estado' => (string) $fila['estado'],
        'ultima_conexion' => $fila['ultima_conexion'] ?? null,
        'creado_en' => $fila['creado_en'] ?? null,
        'online' => $online,
        'conexion' => idindZktecoConexion($sincronizado, $online),
        'nombre_detectado' => $fila['nombre_detectado'] ?? null,
        'modelo_detectado' => $fila['modelo_detectado'] ?? null,
        'serial_detectado' => $fila['serial_detectado'] ?? null,
        'firmware' => $fila['firmware'] ?? null,
        'plataforma' => $fila['plataforma'] ?? null,
        'usuarios_total' => isset($fila['usuarios_total']) ? (int) $fila['usuarios_total'] : null,
        'registros_total' => isset($fila['registros_total']) ? (int) $fila['registros_total'] : null,
        'capacidad_usuarios' => isset($fila['capacidad_usuarios']) ? (int) $fila['capacidad_usuarios'] : null,
        'capacidad_registros' => isset($fila['capacidad_registros']) ? (int) $fila['capacidad_registros'] : null,
        'fuente' => $fila['fuente'] ?? null,
        'ultimo_error' => $fila['ultimo_error'] ?? null,
        'sincronizado_en' => $sincronizado,
    ];
}

function idindZktecoCliente(PDO $pdo, int $clienteId, ?string $id = null): array
{
    $sql = "SELECT z.*, ez.online, ez.nombre_detectado, ez.modelo_detectado,
                   ez.serial_detectado, ez.firmware, ez.plataforma, ez.usuarios_total,
                   ez.registros_total, ez.capacidad_usuarios, ez.capacidad_registros,
                   ez.fuente, ez.ultimo_error, ez.sincronizado_en
            FROM equipos_zkteco z
            LEFT JOIN estado_zkteco ez ON ez.equipo_id = z.id
            WHERE z.cliente_id = :cliente_id";
    $parametros = ['cliente_id' => $clienteId];
    if ($id !== null) {
        $sql .= ' AND z.id = :id';
        $parametros['id'] = $id;
    }
    $sql .= " ORDER BY FIELD(z.estado, 'Activo', 'Mantenimiento', 'Inactivo'), z.ubicacion, z.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    return array_map('idindZktecoPublico', $stmt->fetchAll());
}

function idindZktecoTexto(string $valor, string $campo, int $min, int $max): string
{
    $valor = trim($valor);
    if (strlen($valor) < $min || strlen($valor) > $max) {
        responderJson(422, ['ok' => false, 'error' => $campo . ' debe tener entre ' . $min . ' y ' . $max . ' caracteres']);
    }
    return $valor;
}

function idindZktecoConfiguracion(array $data, array $actual = []): array
{
    $nombre = idindZktecoTexto((string) ($data['nombre'] ?? ($actual['nombre'] ?? '')), 'El nombre', 2, 100);
    $categoria = strtoupper(trim((string) ($data['categoria'] ?? ($actual['categoria'] ?? 'ASISTENCIA'))));
    if (!in_array($categoria, ['ASISTENCIA', 'CONTROL_ACCESO', 'HIBRIDO', 'OTRO'], true)) {
        responderJson(422, ['ok' => false, 'error' => 'Categoria ZKTeco invalida']);
    }
    $protocolo = strtoupper(trim((string) ($data['protocolo'] ?? ($actual['protocolo'] ?? 'PULL_4370'))));
    if (!in_array($protocolo, ['PULL_4370', 'PUSH_ADMS', 'WDMS_API'], true)) {
        responderJson(422, ['ok' => false, 'error' => 'Protocolo ZKTeco invalido']);
    }
    $ip = trim((string) ($data['ip_local'] ?? ($actual['ip_local'] ?? '')));
    if (strlen($ip) > 255 || ($ip !== '' && !preg_match('/^[A-Za-z0-9.:-]+$/', $ip))) {
        responderJson(422, ['ok' => false, 'error' => 'La IP o host local de ZKTeco no es valido']);
    }
    if ($protocolo === 'PULL_4370' && $ip === '') {
        responderJson(422, ['ok' => false, 'error' => 'La IP local es obligatoria para PULL_4370']);
    }
    $puerto = (int) ($data['puerto'] ?? ($actual['puerto'] ?? 4370));
    if ($puerto < 1 || $puerto > 65535) {
        responderJson(422, ['ok' => false, 'error' => 'El puerto ZKTeco debe estar entre 1 y 65535']);
    }
    $numeroMaquina = (int) ($data['numero_maquina'] ?? ($actual['numero_maquina'] ?? 1));
    if ($numeroMaquina < 1 || $numeroMaquina > 65535) {
        responderJson(422, ['ok' => false, 'error' => 'El numero de maquina debe estar entre 1 y 65535']);
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
        'ip_local' => $ip === '' ? null : $ip,
        'puerto' => $puerto,
        'protocolo' => $protocolo,
        'numero_maquina' => $numeroMaquina,
    ];
}

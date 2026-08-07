<?php
declare(strict_types=1);

require_once __DIR__ . '/shelly.php';

final class IdindShellyAdminException extends RuntimeException
{
}

function idindShellyAdminDisponible(PDO $pdo): bool
{
    try {
        $pdo->query(
            'SELECT nombre, categoria, tipo_carga, corriente_max_a, potencia_max_w,
                    tiempo_max_encendido_s, apagado_automatico, permite_rutinas,
                    requiere_confirmacion, descripcion
             FROM actuadores_shelly LIMIT 0'
        );
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function idindShellyAdminRequerirMigracion(PDO $pdo): void
{
    if (!idindShellyAdminDisponible($pdo)) {
        throw new IdindShellyAdminException(
            'Falta importar database/migracion_shelly_seguridad_mysql57.sql'
        );
    }
}

function idindShellyAdminTexto(
    $valor,
    string $campo,
    int $minimo,
    int $maximo,
    bool $opcional = false
): ?string {
    $texto = trim((string) $valor);
    if ($opcional && $texto === '') {
        return null;
    }
    $largo = strlen($texto);
    if ($largo < $minimo || $largo > $maximo) {
        throw new IdindShellyAdminException(
            $campo . ' debe tener entre ' . $minimo . ' y ' . $maximo . ' caracteres'
        );
    }
    return $texto;
}

function idindShellyAdminEnum($valor, array $permitidos, string $campo): string
{
    $normalizado = strtoupper(trim((string) $valor));
    if (!in_array($normalizado, $permitidos, true)) {
        throw new IdindShellyAdminException($campo . ' no valido');
    }
    return $normalizado;
}

function idindShellyAdminBooleano($valor, bool $predeterminado = false): int
{
    if ($valor === null || $valor === '') {
        return $predeterminado ? 1 : 0;
    }
    if (is_bool($valor)) {
        return $valor ? 1 : 0;
    }
    return in_array(strtolower(trim((string) $valor)), ['1', 'true', 'si', 'on'], true) ? 1 : 0;
}

function idindShellyAdminNumeroOpcional(
    $valor,
    string $campo,
    float $minimo,
    float $maximo
): ?float {
    if ($valor === null || trim((string) $valor) === '') {
        return null;
    }
    if (!is_numeric($valor)) {
        throw new IdindShellyAdminException($campo . ' debe ser numerico');
    }
    $numero = (float) $valor;
    if ($numero < $minimo || $numero > $maximo) {
        throw new IdindShellyAdminException(
            $campo . ' debe estar entre ' . $minimo . ' y ' . $maximo
        );
    }
    return $numero;
}

function idindShellyAdminId($valor): string
{
    $id = trim((string) $valor);
    if (strlen($id) < 3 || strlen($id) > 64 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $id)) {
        throw new IdindShellyAdminException(
            'El ID interno debe tener entre 3 y 64 caracteres y usar letras, numeros, punto, guion o guion bajo'
        );
    }
    return $id;
}

function idindShellyAdminConfiguracion(PDO $pdo, array $data, int $clienteId, array $actual = []): array
{
    $valor = static function (string $campo, $predeterminado = null) use ($data, $actual) {
        return array_key_exists($campo, $data)
            ? $data[$campo]
            : ($actual[$campo] ?? $predeterminado);
    };

    $ubicacion = idindShellyAdminTexto($valor('ubicacion'), 'La ubicacion', 2, 160);
    $nombre = idindShellyAdminTexto($valor('nombre', $valor('id')), 'El nombre', 2, 120);
    $deviceId = idindShellyAdminTexto($valor('shelly_device_id'), 'El Device ID de Shelly', 3, 100);
    $modelo = idindShellyAdminTexto($valor('modelo'), 'El modelo', 2, 80);
    $descripcion = idindShellyAdminTexto($valor('descripcion'), 'La descripcion', 0, 255, true);
    $generacion = idindShellyAdminEnum($valor('generacion', 'GEN2_PLUS'), ['GEN1', 'GEN2_PLUS'], 'La generacion');
    $funcion = idindShellyAdminEnum(
        $valor('funcion', 'SIRENA'),
        ['SIRENA', 'BALIZA', 'VENTILACION', 'CONTACTOR', 'OTRO'],
        'La funcion'
    );
    $categoria = idindShellyAdminEnum(
        $valor('categoria', 'SEGURIDAD'),
        ['SEGURIDAD', 'AUTOMATIZACION', 'MONITOREO'],
        'La categoria'
    );
    $tipoCarga = idindShellyAdminEnum(
        $valor('tipo_carga', 'DESCONOCIDA'),
        ['RESISTIVA', 'INDUCTIVA', 'ELECTRONICA', 'DESCONOCIDA'],
        'El tipo de carga'
    );
    $modoControl = idindShellyAdminEnum(
        $valor('modo_control', 'HIBRIDO'),
        ['LOCAL', 'CLOUD', 'HIBRIDO'],
        'El modo de control'
    );
    $estadoNormalizado = idindShellyAdminEnum(
        $valor('estado', 'Activo'),
        ['ACTIVO', 'MANTENIMIENTO', 'INACTIVO'],
        'El estado'
    );
    $estado = ['ACTIVO' => 'Activo', 'MANTENIMIENTO' => 'Mantenimiento', 'INACTIVO' => 'Inactivo'][$estadoNormalizado];

    $canalTexto = trim((string) $valor('canal', '0'));
    if (!preg_match('/^\d+$/', $canalTexto) || (int) $canalTexto > 31) {
        throw new IdindShellyAdminException('El canal debe estar entre 0 y 31');
    }
    $ipLocal = idindShellyAdminTexto($valor('ip_local'), 'La IP o host local', 1, 255, true);
    if ($ipLocal !== null && !preg_match('/^[A-Za-z0-9.:-]+$/', $ipLocal)) {
        throw new IdindShellyAdminException('La IP o host local no es valido');
    }

    $vinculado = trim((string) $valor('dispositivo_vinculado_id'));
    if ($vinculado !== '') {
        $stmt = $pdo->prepare(
            "SELECT id FROM dispositivos WHERE id = :id AND cliente_id = :cliente_id AND estado <> 'Inactivo' LIMIT 1"
        );
        $stmt->execute(['id' => $vinculado, 'cliente_id' => $clienteId]);
        if (!$stmt->fetchColumn()) {
            throw new IdindShellyAdminException('El ESP32 asociado no pertenece al cliente o esta inactivo');
        }
    }

    $corriente = idindShellyAdminNumeroOpcional($valor('corriente_max_a'), 'La corriente maxima', 0.1, 63);
    $potencia = idindShellyAdminNumeroOpcional($valor('potencia_max_w'), 'La potencia maxima', 1, 50000);
    $tiempo = idindShellyAdminNumeroOpcional($valor('tiempo_max_encendido_s'), 'El tiempo maximo', 1, 86400);
    $apagadoAutomatico = idindShellyAdminBooleano($valor('apagado_automatico'));
    $permiteRutinas = idindShellyAdminBooleano($valor('permite_rutinas'));
    $requiereConfirmacion = idindShellyAdminBooleano($valor('requiere_confirmacion'), true);

    if ($apagadoAutomatico && $tiempo === null) {
        throw new IdindShellyAdminException('Define el tiempo maximo para usar el apagado automatico');
    }
    if ($categoria === 'SEGURIDAD' && $permiteRutinas) {
        throw new IdindShellyAdminException('Un dispositivo de seguridad no puede habilitarse para rutinas');
    }
    if ($categoria === 'MONITOREO' && $funcion !== 'OTRO') {
        throw new IdindShellyAdminException('Los equipos de monitoreo deben usar la funcion OTRO');
    }

    return [
        'nombre' => $nombre,
        'ubicacion' => $ubicacion,
        'dispositivo_vinculado_id' => $vinculado === '' ? null : $vinculado,
        'shelly_device_id' => $deviceId,
        'modelo' => $modelo,
        'generacion' => $generacion,
        'ip_local' => $ipLocal,
        'canal' => (int) $canalTexto,
        'funcion' => $funcion,
        'categoria' => $categoria,
        'tipo_carga' => $tipoCarga,
        'corriente_max_a' => $corriente,
        'potencia_max_w' => $potencia,
        'tiempo_max_encendido_s' => $tiempo === null ? null : (int) $tiempo,
        'apagado_automatico' => $apagadoAutomatico,
        'permite_rutinas' => $categoria === 'SEGURIDAD' ? 0 : $permiteRutinas,
        'requiere_confirmacion' => $requiereConfirmacion,
        'descripcion' => $descripcion,
        'modo_control' => $modoControl,
        'estado' => $estado,
    ];
}

function idindShellyAdminObtener(PDO $pdo, int $clienteId, string $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT a.*, es.online, es.salida_encendida, es.potencia_w, es.voltaje_v,
                es.corriente_a, es.temperatura_c, es.fuente, es.ultimo_error,
                es.sincronizado_en,
                CASE
                  WHEN es.sincronizado_en IS NULL THEN 'SIN_DATOS'
                  WHEN es.sincronizado_en < UTC_TIMESTAMP() - INTERVAL 3 MINUTE THEN 'DESACTUALIZADO'
                  WHEN es.online = 1 THEN 'ONLINE'
                  ELSE 'OFFLINE'
                END AS conexion
         FROM actuadores_shelly a
         LEFT JOIN estado_shelly es ON es.actuador_id = a.id
         WHERE a.id = :id AND a.cliente_id = :cliente_id LIMIT 1"
    );
    $stmt->execute(['id' => $id, 'cliente_id' => $clienteId]);
    $fila = $stmt->fetch();
    if (!$fila) {
        return null;
    }
    foreach (['online', 'salida_encendida', 'apagado_automatico', 'permite_rutinas', 'requiere_confirmacion'] as $campo) {
        $fila[$campo] = $fila[$campo] === null ? null : (int) $fila[$campo];
    }
    $fila['canal'] = (int) $fila['canal'];
    return $fila;
}

function idindShellyAdminValidarUnico(
    PDO $pdo,
    int $clienteId,
    string $id,
    string $deviceId,
    int $canal,
    bool $nuevo
): void {
    if ($nuevo) {
        $stmt = $pdo->prepare('SELECT id FROM dispositivos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        if ($stmt->fetchColumn()) {
            throw new IdindShellyAdminException('El ID interno ya pertenece a un ESP32');
        }
    }
    $stmt = $pdo->prepare(
        'SELECT id FROM actuadores_shelly
         WHERE cliente_id = :cliente_id AND shelly_device_id = :device_id
           AND canal = :canal AND id <> :id LIMIT 1'
    );
    $stmt->execute([
        'cliente_id' => $clienteId,
        'device_id' => $deviceId,
        'canal' => $canal,
        'id' => $id,
    ]);
    if ($stmt->fetchColumn()) {
        throw new IdindShellyAdminException('Ese canal del dispositivo Shelly ya esta registrado');
    }
}

function idindShellyAdminEvento(
    PDO $pdo,
    string $actuadorId,
    string $evento,
    array $detalle,
    ?int $salida = null
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO eventos_shelly (
           actuador_id, evento, origen, salida_encendida, detalle_json
         ) VALUES (:actuador, :evento, \'USUARIO\', :salida, :detalle)'
    );
    $stmt->execute([
        'actuador' => $actuadorId,
        'evento' => substr($evento, 0, 80),
        'salida' => $salida,
        'detalle' => idindShellyJsonSeguro($detalle),
    ]);
}

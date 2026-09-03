<?php
declare(strict_types=1);

require_once __DIR__ . '/shelly.php';

final class IdindRutinaException extends RuntimeException
{
}

function idindRutinasDisponibles(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM rutinas LIMIT 0');
        $pdo->query('SELECT 1 FROM rutina_acciones LIMIT 0');
        $pdo->query('SELECT 1 FROM rutina_ejecuciones LIMIT 0');
        $pdo->query('SELECT 1 FROM integraciones_domoticas LIMIT 0');
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function idindRutinasRequerirMigracion(PDO $pdo): void
{
    if (!idindRutinasDisponibles($pdo)) {
        throw new IdindRutinaException(
            'Falta importar database/migracion_rutinas_mysql57.sql'
        );
    }
}

function idindRutinaTexto($valor, string $campo, int $minimo, int $maximo, bool $opcional = false): ?string
{
    $texto = trim((string) $valor);
    if ($opcional && $texto === '') {
        return null;
    }
    if (strlen($texto) < $minimo || strlen($texto) > $maximo) {
        throw new IdindRutinaException(
            $campo . ' debe tener entre ' . $minimo . ' y ' . $maximo . ' caracteres'
        );
    }
    return $texto;
}

function idindRutinaActuadoresDisponibles(PDO $pdo, int $clienteId): array
{
    $stmt = $pdo->prepare(
        "SELECT a.id, a.nombre, a.ubicacion, a.modelo, a.canal, a.funcion,
                a.tipo_carga, a.apagado_automatico, a.tiempo_max_encendido_s,
                es.online, es.salida_encendida,
                CASE
                  WHEN es.sincronizado_en IS NULL THEN 'SIN_DATOS'
                  WHEN es.sincronizado_en < UTC_TIMESTAMP() - INTERVAL 3 MINUTE THEN 'DESACTUALIZADO'
                  WHEN es.online = 1 THEN 'ONLINE'
                  ELSE 'OFFLINE'
                END AS conexion
         FROM actuadores_shelly a
         LEFT JOIN estado_shelly es ON es.actuador_id = a.id
         WHERE a.cliente_id = :cliente_id
           AND a.estado = 'Activo'
           AND a.categoria = 'AUTOMATIZACION'
           AND a.permite_rutinas = 1
           AND a.modo_control IN ('CLOUD', 'HIBRIDO')
         ORDER BY a.ubicacion, a.nombre, a.id"
    );
    $stmt->execute(['cliente_id' => $clienteId]);
    $filas = $stmt->fetchAll();
    foreach ($filas as &$fila) {
        $fila['canal'] = (int) $fila['canal'];
        $fila['online'] = (int) ($fila['online'] ?? 0);
        $fila['salida_encendida'] = $fila['salida_encendida'] === null
            ? null
            : (int) $fila['salida_encendida'];
        $fila['apagado_automatico'] = (int) $fila['apagado_automatico'];
    }
    unset($fila);
    return $filas;
}

function idindRutinaObtener(PDO $pdo, int $clienteId, int $rutinaId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, cliente_id, nombre, descripcion, tipo_disparador, hora_local,
                dias_semana, zona_horaria, activa, creado_por, ultima_ejecucion,
                creado_en, actualizado_en
         FROM rutinas WHERE id = :id AND cliente_id = :cliente_id LIMIT 1'
    );
    $stmt->execute(['id' => $rutinaId, 'cliente_id' => $clienteId]);
    $rutina = $stmt->fetch();
    if (!$rutina) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT ra.id, ra.orden, ra.actuador_id, ra.accion,
                a.nombre AS actuador_nombre, a.ubicacion, a.funcion, a.estado,
                a.categoria, a.permite_rutinas
         FROM rutina_acciones ra
         LEFT JOIN actuadores_shelly a ON a.id = ra.actuador_id
         WHERE ra.rutina_id = :rutina_id ORDER BY ra.orden, ra.id'
    );
    $stmt->execute(['rutina_id' => $rutinaId]);
    $rutina['acciones'] = $stmt->fetchAll();
    $rutina['activa'] = (int) $rutina['activa'];
    $rutina['dias'] = $rutina['dias_semana'] === null || $rutina['dias_semana'] === ''
        ? []
        : array_map('intval', explode(',', (string) $rutina['dias_semana']));
    unset($rutina['dias_semana']);
    foreach ($rutina['acciones'] as &$accion) {
        $accion['id'] = (int) $accion['id'];
        $accion['orden'] = (int) $accion['orden'];
        $accion['permite_rutinas'] = (int) ($accion['permite_rutinas'] ?? 0);
    }
    unset($accion);
    return $rutina;
}

function idindRutinaListar(PDO $pdo, int $clienteId): array
{
    $stmt = $pdo->prepare(
        "SELECT r.id, r.nombre, r.descripcion, r.tipo_disparador, r.hora_local,
                r.dias_semana, r.zona_horaria, r.activa, r.ultima_ejecucion,
                COUNT(ra.id) AS acciones_total,
                SUM(CASE WHEN a.id IS NULL OR a.estado <> 'Activo'
                           OR a.categoria <> 'AUTOMATIZACION' OR a.permite_rutinas <> 1
                         THEN 1 ELSE 0 END) AS acciones_no_disponibles,
                (SELECT re.estado FROM rutina_ejecuciones re
                 WHERE re.rutina_id = r.id ORDER BY re.id DESC LIMIT 1) AS ultimo_estado
         FROM rutinas r
         LEFT JOIN rutina_acciones ra ON ra.rutina_id = r.id
         LEFT JOIN actuadores_shelly a ON a.id = ra.actuador_id
         WHERE r.cliente_id = :cliente_id
         GROUP BY r.id
         ORDER BY r.activa DESC, r.nombre, r.id"
    );
    $stmt->execute(['cliente_id' => $clienteId]);
    $filas = $stmt->fetchAll();

    $stmtAcciones = $pdo->prepare(
        "SELECT ra.rutina_id, ra.orden, ra.actuador_id, ra.accion,
                a.nombre AS actuador_nombre, a.ubicacion, a.funcion
         FROM rutina_acciones ra
         INNER JOIN rutinas r ON r.id = ra.rutina_id
         LEFT JOIN actuadores_shelly a ON a.id = ra.actuador_id
         WHERE r.cliente_id = :cliente_id
         ORDER BY ra.rutina_id, ra.orden, ra.id"
    );
    $stmtAcciones->execute(['cliente_id' => $clienteId]);
    $accionesPorRutina = [];
    foreach ($stmtAcciones->fetchAll() as $accion) {
        $rutinaId = (int) $accion['rutina_id'];
        $accionesPorRutina[$rutinaId][] = [
            'orden' => (int) $accion['orden'],
            'actuador_id' => (string) $accion['actuador_id'],
            'actuador_nombre' => $accion['actuador_nombre'],
            'ubicacion' => $accion['ubicacion'],
            'funcion' => $accion['funcion'],
            'accion' => (string) $accion['accion'],
        ];
    }

    foreach ($filas as &$fila) {
        $fila['id'] = (int) $fila['id'];
        $fila['activa'] = (int) $fila['activa'];
        $fila['acciones_total'] = (int) $fila['acciones_total'];
        $fila['acciones_no_disponibles'] = (int) $fila['acciones_no_disponibles'];
        $fila['acciones_resumen'] = $accionesPorRutina[$fila['id']] ?? [];
        $fila['dias'] = $fila['dias_semana'] === null || $fila['dias_semana'] === ''
            ? []
            : array_map('intval', explode(',', (string) $fila['dias_semana']));
        unset($fila['dias_semana']);
    }
    unset($fila);
    return $filas;
}

function idindRutinaValidar(PDO $pdo, array $data, int $clienteId): array
{
    $nombre = idindRutinaTexto($data['nombre'] ?? '', 'El nombre', 3, 120);
    $descripcion = idindRutinaTexto($data['descripcion'] ?? '', 'La descripcion', 0, 255, true);
    $tipo = strtoupper(trim((string) ($data['tipo_disparador'] ?? 'MANUAL')));
    if (!in_array($tipo, ['MANUAL', 'HORARIO'], true)) {
        throw new IdindRutinaException('Tipo de disparador no valido');
    }
    $zona = trim((string) ($data['zona_horaria'] ?? 'America/Mexico_City'));
    if (!in_array($zona, timezone_identifiers_list(), true)) {
        throw new IdindRutinaException('Zona horaria no valida');
    }
    $hora = null;
    $dias = [];
    if ($tipo === 'HORARIO') {
        $horaEntrada = trim((string) ($data['hora_local'] ?? ''));
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horaEntrada)) {
            throw new IdindRutinaException('La hora debe usar el formato HH:MM');
        }
        $hora = $horaEntrada . ':00';
        if (!is_array($data['dias'] ?? null)) {
            throw new IdindRutinaException('Selecciona al menos un dia');
        }
        foreach ($data['dias'] as $dia) {
            $numero = (int) $dia;
            if ($numero < 1 || $numero > 7) {
                throw new IdindRutinaException('Los dias de la semana no son validos');
            }
            $dias[$numero] = $numero;
        }
        ksort($dias);
        $dias = array_values($dias);
        if ($dias === []) {
            throw new IdindRutinaException('Selecciona al menos un dia');
        }
    }
    $activa = !array_key_exists('activa', $data) || !empty($data['activa']) ? 1 : 0;
    $accionesEntrada = $data['acciones'] ?? null;
    if (!is_array($accionesEntrada) || count($accionesEntrada) < 1 || count($accionesEntrada) > 5) {
        throw new IdindRutinaException('La rutina debe tener entre 1 y 5 acciones');
    }
    $disponibles = [];
    foreach (idindRutinaActuadoresDisponibles($pdo, $clienteId) as $actuador) {
        $disponibles[(string) $actuador['id']] = $actuador;
    }
    $acciones = [];
    $usados = [];
    foreach ($accionesEntrada as $indice => $entrada) {
        if (!is_array($entrada)) {
            throw new IdindRutinaException('Accion de rutina no valida');
        }
        $actuadorId = trim((string) ($entrada['actuador_id'] ?? ''));
        $accion = strtoupper(trim((string) ($entrada['accion'] ?? '')));
        if (!isset($disponibles[$actuadorId])) {
            throw new IdindRutinaException('Un equipo seleccionado no esta habilitado para rutinas');
        }
        if (isset($usados[$actuadorId])) {
            throw new IdindRutinaException('Cada equipo puede aparecer una sola vez por rutina');
        }
        if (!in_array($accion, ['ENCENDER', 'APAGAR'], true)) {
            throw new IdindRutinaException('Accion Shelly no valida');
        }
        $usados[$actuadorId] = true;
        $acciones[] = [
            'orden' => $indice + 1,
            'actuador_id' => $actuadorId,
            'accion' => $accion,
        ];
    }
    return [
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'tipo_disparador' => $tipo,
        'hora_local' => $hora,
        'dias_semana' => $dias === [] ? null : implode(',', $dias),
        'dias' => $dias,
        'zona_horaria' => $zona,
        'activa' => $activa,
        'acciones' => $acciones,
    ];
}

function idindRutinaEjecuciones(PDO $pdo, int $clienteId, ?int $rutinaId = null, int $limite = 30): array
{
    $limite = max(1, min(100, $limite));
    $sql = 'SELECT re.id, re.rutina_id, r.nombre AS rutina_nombre, re.origen,
                   re.estado, re.acciones_total, re.acciones_exitosas,
                   re.detalle_json, re.iniciada_en, re.finalizada_en,
                   u.nombre AS solicitado_por_nombre
            FROM rutina_ejecuciones re
            INNER JOIN rutinas r ON r.id = re.rutina_id
            LEFT JOIN usuarios u ON u.id = re.solicitado_por
            WHERE re.cliente_id = :cliente_id';
    $params = ['cliente_id' => $clienteId];
    if ($rutinaId !== null) {
        $sql .= ' AND re.rutina_id = :rutina_id';
        $params['rutina_id'] = $rutinaId;
    }
    $sql .= ' ORDER BY re.id DESC LIMIT ' . $limite;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll();
    foreach ($filas as &$fila) {
        $fila['id'] = (int) $fila['id'];
        $fila['rutina_id'] = (int) $fila['rutina_id'];
        $fila['acciones_total'] = (int) $fila['acciones_total'];
        $fila['acciones_exitosas'] = (int) $fila['acciones_exitosas'];
        $detalle = json_decode((string) ($fila['detalle_json'] ?? ''), true);
        $fila['detalle'] = is_array($detalle) ? $detalle : [];
        unset($fila['detalle_json']);
    }
    unset($fila);
    return $filas;
}

function idindRutinaEjecutar(
    PDO $pdo,
    array $config,
    int $clienteId,
    int $rutinaId,
    string $origen,
    ?int $usuarioId = null,
    ?string $claveProgramacion = null
): array {
    $origen = strtoupper($origen);
    if (!in_array($origen, ['MANUAL', 'CRON'], true)) {
        throw new IdindRutinaException('Origen de ejecucion no valido');
    }
    $lockName = 'idind_rutina_' . $rutinaId;
    $stmtLock = $pdo->prepare('SELECT GET_LOCK(:nombre, 0)');
    $stmtLock->execute(['nombre' => $lockName]);
    if ((int) $stmtLock->fetchColumn() !== 1) {
        return ['ejecutada' => false, 'estado' => 'OMITIDA', 'error' => 'La rutina ya se esta ejecutando'];
    }
    try {
        $rutina = idindRutinaObtener($pdo, $clienteId, $rutinaId);
        if (!$rutina) {
            throw new IdindRutinaException('Rutina no encontrada');
        }
        if (empty($rutina['activa'])) {
            throw new IdindRutinaException('La rutina esta desactivada');
        }
        if ($rutina['acciones'] === []) {
            throw new IdindRutinaException('La rutina no tiene acciones');
        }

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO rutina_ejecuciones (
               rutina_id, cliente_id, origen, solicitado_por, clave_programacion,
               estado, acciones_total, iniciada_en
             ) VALUES (
               :rutina_id, :cliente_id, :origen, :usuario_id, :clave,
               \'EJECUTANDO\', :total, UTC_TIMESTAMP()
             )'
        );
        $stmt->execute([
            'rutina_id' => $rutinaId,
            'cliente_id' => $clienteId,
            'origen' => $origen,
            'usuario_id' => $usuarioId,
            'clave' => $claveProgramacion,
            'total' => count($rutina['acciones']),
        ]);
        $ejecucionId = (int) $pdo->lastInsertId();
        if ($ejecucionId < 1) {
            return ['ejecutada' => false, 'estado' => 'OMITIDA', 'error' => 'Ejecucion programada ya procesada'];
        }

        $resultados = [];
        $exitosas = 0;
        foreach ($rutina['acciones'] as $accion) {
            $resultadoAccion = [
                'actuador_id' => (string) $accion['actuador_id'],
                'accion' => (string) $accion['accion'],
                'aplicada' => false,
            ];
            try {
                if (
                    (string) ($accion['estado'] ?? '') !== 'Activo'
                    || (string) ($accion['categoria'] ?? '') !== 'AUTOMATIZACION'
                    || (int) ($accion['permite_rutinas'] ?? 0) !== 1
                ) {
                    throw new IdindRutinaException('El equipo ya no esta habilitado para rutinas');
                }
                $comandoId = idindShellyCrearComando(
                    $pdo,
                    (string) $accion['actuador_id'],
                    (string) $accion['accion'],
                    $origen === 'CRON' ? 'CRON' : 'APP',
                    $usuarioId,
                    null,
                    'Rutina: ' . (string) $rutina['nombre']
                );
                $resultado = idindShellyProcesarComando($pdo, $config, $comandoId);
                $resultadoAccion['comando_id'] = $comandoId;
                $resultadoAccion['aplicada'] = !empty($resultado['aplicado']);
                $resultadoAccion['estado'] = $resultado['estado'] ?? null;
                $resultadoAccion['error'] = $resultado['error'] ?? null;
                if ($resultadoAccion['aplicada']) {
                    $exitosas++;
                }
            } catch (Throwable $error) {
                $resultadoAccion['error'] = $error->getMessage();
            }
            $resultados[] = $resultadoAccion;
        }
        $total = count($rutina['acciones']);
        $estado = $exitosas === $total ? 'COMPLETADA' : ($exitosas > 0 ? 'PARCIAL' : 'FALLIDA');
        $pdo->prepare(
            'UPDATE rutina_ejecuciones
             SET estado = :estado, acciones_exitosas = :exitosas,
                 detalle_json = :detalle, finalizada_en = UTC_TIMESTAMP()
             WHERE id = :id'
        )->execute([
            'estado' => $estado,
            'exitosas' => $exitosas,
            'detalle' => idindShellyJsonSeguro(['acciones' => $resultados]),
            'id' => $ejecucionId,
        ]);
        $pdo->prepare(
            'UPDATE rutinas SET ultima_ejecucion = UTC_TIMESTAMP()
             WHERE id = :id AND cliente_id = :cliente_id'
        )->execute(['id' => $rutinaId, 'cliente_id' => $clienteId]);
        return [
            'ejecutada' => true,
            'ejecucion_id' => $ejecucionId,
            'estado' => $estado,
            'acciones_total' => $total,
            'acciones_exitosas' => $exitosas,
            'acciones' => $resultados,
        ];
    } finally {
        try {
            $stmtUnlock = $pdo->prepare('SELECT RELEASE_LOCK(:nombre)');
            $stmtUnlock->execute(['nombre' => $lockName]);
        } catch (Throwable $error) {
            error_log('ID Industrial unlock rutina: ' . $error->getMessage());
        }
    }
}

function idindRutinasProgramadasEjecutar(PDO $pdo, array $config, int $limite = 5): array
{
    idindRutinasRequerirMigracion($pdo);
    $limite = max(1, min(10, $limite));
    $stmt = $pdo->query(
        "SELECT id, cliente_id, hora_local, dias_semana, zona_horaria
         FROM rutinas
         WHERE activa = 1 AND tipo_disparador = 'HORARIO'
         ORDER BY id"
    );
    $resultados = [];
    foreach ($stmt->fetchAll() as $rutina) {
        if (count($resultados) >= $limite) {
            break;
        }
        try {
            $ahora = new DateTimeImmutable('now', new DateTimeZone((string) $rutina['zona_horaria']));
        } catch (Throwable $error) {
            continue;
        }
        $dias = array_map('intval', explode(',', (string) $rutina['dias_semana']));
        if (!in_array((int) $ahora->format('N'), $dias, true)) {
            continue;
        }
        if (substr((string) $rutina['hora_local'], 0, 5) !== $ahora->format('H:i')) {
            continue;
        }
        $clave = $ahora->format('YmdHi') . '-' . (string) $rutina['zona_horaria'];
        $resultados[] = idindRutinaEjecutar(
            $pdo,
            $config,
            (int) $rutina['cliente_id'],
            (int) $rutina['id'],
            'CRON',
            null,
            $clave
        );
    }
    return $resultados;
}

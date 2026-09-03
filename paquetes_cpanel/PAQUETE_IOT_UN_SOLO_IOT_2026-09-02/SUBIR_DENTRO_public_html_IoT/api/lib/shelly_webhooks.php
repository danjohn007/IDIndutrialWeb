<?php
declare(strict_types=1);

function idindWebhookAuditoriaDisponible(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT id FROM entregas_webhook_shelly LIMIT 0');
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function idindWebhookCrearEntrega(
    PDO $pdo,
    string $actuadorId,
    bool $encendida,
    string $metodo,
    array $detalle
): ?int {
    if (!idindWebhookAuditoriaDisponible($pdo)) {
        return null;
    }
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO entregas_webhook_shelly (
                actuador_id, evento, salida_encendida, metodo, detalle_json
             ) VALUES (
                :actuador_id, :evento, :salida, :metodo, :detalle
             )"
        );
        $stmt->execute([
            'actuador_id' => $actuadorId,
            'evento' => $encendida ? 'ENCENDIDO' : 'APAGADO',
            'salida' => $encendida ? 1 : 0,
            'metodo' => $metodo,
            'detalle' => idindShellyJsonSeguro($detalle),
        ]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $error) {
        error_log('ID Industrial auditoria webhook: ' . $error->getMessage());
        return null;
    }
}

function idindWebhookCerrarEntrega(
    PDO $pdo,
    ?int $entregaId,
    string $estado,
    bool $cambioEstado = false,
    bool $cambioExterno = false,
    int $alexaEnviados = 0,
    array $alexaErrores = [],
    ?string $error = null
): void {
    if ($entregaId === null) {
        return;
    }
    try {
        $pdo->prepare(
            'UPDATE entregas_webhook_shelly
             SET estado = :estado,
                 cambio_estado = :cambio_estado,
                 cambio_externo = :cambio_externo,
                 alexa_enviados = :alexa_enviados,
                 alexa_errores_json = :alexa_errores,
                 ultimo_error = :ultimo_error,
                 procesado_en = UTC_TIMESTAMP()
             WHERE id = :id'
        )->execute([
            'estado' => $estado,
            'cambio_estado' => $cambioEstado ? 1 : 0,
            'cambio_externo' => $cambioExterno ? 1 : 0,
            'alexa_enviados' => max(0, $alexaEnviados),
            'alexa_errores' => $alexaErrores === []
                ? null
                : idindShellyJsonSeguro($alexaErrores),
            'ultimo_error' => $error === null ? null : substr($error, 0, 500),
            'id' => $entregaId,
        ]);
    } catch (Throwable $auditError) {
        error_log('ID Industrial cierre auditoria webhook: ' . $auditError->getMessage());
    }
}

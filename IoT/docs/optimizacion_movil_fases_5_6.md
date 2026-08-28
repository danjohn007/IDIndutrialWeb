# Fases 5 y 6: navegacion y optimizacion

## Cambios en la app

- Rutinas ya no ocupa una opcion en la barra inferior. Se abre desde Cuenta.
- Monitoreo y graficas consultan datos solamente cuando su pantalla esta activa.
- Equipos Shelly sincroniza al entrar, cada 10 segundos mientras esta visible y
  al regresar la app desde segundo plano.
- El detalle Shelly consulta Cloud al abrirse y mantiene `Sincronizar ahora`
  como respaldo manual.
- Las solicitudes periodicas no se superponen si el servidor tarda en responder.

## Cambios en cPanel

Subir estas rutas conservando la estructura:

```text
api/mobile/dispositivos.php
api/cron_retencion.php
```

No se requiere una migracion SQL para estas fases. El cron de retencion ya
existente se reutiliza; no se debe crear otro cron.

Opcionalmente se pueden agregar a `api/config.local.php`:

```php
'retention_shelly_event_days' => 365,
'retention_push_days' => 90,
```

Si no se agregan, esos valores se aplican automaticamente. Solo se eliminan
notificaciones push con estado `ENVIADA` o `DESCARTADA`; las pendientes y los
eventos recientes permanecen intactos.

## Frecuencia resultante

- Monitoreo visible: cada 5 segundos.
- Graficas visibles: cada 5 segundos.
- Shelly visible: cada 10 segundos.
- Pantallas ocultas o app en segundo plano: sin sondeo movil.
- App cerrada: webhook y cron Shelly mantienen el estado del servidor y las
  notificaciones externas.

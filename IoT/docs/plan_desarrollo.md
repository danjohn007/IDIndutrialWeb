# Plan de desarrollo

## Fase 1: prototipo integrado

- [x] Lectura no bloqueante de DHT11, MQ-2 y KY-026.
- [x] Estados `NORMAL`, `ALERTA` y `ALARMA`.
- [x] LEDs, buzzer y panel web local.
- [x] Envío HTTPS cada 10 segundos.
- [x] API PHP con validación, token y sentencias preparadas.
- [x] Historial, resumen horario y dashboard oscuro.
- [x] Esquema MariaDB y documentación de conexiones.

## Fase 2: despliegue controlado

- [ ] Crear base, usuario y `config.local.php` en cPanel.
- [ ] Registrar cliente y dispositivo inicial.
- [ ] Subir API y web al dominio.
- [ ] Probar `guardar_lectura.php` con `curl`.
- [ ] Cargar firmware y comprobar lecturas durante 24 horas.
- [ ] Configurar la CA TLS en el ESP32 para retirar `setInsecure()`.

## Fase 3: calibración y pruebas

- [x] Registrar referencia ADC del MQ-2 en aire limpio desde el panel.
- [x] Mostrar calentamiento, umbral, última calibración y posible lectura atascada.
- [ ] Medir el rango ADC del MQ-2 en aire limpio y con estímulos controlados.
- [ ] Ajustar `GAS_UMBRAL`, temperatura de alerta y temperatura de alarma.
- [ ] Probar sensor desconectado, WiFi caído, API caída y reinicio del ESP32.
- [ ] Verificar alimentación y temperatura del módulo MQ-2.
- [ ] Evitar pruebas con fuego abierto fuera de un entorno controlado.

## Fase 4: usuarios y aplicación móvil

- [x] Login con `password_hash()`/`password_verify()` y sesiones PHP.
- [x] Autorización por `cliente_id` en todos los endpoints de consulta.
- [ ] CRUD de clientes y dispositivos para administradores.
- [x] App móvil consumiendo resumen, historial, dispositivos e incidentes.
- [x] Cola y envío push para alertas críticas mediante Cron Job.
- [x] Flujo para reconocer y resolver alertas.
- [x] Arquitectura móvil documentada para las limitaciones de cPanel.
- [x] Vista de salud del sistema y reporte PDF operativo.
- [x] Silenciamiento remoto autenticado y revisión física con GPIO25.

## Fase 5: robustez

- [ ] Token individual revocable por dispositivo.
- [ ] Cola local o reintentos para lecturas no enviadas.
- [x] Monitoreo de dispositivos sin conexión en web y app.
- [x] Retención y resumen horario mediante Cron Job de PHP.
- [x] Alarma enclavada con buzzer intermitente y rearme por peligro nuevo.
- [ ] Pruebas de carga, respaldo y restauración de MariaDB.
- [ ] Revisión contra la normativa industrial y contra incendios aplicable.

## Criterios mínimos de aceptación

1. Un dispositivo no registrado o con token incorrecto no puede escribir.
2. Una lectura válida aparece en `ultima_lectura.php` y `historial.php`.
3. Cada sensor crea una alerta al comenzar su detección, no una por cada envío.
4. Si humo/gas y flama aparecen juntos, se crean dos alertas identificables.
5. La web conserva la última información si la API falla temporalmente.
6. Ninguna contraseña o token real queda dentro del repositorio.

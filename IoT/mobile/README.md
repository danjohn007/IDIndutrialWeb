# ID Industrial Mobile

App Expo SDK 54/React Native para Android, iOS y vista web de desarrollo. Consume la
API PHP alojada en cPanel y no contiene funciones de reportes.

## Preparacion

1. Copia `.env.example` como `.env`.
2. Verifica la URL publica de `EXPO_PUBLIC_API_BASE_URL`.
3. Para notificaciones remotas, agrega el Project ID de EAS en
   `EXPO_PUBLIC_EAS_PROJECT_ID`.
4. Instala dependencias con `npm install`.
5. Inicia con `npm start`.

Para probar en un telefono usa Expo Go compatible con el SDK del proyecto o
genera un development build. Expo Go permite revisar el resto de la app, pero
las notificaciones push remotas requieren un development build. La sesion y el
token push se guardan en SecureStore en Android/iOS.

## Alcance actual

- Tema oscuro e identidad de ID Industrial.
- Navegacion inferior: Monitoreo, Alertas, En vivo, Dispositivos y Cuenta.
- Login Bearer y restauracion segura de sesion.
- Cierre de sesion con revocacion del token.
- Portada conectada al resumen compacto de la API.
- Actualizacion automatica cada cinco segundos mientras la app esta visible.
- Graficas en vivo con escalas separadas para temperatura, humedad y MQ-2.
- Linea temporal binaria para distinguir deteccion y ausencia de flama.
- Historial de alertas con filtros por sensor, severidad, estado y dispositivo.
- Paginacion de alertas en bloques de 20 eventos.
- Detalle de incidente con causa, lectura del evento, estado actual y contexto.
- Reconocimiento y resolucion para perfiles ADMIN y OPERADOR.
- Diagnostico de dispositivos: conexion, DHT11, MQ-2, KY-026 y datos de salud.
- Area segura adaptable para barras de estado y navegacion de Android/iOS.
- Activacion voluntaria de alertas push criticas desde la pantalla Cuenta.
- Apertura directa del incidente al tocar una notificacion.

No existe una pantalla ni un endpoint movil para generar reportes.

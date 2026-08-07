#include <WiFi.h>
#include <WebServer.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include "DHT.h"

const char* ssid = "INFINITUM7586_2.4";
const char* password = "sh53qASgY7";

const char* API_URL = "https://idactivos.digital/ID-Industrial/api/guardar_lectura.php";
const char* COMANDOS_URL = "https://idactivos.digital/ID-Industrial/api/comando_dispositivo.php";
const char* API_TOKEN = "WJuIUBSvjb46uUL4IBg4DulwZvbZ74Nn";
const char* DISPOSITIVO_ID = "ESP32_001";

#define DHTTYPE DHT11

constexpr uint8_t PIN_DHT = 17;
constexpr uint8_t PIN_FLAMA = 23;
constexpr uint8_t PIN_GAS = 34;
constexpr uint8_t PIN_LED_VERDE = 18;
constexpr uint8_t PIN_LED_AMARILLO = 19;
constexpr uint8_t PIN_LED_ROJO = 21;
constexpr uint8_t PIN_BUZZER = 22;
constexpr uint8_t PIN_BOTON_REVISION = 25;

constexpr float TEMPERATURA_ALERTA = 30.0;
constexpr float TEMPERATURA_ALARMA = 35.0;
constexpr int GAS_UMBRAL = 1600;
constexpr int GAS_UMBRAL_APAGADO = 1500;
constexpr uint8_t GAS_LECTURAS_CONFIRMAR = 2;
constexpr uint8_t GAS_LECTURAS_LIMPIAR = 2;
constexpr uint8_t FLAMA_LECTURAS_CONFIRMAR = 1;
constexpr uint8_t FLAMA_LECTURAS_LIMPIAR = 2;

constexpr unsigned long INTERVALO_LECTURA_MS = 2000;
constexpr unsigned long INTERVALO_API_MS = 10000;
constexpr unsigned long INTERVALO_REINTENTO_URGENTE_MS = 2000;
constexpr unsigned long INTERVALO_SERIAL_MS = 5000;
constexpr unsigned long INTERVALO_RECONEXION_MS = 15000;
constexpr unsigned long CALENTAMIENTO_MQ2_MS = 120000;
constexpr unsigned long INTERVALO_BUZZER_MS = 250;
constexpr unsigned long INTERVALO_LED_SILENCIADO_MS = 500;
// El servidor reentrega ordenes cada 5 s. Consultar antes solo crea conexiones TLS innecesarias.
constexpr unsigned long INTERVALO_COMANDOS_MS = 5000;
constexpr unsigned long BOTON_ANTIRREBOTE_MS = 50;
constexpr unsigned long BOTON_PULSACION_MS = 2000;

DHT dht(PIN_DHT, DHTTYPE);
WebServer server(80);

float temperatura = NAN;
float humedad = NAN;
float indiceCalor = NAN;
int lecturaGas = 0;
int lecturaFlama = HIGH;
bool dhtLecturaValida = false;
bool gasDetectado = false;
bool flamaDetectada = false;
bool peligroActivoAhora = false;
bool alarmaEnclavada = false;
bool alarmaSilenciada = false;
bool revisionFisicaPendiente = false;

String estado = "INICIANDO";
String saludDHT = "INICIANDO";
String saludMQ2 = "CALENTANDO";
String saludFlama = "OK";
String silenciadaPor = "NINGUNO";
String ultimoTipoAlarma = "";

unsigned long tiempoInicio = 0;
unsigned long ultimaLectura = 0;
unsigned long ultimoEnvioApi = 0;
unsigned long ultimaImpresion = 0;
unsigned long ultimaReconexion = 0;
unsigned long ultimoCambioBuzzer = 0;
unsigned long ultimaConsultaComandos = 0;
unsigned long comandoPendienteConfirmacion = 0;
unsigned long ultimoCambioBoton = 0;
unsigned long inicioPulsacionBoton = 0;

uint8_t erroresDHT = 0;
uint8_t lecturasMQ2Altas = 0;
uint8_t gasLecturasAltas = 0;
uint8_t gasLecturasBajas = 0;
uint8_t flamaLecturasDetectadas = 0;
uint8_t flamaLecturasLimpias = 0;
unsigned long contadorAlarmas = 0;
unsigned long contadorSilenciosEnLinea = 0;
unsigned long contadorSilenciosFisicos = 0;
unsigned long contadorResetsFisicos = 0;
bool buzzerActivo = false;
bool envioUrgentePendiente = false;
bool gasDetectadoAnterior = false;
bool flamaDetectadaAnterior = false;
bool temperaturaPeligrosaAnterior = false;
int estadoBotonRawAnterior = HIGH;
int estadoBotonEstable = HIGH;
bool pulsacionBotonAtendida = false;

void setup() {
  Serial.begin(115200);
  dht.begin();
  tiempoInicio = millis();

  pinMode(PIN_FLAMA, INPUT);
  pinMode(PIN_GAS, INPUT);
  pinMode(PIN_LED_VERDE, OUTPUT);
  pinMode(PIN_LED_AMARILLO, OUTPUT);
  pinMode(PIN_LED_ROJO, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);
  pinMode(PIN_BOTON_REVISION, INPUT_PULLUP);

  analogReadResolution(12);
  analogSetPinAttenuation(PIN_GAS, ADC_11db);

  apagarSalidas();

  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.persistent(false);
  conectarWifi();

  server.on("/", HTTP_GET, paginaPrincipal);
  server.on("/data", HTTP_GET, datosJSON);
  server.begin();

  Serial.println("Servidor web local iniciado");
}

void loop() {
  unsigned long ahora = millis();

  mantenerWifi();
  server.handleClient();

  if (ahora - ultimaLectura >= INTERVALO_LECTURA_MS || ultimaLectura == 0) {
    leerSensores();
    revisarSaludSensores();
    estabilizarDetectores();
    actualizarAlarmas();
    ultimaLectura = ahora;
  }

  actualizarBotonFisico();

  if (
    (alarmaEnclavada || comandoPendienteConfirmacion > 0)
    && (
      ultimaConsultaComandos == 0
      || ahora - ultimaConsultaComandos >= INTERVALO_COMANDOS_MS
    )
  ) {
    consultarComandosAPI();
    ultimaConsultaComandos = ahora;
  }

  actualizarSalidas();

  bool tocaEnvioUrgente =
    envioUrgentePendiente
    && (
      ultimoEnvioApi == 0
      || ahora - ultimoEnvioApi >= INTERVALO_REINTENTO_URGENTE_MS
    );
  bool tocaEnvioPeriodico = ahora - ultimoEnvioApi >= INTERVALO_API_MS;

  if (tocaEnvioUrgente || tocaEnvioPeriodico) {
    if (enviarDatosAPI()) {
      envioUrgentePendiente = false;
    }
    ultimoEnvioApi = ahora;
  }

  if (ahora - ultimaImpresion >= INTERVALO_SERIAL_MS) {
    imprimirMonitorSerie();
    ultimaImpresion = ahora;
  }

  delay(2);
}

void conectarWifi() {
  if (strlen(ssid) == 0 || strlen(password) == 0 || strcmp(ssid, "TU_WIFI") == 0) {
    Serial.println("Configura WiFi antes de cargar el firmware");
    return;
  }

  Serial.print("Conectando a WiFi");
  WiFi.begin(ssid, password);

  unsigned long inicioIntento = millis();

  while (WiFi.status() != WL_CONNECTED && millis() - inicioIntento < 15000) {
    delay(250);
    Serial.print(".");
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println();
    Serial.print("WiFi conectado. IP local: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println();
    Serial.println("WiFi no disponible; el monitoreo local continua");
  }
}

void mantenerWifi() {
  if (strlen(ssid) == 0 || strlen(password) == 0 || strcmp(ssid, "TU_WIFI") == 0) {
    return;
  }

  if (WiFi.status() == WL_CONNECTED) {
    return;
  }

  unsigned long ahora = millis();

  if (ahora - ultimaReconexion < INTERVALO_RECONEXION_MS) {
    return;
  }

  ultimaReconexion = ahora;
  WiFi.disconnect();
  WiFi.begin(ssid, password);
}

void leerSensores() {
  float nuevaHumedad = dht.readHumidity();
  float nuevaTemperatura = dht.readTemperature();

  dhtLecturaValida = !isnan(nuevaHumedad) && !isnan(nuevaTemperatura);

  if (dhtLecturaValida) {
    humedad = nuevaHumedad;
    temperatura = nuevaTemperatura;
    indiceCalor = dht.computeHeatIndex(temperatura, humedad, false);
  } else {
    humedad = NAN;
    temperatura = NAN;
    indiceCalor = NAN;
  }

  lecturaFlama = digitalRead(PIN_FLAMA);
  lecturaGas = analogRead(PIN_GAS);
}

void revisarSaludSensores() {
  if (!dhtLecturaValida) {
    if (erroresDHT < 10) {
      erroresDHT++;
    }

    saludDHT = erroresDHT >= 5 ? "FALLO" : "REVISAR";
  } else {
    erroresDHT = 0;
    saludDHT = "OK";
  }

  if (millis() - tiempoInicio < CALENTAMIENTO_MQ2_MS) {
    saludMQ2 = "CALENTANDO";
  } else {
    // En este montaje, 0 ADC en aire limpio es una lectura normal.
    if (lecturaGas >= 4080 && lecturasMQ2Altas < 20) {
      lecturasMQ2Altas++;
    } else if (lecturaGas < 4080) {
      lecturasMQ2Altas = 0;
    }

    saludMQ2 = lecturasMQ2Altas >= 10 ? "REVISAR" : "OK";
  }

  saludFlama = "OK";
}

void estabilizarDetectores() {
  bool mq2Listo = millis() - tiempoInicio >= CALENTAMIENTO_MQ2_MS;

  if (!mq2Listo) {
    gasDetectado = false;
    gasLecturasAltas = 0;
    gasLecturasBajas = 0;
  } else if (lecturaGas >= GAS_UMBRAL) {
    if (gasLecturasAltas < 20) {
      gasLecturasAltas++;
    }
    gasLecturasBajas = 0;
  } else if (lecturaGas <= GAS_UMBRAL_APAGADO) {
    if (gasLecturasBajas < 20) {
      gasLecturasBajas++;
    }
    gasLecturasAltas = 0;
  }

  if (gasLecturasAltas >= GAS_LECTURAS_CONFIRMAR) {
    gasDetectado = true;
  }
  if (gasLecturasBajas >= GAS_LECTURAS_LIMPIAR) {
    gasDetectado = false;
  }

  bool flamaRawDetectada = lecturaFlama == LOW;
  if (flamaRawDetectada) {
    if (flamaLecturasDetectadas < 20) {
      flamaLecturasDetectadas++;
    }
    flamaLecturasLimpias = 0;
  } else {
    if (flamaLecturasLimpias < 20) {
      flamaLecturasLimpias++;
    }
    flamaLecturasDetectadas = 0;
  }

  if (flamaLecturasDetectadas >= FLAMA_LECTURAS_CONFIRMAR) {
    flamaDetectada = true;
  }
  if (flamaLecturasLimpias >= FLAMA_LECTURAS_LIMPIAR) {
    flamaDetectada = false;
  }
}

bool hayGasDetectado() {
  return gasDetectado;
}

bool hayFlamaDetectada() {
  return flamaDetectada;
}

void actualizarAlarmas() {
  bool hayFlama = hayFlamaDetectada();
  bool hayGas = hayGasDetectado();
  bool temperaturaPeligrosa = dhtLecturaValida && temperatura >= TEMPERATURA_ALARMA;
  bool temperaturaAlta = dhtLecturaValida && temperatura >= TEMPERATURA_ALERTA;
  bool nuevaCondicionCritica =
    (hayGas && !gasDetectadoAnterior)
    || (hayFlama && !flamaDetectadaAnterior)
    || (temperaturaPeligrosa && !temperaturaPeligrosaAnterior);

  String estadoAnterior = estado;
  peligroActivoAhora = hayFlama || hayGas || temperaturaPeligrosa;

  if (peligroActivoAhora && !alarmaEnclavada) {
    alarmaEnclavada = true;
    alarmaSilenciada = false;
    revisionFisicaPendiente = true;
    silenciadaPor = "NINGUNO";
    contadorAlarmas++;
    ultimoTipoAlarma = tipoPeligroActual();
  } else if (nuevaCondicionCritica && alarmaEnclavada && alarmaSilenciada) {
    // Una condicion critica nueva vuelve a armar el sonido.
    alarmaSilenciada = false;
    silenciadaPor = "NINGUNO";
    envioUrgentePendiente = true;
  }

  if (peligroActivoAhora) {
    ultimoTipoAlarma = tipoPeligroActual();
  }

  if (alarmaEnclavada || peligroActivoAhora) {
    estado = "ALARMA";
  } else if (temperaturaAlta || saludDHT == "FALLO") {
    estado = "ALERTA";
  } else {
    estado = "NORMAL";
  }

  if (
    (estado == "ALARMA" && estadoAnterior != "ALARMA")
    || nuevaCondicionCritica
  ) {
    envioUrgentePendiente = true;
  }

  gasDetectadoAnterior = hayGas;
  flamaDetectadaAnterior = hayFlama;
  temperaturaPeligrosaAnterior = temperaturaPeligrosa;
}

void actualizarSalidas() {
  if (estado == "ALARMA") {
    digitalWrite(PIN_LED_VERDE, LOW);
    digitalWrite(PIN_LED_AMARILLO, LOW);
    digitalWrite(
      PIN_LED_ROJO,
      alarmaSilenciada
        ? ((millis() / INTERVALO_LED_SILENCIADO_MS) % 2 == 0 ? HIGH : LOW)
        : HIGH
    );

    if (
      alarmaEnclavada
      && !alarmaSilenciada
      && millis() - ultimoCambioBuzzer >= INTERVALO_BUZZER_MS
    ) {
      buzzerActivo = !buzzerActivo;
      digitalWrite(PIN_BUZZER, buzzerActivo ? HIGH : LOW);
      ultimoCambioBuzzer = millis();
    } else if (!alarmaEnclavada || alarmaSilenciada) {
      buzzerActivo = false;
      digitalWrite(PIN_BUZZER, LOW);
    }

    return;
  }

  buzzerActivo = false;
  digitalWrite(PIN_BUZZER, LOW);
  digitalWrite(PIN_LED_ROJO, LOW);
  digitalWrite(PIN_LED_AMARILLO, estado == "ALERTA" ? HIGH : LOW);
  digitalWrite(PIN_LED_VERDE, estado == "NORMAL" ? HIGH : LOW);
}

void actualizarBotonFisico() {
  int lecturaBoton = digitalRead(PIN_BOTON_REVISION);
  unsigned long ahora = millis();

  if (lecturaBoton != estadoBotonRawAnterior) {
    estadoBotonRawAnterior = lecturaBoton;
    ultimoCambioBoton = ahora;
  }

  if (
    ahora - ultimoCambioBoton >= BOTON_ANTIRREBOTE_MS
    && lecturaBoton != estadoBotonEstable
  ) {
    estadoBotonEstable = lecturaBoton;

    if (estadoBotonEstable == LOW) {
      inicioPulsacionBoton = ahora;
      pulsacionBotonAtendida = false;
    } else {
      inicioPulsacionBoton = 0;
      pulsacionBotonAtendida = false;
    }
  }

  if (
    estadoBotonEstable == LOW
    && !pulsacionBotonAtendida
    && inicioPulsacionBoton > 0
    && ahora - inicioPulsacionBoton >= BOTON_PULSACION_MS
  ) {
    pulsacionBotonAtendida = true;
    atenderBotonFisico();
  }
}

void atenderBotonFisico() {
  if (!alarmaEnclavada) {
    Serial.println("Boton: no hay una alarma enclavada");
    return;
  }

  if (peligroActivoAhora) {
    if (!alarmaSilenciada) {
      silenciarAlarma("BOTON_FISICO");
      contadorSilenciosFisicos++;
      Serial.println("Boton: buzzer silenciado; el peligro sigue activo");
    } else {
      Serial.println("Boton: no se puede restablecer mientras exista peligro");
    }
    return;
  }

  restablecerAlarmaFisica();
}

void silenciarAlarma(const String& origen) {
  if (!alarmaEnclavada || alarmaSilenciada) {
    return;
  }

  alarmaSilenciada = true;
  revisionFisicaPendiente = true;
  silenciadaPor = origen;
  buzzerActivo = false;
  digitalWrite(PIN_BUZZER, LOW);
  envioUrgentePendiente = true;
}

void restablecerAlarmaFisica() {
  if (!alarmaEnclavada || peligroActivoAhora) {
    return;
  }

  alarmaEnclavada = false;
  alarmaSilenciada = false;
  revisionFisicaPendiente = false;
  silenciadaPor = "NINGUNO";
  ultimoTipoAlarma = "";
  contadorResetsFisicos++;
  buzzerActivo = false;
  digitalWrite(PIN_BUZZER, LOW);

  bool temperaturaAlta = dhtLecturaValida && temperatura >= TEMPERATURA_ALERTA;
  estado = (temperaturaAlta || saludDHT == "FALLO") ? "ALERTA" : "NORMAL";
  envioUrgentePendiente = true;
  Serial.println("Boton: revision completada y alarma restablecida");
}

bool consultarComandosAPI() {
  if (WiFi.status() != WL_CONNECTED) {
    return false;
  }

  WiFiClientSecure clienteSeguro;
  clienteSeguro.setInsecure();
  clienteSeguro.setHandshakeTimeout(12);

  HTTPClient http;
  http.setConnectTimeout(8000);
  http.setTimeout(8000);
  http.useHTTP10(true);

  if (!http.begin(clienteSeguro, COMANDOS_URL)) {
    return false;
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("X-API-TOKEN", API_TOKEN);
  http.addHeader("Connection", "close");

  unsigned long confirmacionEnviada = comandoPendienteConfirmacion;
  String json =
    "{\"dispositivo_id\":\"" + String(DISPOSITIVO_ID)
    + "\",\"comando_aplicado_id\":" + String(confirmacionEnviada) + "}";

  int codigoHttp = http.POST(json);
  String respuesta = codigoHttp > 0 ? http.getString() : "";
  if (codigoHttp <= 0) {
    Serial.print("Comandos: fallo de transporte ");
    Serial.print(codigoHttp);
    Serial.print(" - ");
    Serial.println(HTTPClient::errorToString(codigoHttp));
    Serial.print("WiFi RSSI: ");
    Serial.print(WiFi.RSSI());
    Serial.print(" dBm, heap libre: ");
    Serial.println(ESP.getFreeHeap());
  }
  http.end();

  if (codigoHttp < 200 || codigoHttp >= 300) {
    Serial.print("Comandos HTTP: ");
    Serial.println(codigoHttp);
    if (respuesta.length() > 0) {
      Serial.println(respuesta);
    }
    return false;
  }

  if (confirmacionEnviada > 0) {
    comandoPendienteConfirmacion = 0;
  }

  if (respuesta.indexOf("\"accion\":\"SILENCIAR_ALARMA\"") < 0) {
    return true;
  }

  unsigned long comandoId = extraerEnteroJson(respuesta, "\"id\":");
  if (comandoId == 0) {
    Serial.println("Comando recibido sin identificador valido");
    return false;
  }

  if (alarmaEnclavada && !alarmaSilenciada) {
    silenciarAlarma("APP_MOVIL");
    contadorSilenciosEnLinea++;
    Serial.println("App: buzzer silenciado");
  }

  comandoPendienteConfirmacion = comandoId;
  return true;
}

unsigned long extraerEnteroJson(const String& contenido, const String& clave) {
  int posicion = contenido.indexOf(clave);
  if (posicion < 0) {
    return 0;
  }

  posicion += clave.length();
  while (posicion < contenido.length() && contenido.charAt(posicion) == ' ') {
    posicion++;
  }

  unsigned long valor = 0;
  bool encontroDigito = false;
  while (posicion < contenido.length()) {
    char caracter = contenido.charAt(posicion);
    if (caracter < '0' || caracter > '9') {
      break;
    }
    encontroDigito = true;
    valor = valor * 10 + (caracter - '0');
    posicion++;
  }

  return encontroDigito ? valor : 0;
}

bool enviarDatosAPI() {
  if (WiFi.status() != WL_CONNECTED) {
    return false;
  }

  if (
    strlen(API_TOKEN) < 32 ||
    strcmp(API_TOKEN, "CAMBIA_ESTE_TOKEN_SECRETO") == 0 ||
    strstr(API_URL, "tudominio.com") != nullptr
  ) {
    Serial.println("API pendiente de configurar");
    return false;
  }

  WiFiClientSecure clienteSeguro;
  clienteSeguro.setInsecure();
  clienteSeguro.setHandshakeTimeout(12);

  HTTPClient http;
  http.setConnectTimeout(8000);
  http.setTimeout(8000);
  http.useHTTP10(true);

  if (!http.begin(clienteSeguro, API_URL)) {
    Serial.println("No fue posible iniciar la conexion HTTP");
    return false;
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("X-API-TOKEN", API_TOKEN);
  http.addHeader("Connection", "close");

  float gasPorcentaje = constrain((lecturaGas / 4095.0) * 100.0, 0.0, 100.0);
  int gasDetectado = hayGasDetectado() ? 1 : 0;
  int flamaDetectada = hayFlamaDetectada() ? 1 : 0;

  String json;
  json.reserve(950);

  json += "{";
  json += "\"dispositivo_id\":\"" + String(DISPOSITIVO_ID) + "\",";
  json += "\"temperatura\":" + numeroJson(temperatura, 1) + ",";
  json += "\"humedad\":" + numeroJson(humedad, 1) + ",";
  json += "\"indice_calor\":" + numeroJson(indiceCalor, 1) + ",";
  json += "\"gas_raw\":" + String(lecturaGas) + ",";
  json += "\"gas_porcentaje\":" + String(gasPorcentaje, 2) + ",";
  json += "\"gas_umbral\":" + String(GAS_UMBRAL) + ",";
  json += "\"gas_detectado\":" + String(gasDetectado) + ",";
  json += "\"flama_detectada\":" + String(flamaDetectada) + ",";
  json += "\"temperatura_alerta\":" + String(TEMPERATURA_ALERTA, 1) + ",";
  json += "\"temperatura_alarma\":" + String(TEMPERATURA_ALARMA, 1) + ",";
  json += "\"estado_general\":\"" + estado + "\",";
  json += "\"tipo_alerta\":\"" + tipoAlertaActual() + "\",";
  json += "\"peligro_activo\":" + String(peligroActivoAhora ? 1 : 0) + ",";
  json += "\"alarma_enclavada\":" + String(alarmaEnclavada ? 1 : 0) + ",";
  json += "\"alarma_silenciada\":" + String(alarmaSilenciada ? 1 : 0) + ",";
  json += "\"revision_fisica_pendiente\":" + String(revisionFisicaPendiente ? 1 : 0) + ",";
  json += "\"buzzer_encendido\":" + String(
    alarmaEnclavada && !alarmaSilenciada ? 1 : 0
  ) + ",";
  json += "\"modo_operacion\":\"" + modoOperacionActual() + "\",";
  json += "\"silenciada_por\":\"" + silenciadaPor + "\",";
  json += "\"salud_dht\":\"" + saludDHT + "\",";
  json += "\"salud_mq2\":\"" + saludMQ2 + "\",";
  json += "\"salud_flama\":\"" + saludFlama + "\",";
  json += "\"wifi_rssi\":" + String(WiFi.RSSI()) + ",";
  json += "\"tiempo_encendido\":" + String(millis() / 1000) + ",";
  json += "\"mq2_calentamiento_total_s\":" + String(CALENTAMIENTO_MQ2_MS / 1000) + ",";
  json += "\"contador_alarmas\":" + String(contadorAlarmas) + ",";
  json += "\"contador_silencios_en_linea\":" + String(contadorSilenciosEnLinea) + ",";
  json += "\"contador_silencios_fisicos\":" + String(contadorSilenciosFisicos) + ",";
  json += "\"contador_resets_fisicos\":" + String(contadorResetsFisicos);
  json += "}";

  int codigoHttp = http.POST(json);

  Serial.print("API HTTP: ");
  Serial.println(codigoHttp);

  if (codigoHttp > 0) {
    Serial.println(http.getString());
  } else {
    Serial.print("API transporte: ");
    Serial.println(HTTPClient::errorToString(codigoHttp));
  }

  http.end();
  return codigoHttp >= 200 && codigoHttp < 300;
}

String numeroJson(float valor, uint8_t decimales) {
  if (isnan(valor)) {
    return "null";
  }

  return String(valor, (unsigned int)decimales);
}

String tipoAlertaActual() {
  if (estado == "NORMAL" && !alarmaEnclavada) {
    return "";
  }

  bool gasActual = hayGasDetectado();
  bool flamaActual = hayFlamaDetectada();

  if (gasActual && flamaActual) {
    return "Flama + Humo/Gas";
  }

  if (flamaActual) {
    return "Flama";
  }

  if (gasActual) {
    return "Gas/Humo";
  }

  if (alarmaEnclavada && ultimoTipoAlarma.length() > 0) {
    return ultimoTipoAlarma;
  }

  if (saludDHT == "FALLO") {
    return "Fallo DHT11";
  }

  return "Temperatura alta";
}

String tipoPeligroActual() {
  bool gasActual = hayGasDetectado();
  bool flamaActual = hayFlamaDetectada();
  bool temperaturaPeligrosa =
    dhtLecturaValida && temperatura >= TEMPERATURA_ALARMA;

  if (gasActual && flamaActual) {
    return "Flama + Humo/Gas";
  }
  if (flamaActual) {
    return "Flama";
  }
  if (gasActual) {
    return "Gas/Humo";
  }
  if (temperaturaPeligrosa) {
    return "Temperatura peligrosa";
  }
  return "Alarma general";
}

String modoOperacionActual() {
  if (alarmaEnclavada) {
    if (alarmaSilenciada) {
      return peligroActivoAhora
        ? "ALARMA_SILENCIADA"
        : "REVISION_PENDIENTE";
    }
    return "ALARMA_SONORA";
  }
  if (estado == "ALERTA") {
    return "ALERTA";
  }
  return "NORMAL";
}

void imprimirMonitorSerie() {
  Serial.println("----- MONITOREO INDUSTRIAL -----");

  Serial.print("Temperatura: ");
  Serial.println(numeroJson(temperatura, 1));

  Serial.print("Humedad: ");
  Serial.println(numeroJson(humedad, 1));

  Serial.print("Indice calor: ");
  Serial.println(numeroJson(indiceCalor, 1));

  Serial.print("Gas/Humo AO: ");
  Serial.println(lecturaGas);

  Serial.print("Gas detectado: ");
  Serial.println(hayGasDetectado() ? "SI" : "NO");

  Serial.print("Umbral gas: ");
  Serial.println(GAS_UMBRAL);

  Serial.print("Flama DO: ");
  Serial.println(lecturaFlama);

  Serial.print("Estado: ");
  Serial.println(estado);

  Serial.print("Modo: ");
  Serial.println(modoOperacionActual());

  Serial.print("Tipo alerta: ");
  Serial.println(tipoAlertaActual());

  Serial.print("Peligro / Enclavada / Silenciada / Revision: ");
  Serial.print(peligroActivoAhora ? "SI" : "NO");
  Serial.print(" / ");
  Serial.print(alarmaEnclavada ? "SI" : "NO");
  Serial.print(" / ");
  Serial.print(alarmaSilenciada ? "SI" : "NO");
  Serial.print(" / ");
  Serial.println(revisionFisicaPendiente ? "SI" : "NO");

  Serial.print("Silenciada por: ");
  Serial.println(silenciadaPor);

  Serial.print("Salud DHT / MQ2 / Flama: ");
  Serial.print(saludDHT);
  Serial.print(" / ");
  Serial.print(saludMQ2);
  Serial.print(" / ");
  Serial.println(saludFlama);

  Serial.print("WiFi RSSI: ");
  Serial.println(WiFi.status() == WL_CONNECTED ? String(WiFi.RSSI()) : "SIN WIFI");

  Serial.println("--------------------------------");
}

void apagarSalidas() {
  digitalWrite(PIN_LED_VERDE, LOW);
  digitalWrite(PIN_LED_AMARILLO, LOW);
  digitalWrite(PIN_LED_ROJO, LOW);
  digitalWrite(PIN_BUZZER, LOW);
}

void paginaPrincipal() {
  static const char html[] PROGMEM = R"rawliteral(
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Monitor ESP32</title>
  <style>
    :root {
      color-scheme: dark;
      --bg: #12161b;
      --surface: #1e252b;
      --text: #e0e0e0;
      --muted: #9aa8b3;
      --normal: #00a3ff;
      --ok: #24d15c;
      --warning: #ffb000;
      --critical: #ff453a;
      --line: #33404a;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      padding: 20px;
      background: var(--bg);
      color: var(--text);
      font-family: Arial, sans-serif;
    }

    main {
      width: min(680px, 100%);
      margin: auto;
    }

    header {
      margin-bottom: 18px;
    }

    h1, h2, p {
      margin: 0;
    }

    h1 {
      font-size: 1.8rem;
    }

    p {
      color: var(--muted);
      margin-top: 5px;
    }

    .panel {
      margin-bottom: 12px;
      padding: 16px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: var(--surface);
    }

    #estado {
      color: var(--ok);
      font-size: 2.4rem;
      font-weight: 800;
    }

    #estado.ALERTA { color: var(--warning); }
    #estado.ALARMA { color: var(--critical); }

    .grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    .dato {
      padding: 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
    }

    .dato.sensor-alerta {
      border-color: var(--critical);
      background: rgba(255, 69, 58, .08);
    }

    .dato span {
      display: block;
      color: var(--muted);
      font-size: .8rem;
      margin-bottom: 6px;
    }

    .OK { color: var(--ok); }
    .CALENTANDO, .REVISAR { color: var(--warning); }
    .FALLO, .SIN.CONEXION { color: var(--critical); }

    @media(max-width:520px) {
      .grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<main>
  <header>
    <h1>Monitoreo industrial</h1>
    <p>Panel local del ESP32</p>
  </header>

  <section class="panel">
    <span>Estado general</span>
    <div id="estado">---</div>
  </section>

  <section class="panel grid">
    <div class="dato"><span>Temperatura</span><b id="temp">--</b></div>
    <div class="dato"><span>Humedad</span><b id="hum">--</b></div>
    <div class="dato" id="gasCard">
      <span>MQ-2 Humo/Gas</span>
      <b id="gas">--</b>
      <small id="gasEstado">--</small>
    </div>
    <div class="dato" id="flamaCard">
      <span>KY-026 Flama</span>
      <b id="flama">--</b>
      <small id="flamaEstado">--</small>
    </div>
  </section>

  <section class="panel grid">
    <div class="dato"><span>DHT11</span><b id="saludDHT">--</b></div>
    <div class="dato"><span>MQ-2</span><b id="saludMQ2">--</b></div>
    <div class="dato"><span>KY-026</span><b id="saludFlama">--</b></div>
    <div class="dato"><span>Buzzer</span><b id="buzzer">--</b></div>
    <div class="dato"><span>Revision fisica</span><b id="revision">--</b></div>
    <div class="dato"><span>Ultima lectura</span><b id="ultima">--</b></div>
  </section>
</main>

<script>
function estadoClase(id, valor) {
  const elemento = document.getElementById(id);
  elemento.textContent = valor;
  elemento.className = valor;
}

function actualizarDetector(cardId, estadoId, detectado) {
  const card = document.getElementById(cardId);
  card.classList.toggle('sensor-alerta', detectado);
  document.getElementById(estadoId).textContent = detectado ? 'DETECTADO' : 'NORMAL';
}

async function actualizar() {
  try {
    const respuesta = await fetch('/data', { cache: 'no-store' });
    const datos = await respuesta.json();

    document.getElementById('temp').textContent = datos.temperatura + ' C';
    document.getElementById('hum').textContent = datos.humedad + ' %';
    document.getElementById('gas').textContent = datos.gas;
    document.getElementById('flama').textContent = datos.flama;
    document.getElementById('buzzer').textContent =
      datos.buzzerEncendido ? 'SONANDO' : (datos.alarmaSilenciada ? 'SILENCIADO' : 'APAGADO');
    document.getElementById('revision').textContent =
      datos.revisionFisicaPendiente ? 'PENDIENTE' : 'COMPLETA';
    document.getElementById('ultima').textContent = datos.ultimaLectura + ' s';
    actualizarDetector('gasCard', 'gasEstado', Boolean(datos.gasDetectado));
    actualizarDetector('flamaCard', 'flamaEstado', datos.flama === 'DETECTADA');

    estadoClase('estado', datos.estado);
    estadoClase('saludDHT', datos.saludDHT);
    estadoClase('saludMQ2', datos.saludMQ2);
    estadoClase('saludFlama', datos.saludFlama);
  } catch (error) {
    estadoClase('estado', 'SIN CONEXION');
  }
}

actualizar();
setInterval(actualizar, 2000);
</script>
</body>
</html>
)rawliteral";

  server.send_P(200, "text/html; charset=UTF-8", html);
}

void datosJSON() {
  String flamaTexto = lecturaFlama == LOW ? "DETECTADA" : "NO";
  unsigned long segundosDesdeLectura = (millis() - ultimaLectura) / 1000;

  String json;
  json.reserve(620);

  json += "{";
  json += "\"temperatura\":" + numeroJson(temperatura, 1) + ",";
  json += "\"humedad\":" + numeroJson(humedad, 1) + ",";
  json += "\"indiceCalor\":" + numeroJson(indiceCalor, 1) + ",";
  json += "\"gas\":" + String(lecturaGas) + ",";
  json += "\"gasDetectado\":" + String(hayGasDetectado() ? "true" : "false") + ",";
  json += "\"gasUmbral\":" + String(GAS_UMBRAL) + ",";
  json += "\"flama\":\"" + flamaTexto + "\",";
  json += "\"tipoAlerta\":\"" + tipoAlertaActual() + "\",";
  json += "\"estado\":\"" + estado + "\",";
  json += "\"peligroActivo\":" + String(peligroActivoAhora ? "true" : "false") + ",";
  json += "\"alarmaEnclavada\":" + String(alarmaEnclavada ? "true" : "false") + ",";
  json += "\"alarmaSilenciada\":" + String(alarmaSilenciada ? "true" : "false") + ",";
  json += "\"revisionFisicaPendiente\":" + String(
    revisionFisicaPendiente ? "true" : "false"
  ) + ",";
  json += "\"buzzerEncendido\":" + String(
    alarmaEnclavada && !alarmaSilenciada ? "true" : "false"
  ) + ",";
  json += "\"modoOperacion\":\"" + modoOperacionActual() + "\",";
  json += "\"silenciadaPor\":\"" + silenciadaPor + "\",";
  json += "\"saludDHT\":\"" + saludDHT + "\",";
  json += "\"saludMQ2\":\"" + saludMQ2 + "\",";
  json += "\"saludFlama\":\"" + saludFlama + "\",";
  json += "\"calentamientoMQ2Restante\":" + String(
    millis() - tiempoInicio >= CALENTAMIENTO_MQ2_MS
      ? 0
      : (CALENTAMIENTO_MQ2_MS - (millis() - tiempoInicio) + 999) / 1000
  ) + ",";
  json += "\"ultimaLectura\":" + String(segundosDesdeLectura);
  json += "}";

  server.send(200, "application/json; charset=UTF-8", json);
}

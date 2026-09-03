// Diagnostico exclusivo para la estacion manual en D32 / GPIO32.
// Abre Monitor Serial a 115200.
// Prueba directa: D32 ---- resistencia 4.7k ---- GND del mismo ESP32.

constexpr uint8_t PIN_ESTACION_MANUAL = 32;
int estadoAnterior = HIGH;
unsigned long ultimoReporte = 0;

void setup() {
  Serial.begin(115200);
  delay(1000);

  pinMode(PIN_ESTACION_MANUAL, INPUT_PULLUP);
  estadoAnterior = digitalRead(PIN_ESTACION_MANUAL);

  Serial.println();
  Serial.println("=== DIAGNOSTICO SOLO D32 / GPIO32 ===");
  Serial.println("Normal: D32 suelto = NORMAL / HIGH");
  Serial.println("Prueba: D32 -> resistencia 4.7k -> GND = ACTIVADA / LOW");
  Serial.println();
}

void loop() {
  int estadoActual = digitalRead(PIN_ESTACION_MANUAL);

  if (estadoActual != estadoAnterior) {
    estadoAnterior = estadoActual;
    imprimirEstado(estadoActual);
  }

  if (millis() - ultimoReporte >= 1000) {
    ultimoReporte = millis();
    imprimirEstado(estadoActual);
  }

  delay(20);
}

void imprimirEstado(int estadoPin) {
  Serial.print("D32 / GPIO32: ");
  if (estadoPin == LOW) {
    Serial.println("ACTIVADA / LOW");
  } else {
    Serial.println("NORMAL / HIGH");
  }
}

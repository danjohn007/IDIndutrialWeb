// Diagnostico exclusivo para la estacion manual en D25 / GPIO25.
// Abre Monitor Serial a 115200.
// Prueba directa: D25 ---- resistencia 4.7k ---- GND del mismo ESP32.

constexpr uint8_t PIN_ESTACION_MANUAL = 25;
int estadoAnterior = HIGH;
unsigned long ultimoReporte = 0;

void setup() {
  Serial.begin(115200);
  delay(1000);

  pinMode(PIN_ESTACION_MANUAL, INPUT_PULLUP);
  estadoAnterior = digitalRead(PIN_ESTACION_MANUAL);

  Serial.println();
  Serial.println("=== DIAGNOSTICO SOLO D25 / GPIO25 ===");
  Serial.println("Normal: D25 suelto = NORMAL / HIGH");
  Serial.println("Prueba: D25 -> resistencia 4.7k -> GND = ACTIVADA / LOW");
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
  Serial.print("D25 / GPIO25: ");
  if (estadoPin == LOW) {
    Serial.println("ACTIVADA / LOW");
  } else {
    Serial.println("NORMAL / HIGH");
  }
}

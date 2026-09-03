// Diagnostico simple para encontrar el pin correcto de la estacion manual.
// Abre Monitor Serial a 115200 y toca cada GPIO contra GND con la resistencia 4.7k.

struct PinPrueba {
  uint8_t pin;
  const char* nombre;
  int anterior;
};

PinPrueba pines[] = {
  {32, "GPIO32 / G32 / IO32", HIGH},
  {33, "GPIO33 / G33 / IO33", HIGH},
  {27, "GPIO27 / G27 / IO27", HIGH},
  {26, "GPIO26 / G26 / IO26", HIGH},
  {25, "GPIO25 / G25 / IO25", HIGH},
  {23, "GPIO23 / G23 / IO23", HIGH},
  {14, "GPIO14 / G14 / IO14", HIGH},
  {13, "GPIO13 / G13 / IO13", HIGH}
};

constexpr unsigned long INTERVALO_REPORTE_MS = 1000;
unsigned long ultimoReporte = 0;

void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println();
  Serial.println("=== DIAGNOSTICO ESTACION MANUAL ===");
  Serial.println("Conecta: GPIO -> resistencia 4.7k -> GND");
  Serial.println("Cuando el pin detecte GND, saldra como ACTIVADO.");
  Serial.println();

  for (PinPrueba& prueba : pines) {
    pinMode(prueba.pin, INPUT_PULLUP);
    prueba.anterior = digitalRead(prueba.pin);
  }
}

void loop() {
  bool huboCambio = false;

  for (PinPrueba& prueba : pines) {
    int lectura = digitalRead(prueba.pin);
    if (lectura != prueba.anterior) {
      prueba.anterior = lectura;
      huboCambio = true;
      Serial.print(prueba.nombre);
      Serial.print(": ");
      Serial.println(lectura == LOW ? "ACTIVADO / LOW" : "NORMAL / HIGH");
    }
  }

  if (millis() - ultimoReporte >= INTERVALO_REPORTE_MS) {
    ultimoReporte = millis();
    Serial.print("Estado actual -> ");
    for (PinPrueba& prueba : pines) {
      if (digitalRead(prueba.pin) == LOW) {
        Serial.print(prueba.nombre);
        Serial.print(" ACTIVADO | ");
        huboCambio = true;
      }
    }
    if (!huboCambio) {
      Serial.print("ningun pin activado");
    }
    Serial.println();
  }

  delay(20);
}

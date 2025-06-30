#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>
#include <ArduinoJson.h>

const char *ssid = "SSID";
const char *password = "PASSWORD";

#define DHT_PIN 4        // Pin où est connecté le DHT11
#define DHT_TYPE DHT11   
DHT dht(DHT_PIN, DHT_TYPE);

// Configuration base de données
#define LED_WIFI_PIN 5

const char* server_url = "http://raspberrypi.local:8084/insert_data.php";
const char* db_user = "user";
const char* db_password = "password";
const char* db_name = "name database";

unsigned long previousMillis = 0;
const long interval = 3600000; 

void setup() {
  Serial.begin(115200);

  pinMode(LED_WIFI_PIN, OUTPUT);
  digitalWrite(LED_WIFI_PIN, LOW);

  dht.begin();
  Serial.println("DHT11 initialisé");
  
  WiFi.begin(ssid, password);
  Serial.print("Connexion au WiFi");
  
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  
  Serial.println();
  Serial.println("WiFi connecté !");
  Serial.print("Adresse IP: ");
  Serial.println(WiFi.localIP());

  delay(2000);

  float humidity = dht.readHumidity();
  float temperature = dht.readTemperature();

  if (isnan(humidity) || isnan(temperature)) {
    Serial.println("Erreur de lecture du DHT11 (démarrage) !");
  } else {
    Serial.print("Température (démarrage): ");
    Serial.print(temperature);
    Serial.print("°C, Humidité: ");
    Serial.print(humidity);
    Serial.println("%");
    sendDataToDatabase(temperature, humidity);
  }

  previousMillis = millis(); 
}

void loop() {
  unsigned long currentMillis = millis();
  
  if (currentMillis - previousMillis >= interval) {
    previousMillis = currentMillis;

    checkWiFiConnection();
    
    float humidity = dht.readHumidity();
    float temperature = dht.readTemperature();
    
    if (isnan(humidity) || isnan(temperature)) {
      Serial.println("Erreur de lecture du DHT11 !");
      return;
    }
    
    // Show variables
    Serial.print("Température: ");
    Serial.print(temperature);
    Serial.print("°C, Humidité: ");
    Serial.print(humidity);
    Serial.println("%");
    
    sendDataToDatabase(temperature, humidity);
  }
}

void sendDataToDatabase(float temperature, float humidity) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(server_url);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    
    String postData = "temperature=" + String(temperature) + 
                     "&humidity=" + String(humidity) +
                     "&sensor_id=ESP32_01"; // ID of the sensor
    
    Serial.println("Envoi des données: " + postData);
    
    int httpResponseCode = http.POST(postData);
    
    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.println("Code de réponse HTTP: " + String(httpResponseCode));
      Serial.println("Réponse: " + response);
      
      if (httpResponseCode == 200) {
        Serial.println("Données envoyées avec succès !");
      }
    } else {
      Serial.print("Erreur lors de l'envoi: ");
      Serial.println(httpResponseCode);
    }
    
    http.end();
  } else {
    Serial.println("WiFi non connecté !");
  }
}

void checkWiFiConnection() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi déconnecté, tentative de reconnexion...");
    digitalWrite(LED_WIFI_PIN, LOW);
    
    Serial.println("Reconnexion WiFi...");
    WiFi.begin(ssid, password);
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
      delay(500);
      Serial.print(".");
      attempts++;
    }
    
    if (WiFi.status() == WL_CONNECTED) {
      Serial.println("\nWiFi reconnecté !");
      digitalWrite(LED_WIFI_PIN, HIGH);
    } else {
      Serial.println("\nÉchec de reconnexion WiFi");
      digitalWrite(LED_WIFI_PIN, LOW);
    }
  } else {
    digitalWrite(LED_WIFI_PIN, HIGH);
  }
}
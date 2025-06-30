<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$servername = getenv('DB_HOST')
$username   = getenv('DB_USER')
$password   = getenv('DB_PASSWORD')
$dbname     = getenv('DB_NAME')
$port       = getenv('DB_PORT')

// Test de connexion (GET ?test=1)
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['test'])) {
    try {
        $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Forcer timezone Europe/Paris pour la session
        $conn->exec("SET time_zone = 'Europe/Paris'");
        echo json_encode([
            'status' => 'success',
            'message' => 'Connexion à la base de données réussie',
            'server' => $servername,
            'database' => $dbname
        ]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Erreur de connexion DB: ' . $e->getMessage()
        ]);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $temperature = isset($_POST['temperature']) ? floatval($_POST['temperature']) : null;
    $humidity    = isset($_POST['humidity']) ? floatval($_POST['humidity']) : null;
    $sensor_id   = isset($_POST['sensor_id']) ? $_POST['sensor_id'] : 'ESP32_DEFAULT';

    error_log("Données reçues - Temp: $temperature, Hum: $humidity, ID: $sensor_id");

    if ($temperature !== null && $humidity !== null) {
        try {
            $maxRetries = 3;
            $retryDelay = 1; 
            for ($i = 0; $i < $maxRetries; $i++) {
                try {
                    $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    // Forcer timezone Europe/Paris pour la session
                    $conn->exec("SET time_zone = 'Europe/Paris'");
                    break;
                } catch(PDOException $e) {
                    if ($i == $maxRetries - 1) {
                        throw $e;
                    }
                    sleep($retryDelay);
                }
            }

            $createTableSQL = "CREATE TABLE IF NOT EXISTS sensor_readings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sensor_id VARCHAR(50) NOT NULL,
                temperature DECIMAL(5,2) NOT NULL,
                humidity DECIMAL(5,2) NOT NULL,
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sensor_id (sensor_id),
                INDEX idx_recorded_at (recorded_at)
            )";
            $conn->exec($createTableSQL);

            $now = (new DateTime('now', new DateTimeZone('Europe/Paris')))->format('Y-m-d H:i:s');

            $stmt = $conn->prepare("INSERT INTO sensor_readings (sensor_id, temperature, humidity, recorded_at) VALUES (:sensor_id, :temperature, :humidity, :recorded_at)");
            $stmt->bindParam(':sensor_id', $sensor_id);
            $stmt->bindParam(':temperature', $temperature);
            $stmt->bindParam(':humidity', $humidity);
            $stmt->bindParam(':recorded_at', $now);
            $stmt->execute();

            echo json_encode([
                'status' => 'success',
                'message' => 'Données enregistrées avec succès',
                'data' => [
                    'sensor_id' => $sensor_id,
                    'temperature' => $temperature,
                    'humidity' => $humidity,
                    'timestamp' => $now
                ]
            ]);
        } catch(PDOException $e) {
            error_log("Erreur DB: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Erreur de base de données: ' . $e->getMessage()
            ]);
        }
        $conn = null;
    } else {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Données manquantes: température ou humidité'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Méthode non autorisée. Utilisez POST pour insérer des données, GET?test=1 pour tester la connexion.'
    ]);
}
?>
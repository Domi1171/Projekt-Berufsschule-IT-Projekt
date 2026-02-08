<?php
/**
 * save_sensor_data.php
 * =====================
 * Empfängt Sensordaten vom Arduino MKR WiFi 1010 per HTTP POST
 * und speichert sie in der MySQL-Datenbank auf IONOS.
 * 
 * INSTALLATION:
 * 1. Trage dein DB-Passwort unten ein
 * 2. Lade diese Datei per FTP ins Root-Verzeichnis deines IONOS-Webhostings
 * 3. Teste mit: http://www.elbawohnmobile.de/test_db.php (siehe test_db.php)
 * 4. Fertig! Der Arduino sendet alle 10 Minuten Daten hierher.
 */

// ============================================================
// DATENBANK-KONFIGURATION
// ============================================================
$db_host = "db5019198513.hosting-data.io";
$db_port = 3306;
$db_name = "dbs15071844";
$db_user = "dbu3392536";
$db_pass = "UTNJBuA5auGS";  // <-- HIER dein DB-Passwort eintragen!

// ============================================================
// STANDARD-IDs
// Nach dem Ausführen von setup.sql haben Sensor und Pflanze jeweils ID 1
// ============================================================
$default_sensor_id   = 1;
$default_pflanzen_id = 1;

// ============================================================
// AB HIER NICHTS ÄNDERN
// ============================================================

header('Content-Type: application/json; charset=utf-8');

// Nur POST erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Nur POST erlaubt"]);
    exit;
}

// POST-Daten lesen
$temperature = isset($_POST['temperature']) ? floatval($_POST['temperature']) : null;
$humidity    = isset($_POST['humidity'])    ? floatval($_POST['humidity'])    : null;
$moisture    = isset($_POST['moisture'])    ? floatval($_POST['moisture'])    : null;
$light       = isset($_POST['light'])       ? intval($_POST['light'])        : null;
$sensor_id   = isset($_POST['sensor_id'])   ? intval($_POST['sensor_id'])    : $default_sensor_id;
$pflanzen_id = isset($_POST['pflanzen_id']) ? intval($_POST['pflanzen_id'])  : $default_pflanzen_id;

// Pflichtfelder prüfen
if ($temperature === null || $humidity === null || $moisture === null || $light === null) {
    http_response_code(400);
    echo json_encode([
        "status"   => "error",
        "message"  => "Fehlende Daten. Benötigt: temperature, humidity, moisture, light",
        "received" => $_POST
    ]);
    exit;
}

// DB-Verbindung
try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => "DB-Verbindung fehlgeschlagen: " . $e->getMessage()
    ]);
    exit;
}

// Daten speichern
try {
    $sql = "INSERT INTO messwerte 
            (sensor_id, pflanzen_id, temperatur, feuchtigkeit, bodenfeuchtigkeit, licht, gemessen_am) 
            VALUES 
            (:sensor_id, :pflanzen_id, :temperatur, :feuchtigkeit, :bodenfeuchtigkeit, :licht, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':sensor_id'         => $sensor_id,
        ':pflanzen_id'       => $pflanzen_id,
        ':temperatur'        => $temperature,
        ':feuchtigkeit'      => $humidity,
        ':bodenfeuchtigkeit' => $moisture,
        ':licht'             => $light,
    ]);
    
    $id = $pdo->lastInsertId();
    
    echo json_encode([
        "status"      => "success",
        "message"     => "Messwert #$id gespeichert!",
        "messwert_id" => $id,
        "data"        => [
            "temperatur"        => $temperature,
            "feuchtigkeit"      => $humidity,
            "bodenfeuchtigkeit" => $moisture,
            "licht"             => $light
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => "Speichern fehlgeschlagen: " . $e->getMessage()
    ]);
}
?>

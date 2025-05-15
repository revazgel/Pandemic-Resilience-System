<?php
header("Content-Type: application/json");

// DB config
$host = 'localhost';
$db = 'CovidSystem';
$user = 'root';
$pass = '';
$port = 3307;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Validate required parameters
    if (!isset($_GET['table']) || empty($_GET['table'])) {
        echo json_encode(['error' => 'Table name is required']);
        exit;
    }
    
    // Sanitize input
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['table']);
    
    // Validate table exists
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() === 0) {
        echo json_encode(["error" => "Table not found"]);
        exit;
    }
    
    // Get fields to retrieve
    $fields = "*"; // Default to all fields
    if (isset($_GET['fields']) && !empty($_GET['fields'])) {
        $requestedFields = explode(',', $_GET['fields']);
        $safeFields = [];
        
        // Sanitize each field
        foreach ($requestedFields as $field) {
            $safeFields[] = preg_replace('/[^a-zA-Z0-9_]/', '', trim($field));
        }
        
        $fields = implode(', ', $safeFields);
    }
    
    // Create and execute query
    $query = "SELECT $fields FROM $table";
    
    // Add a limit if specified
    if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
        $limit = (int)$_GET['limit'];
        $query .= " LIMIT $limit";
    }
    
    $stmt = $pdo->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($data);
    
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
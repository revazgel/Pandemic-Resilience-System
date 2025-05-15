<?php
header("Content-Type: application/json");

try{
    $pdo = new PDO("mysql:host=localhost;dbname=CovidSystem","root","",[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    //This checks if you actually pass a table
    if(!isset($_GET["table"]) || empty($_GET['table'])){
        echo json_encode(['error'=> 'Table name is required']);
        exit;
    }
    //sanitize input
    $table = preg_replace('/[^a-zA-Z0-9_]/','',$_GET['table']);

    //Validate if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if($stmt->rowCount() === 0){
        echo json_encode(["error" => "Table name is required!"]);
    }

    $query = "SELECT * FROM " . $table;
    //$query = "SELECT * FROM users";

    $stmt = $pdo->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($data);
} catch (PDOException $e){
    echo json_encode(["error"=> "Database connection failed:". $e->getMessage()]);
}

?>
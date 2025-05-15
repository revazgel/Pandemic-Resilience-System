<?php
// register.php (modified to handle official approvals)

// DB config
$host = 'localhost';
$db = 'CovidSystem';
$user = 'root';
$pass = '';
$port = 3307;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $full_name = $firstname . ' ' . $lastname;
    $national_id = trim($_POST['national_id']);
    $dob = $_POST['dob'];
    $role = $_POST['role'];
    
    if (!empty($_POST['prs_id'])) {
        $prs_id = trim($_POST['prs_id']);
    } else {
        $prs_id = 'PRS' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
    $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
    $address = !empty($_POST['address']) ? trim($_POST['address']) : null;
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $is_visitor = isset($_POST['is_visitor']) ? 1 : 0;
    
    // Additional fields for Officials
    $department = !empty($_POST['department']) ? trim($_POST['department']) : null;
    $official_role = !empty($_POST['official_role']) ? trim($_POST['official_role']) : null;
    $badge_number = !empty($_POST['badge_number']) ? trim($_POST['badge_number']) : null;
    
    // Check if username already exists
    $checkUsername = $pdo->prepare("SELECT username FROM Users WHERE username = ?");
    $checkUsername->execute([$username]);
    if ($checkUsername->rowCount() > 0) {
        // Username already exists
        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Error</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <meta http-equiv="refresh" content="3;url=register.html">
        </head>
        <body class="d-flex justify-content-center align-items-center vh-100 bg-light">
            <div class="alert alert-danger text-center" role="alert">
                ⚠️ Username already exists. Please choose a different username. Redirecting back...
            </div>
        </body>
        </html>
        HTML;
        exit;
    }
    
    // Check if national ID already exists
    $checkNationalId = $pdo->prepare("SELECT national_id FROM Users WHERE national_id = ?");
    $checkNationalId->execute([$national_id]);
    if ($checkNationalId->rowCount() > 0) {
        // National ID already exists
        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Error</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <meta http-equiv="refresh" content="3;url=register.html">
        </head>
        <body class="d-flex justify-content-center align-items-center vh-100 bg-light">
            <div class="alert alert-danger text-center" role="alert">
                ⚠️ National ID already exists. Please check your information. Redirecting back...
            </div>
        </body>
        </html>
        HTML;
        exit;
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // If registering as an Official, initial role is 'Pending'
        $initial_role = ($role === 'Official') ? 'Pending' : $role;
        
        // Insert into Users table with all the collected fields
        $stmt = $pdo->prepare("INSERT INTO Users (prs_id, full_name, national_id, dob, role, username, password, email, phone, address, is_visitor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$prs_id, $full_name, $national_id, $dob, $initial_role, $username, $password, $email, $phone, $address, $is_visitor]);
        
        $user_id = $pdo->lastInsertId();
        
        // If registering as an Official, create an approval request
        if ($role === 'Official') {
            // Validate additional fields
            if (empty($department) || empty($official_role) || empty($badge_number)) {
                throw new Exception("Department, role, and badge number are required for officials");
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO official_approvals
                (user_id, department, role, badge_number, status, created_at)
                VALUES (?, ?, ?, ?, 'Pending', NOW())
            ");
            $stmt->execute([$user_id, $department, $official_role, $badge_number]);
            
            // Show HTML alert for pending approval
            echo <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Registration Pending</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <meta http-equiv="refresh" content="5;url=login.html">
            </head>
            <body class="d-flex justify-content-center align-items-center vh-100 bg-light">
                <div class="alert alert-warning text-center" role="alert">
                    ⚠️ Your official registration is pending approval by an administrator.
                    <p>You will receive an email notification once your request is processed.</p>
                    <p>Your PRS ID is: {$prs_id}</p>
                    <p>Redirecting to login...</p>
                </div>
            </body>
            </html>
            HTML;
        } else {
            // Show HTML alert on success for non-official users
            echo <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Registered</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <meta http-equiv="refresh" content="2;url=login.html">
            </head>
            <body class="d-flex justify-content-center align-items-center vh-100 bg-light">
                <div class="alert alert-success text-center" role="alert">
                    ✅ Registration successful! Your PRS ID is: {$prs_id}
                    <p>Please remember this ID for future reference.</p>
                    <p>Redirecting to login...</p>
                </div>
            </body>
            </html>
            HTML;
        }
        
        // Commit transaction
        $pdo->commit();
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        if ($e->getCode() == 23000) {
            echo <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Error</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <meta http-equiv="refresh" content="3;url=register.html">
            </head>
            <body class="d-flex justify-content-center align-items-center vh-100 bg-light">
                <div class="alert alert-danger text-center" role="alert">
                    ⚠️ Registration failed due to duplicate information. Please check your details. Redirecting back...
                </div>
            </body>
            </html>
            HTML;
        } else {
            echo <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Error</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <meta http-equiv="refresh" content="3;url=register.html">
            </head>
            <body class="d-flex justify-content-center align-items-center vh-100 bg-light">
                <div class="alert alert-danger text-center" role="alert">
                    ⚠️ Database error: {$e->getMessage()}
                    <p>Please try again later. Redirecting back...</p>
                </div>
            </body>
            </html>
            HTML;
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Error</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <meta http-equiv="refresh" content="3;url=register.html">
        </head>
        <body class="d-flex justify-content-center align-items-center vh-100 bg-light">
            <div class="alert alert-danger text-center" role="alert">
                ⚠️ Error: {$e->getMessage()}
                <p>Please try again. Redirecting back...</p>
            </div>
        </body>
        </html>
        HTML;
    }
}
?>
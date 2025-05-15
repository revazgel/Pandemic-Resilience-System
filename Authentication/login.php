<?php
session_start();

// Debug mode - set to true to see detailed errors
$debug_mode = true;

// DB config
$host = 'localhost';
$db = 'CovidSystem';
$user = 'root';
$pass = '';
$port = 3307;

function debug_log($message) {
    global $debug_mode;
    if ($debug_mode) {
        error_log($message);
        echo "<p style='color: red; font-family: monospace;'>Debug: $message</p>";
    }
}

try {
    debug_log("Attempting database connection");
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    debug_log("Database connection successful");
} catch (PDOException $e) {
    debug_log("DB connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Handle login
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    
    debug_log("Login attempt for username: $username");

    // Find user by username
    try {
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        debug_log("Query executed. User found: " . ($user ? "Yes" : "No"));
        
        // Check if user exists and password matches (plain text comparison)
        if ($user && $user['password'] === $password) {
            debug_log("Password match. Setting session data.");
            
            // Store user data in session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['logged_in'] = true;
            
            debug_log("Session data set. Role: " . $user['role']);
            
            // Update last login time
            $update_stmt = $pdo->prepare("UPDATE Users SET last_login = NOW() WHERE user_id = ?");
            $update_stmt->execute([$user['user_id']]);
            
            // Add login to access logs
            $log_stmt = $pdo->prepare("INSERT INTO access_logs (user_id, ip_address, action_type, action_details, entity_type, entity_id) 
                                      VALUES (?, ?, 'login', 'User logged in', 'user', ?)");
            $log_stmt->execute([$user['user_id'], $_SERVER['REMOTE_ADDR'], $user['user_id']]);
            
            // Check if user is in "Pending" status
            if ($user['role'] === 'Pending') {
                debug_log("User is in Pending status");
                // Show pending message and exit
                echo '<div style="text-align: center; margin: 50px auto; max-width: 500px; padding: 20px; background-color: #fff3cd; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h3 style="color: #856404;"><i class="fas fa-exclamation-triangle"></i> Account Pending Approval</h3>
                        <p>Your official registration is still pending approval by an administrator.</p>
                        <p>You will receive an email notification once your request has been processed.</p>
                        <p><a href="logout.php" style="color: #0d6efd; text-decoration: none;">Return to Login</a></p>
                      </div>';
                exit();
            }
            
            // Redirect based on role
            debug_log("Redirecting based on role: " . $user['role']);
            
            switch ($user['role']) {
                case 'Admin':
                    debug_log("Redirecting to Admin dashboard");
                    header("Location: ../Admin/admin_dashboard.php");
                    break;
                case 'Official':
                    debug_log("Redirecting to Official dashboard");
                    header("Location: ../Official/dashboard_official.php");
                    break;
                case 'Merchant':
                    debug_log("Redirecting to Merchant dashboard");
                    header("Location: ../Merchant/dashboard_merchant.php");
                    break;
                case 'Citizen':
                default:
                    debug_log("Redirecting to Citizen dashboard");
                    header("Location: ../Citizen/dashboard_citizen.php");
                    break;
            }
            debug_log("Redirection headers sent, exiting script");
            exit();
        } else {
            // Login failed
            debug_log("Login failed: incorrect username or password");
            header("Location: login.html?error=invalid");
            exit();
        }
    } catch (PDOException $e) {
        debug_log("Database error: " . $e->getMessage());
        header("Location: login.html?error=database");
        exit();
    }
}
?>
<?php
require_once '../Authentication/session_check.php';

// Check if user has the correct role
if ($_SESSION['role'] !== 'Citizen') {
    header("Location: ../Authentication/login.html");
    exit();
}

// DB config
$host = 'localhost';
$db = 'CovidSystem';
$user = 'root';
$pass = '';
$port = 3307;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get vaccines list
    $stmt = $pdo->query("SELECT * FROM vaccines ORDER BY vaccine_name");
    $vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate required fields
        $vaccine_id = $_POST['vaccine_id'] ?? null;
        $dose_number = $_POST['dose_number'] ?? null;
        $vaccination_date = $_POST['vaccination_date'] ?? null;
        $healthcare_provider = $_POST['healthcare_provider'] ?? null;
        $location = $_POST['location'] ?? null;
        $batch_number = $_POST['batch_number'] ?? '';
        
        // Validation
        if (empty($vaccine_id) || !is_numeric($vaccine_id)) {
            $_SESSION['error_message'] = "Please select a valid vaccine.";
        } elseif (empty($dose_number) || !is_numeric($dose_number)) {
            $_SESSION['error_message'] = "Please select a valid dose number.";
        } elseif (empty($vaccination_date)) {
            $_SESSION['error_message'] = "Please enter the vaccination date.";
        } elseif (empty($healthcare_provider)) {
            $_SESSION['error_message'] = "Please enter the healthcare provider.";
        } elseif (empty($location)) {
            $_SESSION['error_message'] = "Please enter the vaccination location.";
        } else {
            // Validate date format
            $date = DateTime::createFromFormat('Y-m-d', $vaccination_date);
            if (!$date || $date->format('Y-m-d') !== $vaccination_date) {
                $_SESSION['error_message'] = "Please enter a valid date format.";
            } elseif ($date > new DateTime()) {
                $_SESSION['error_message'] = "Vaccination date cannot be in the future.";
            } else {
                // Insert vaccination record
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO vaccination_records 
                        (user_id, vaccine_id, dose_number, vaccination_date, healthcare_provider, location, batch_number, verified)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 0)
                    ");
                    
                    $stmt->execute([
                        $_SESSION['user_id'],
                        intval($vaccine_id),
                        intval($dose_number),
                        $vaccination_date,
                        $healthcare_provider,
                        $location,
                        $batch_number
                    ]);
                    
                    $_SESSION['success_message'] = "Vaccination record uploaded successfully! It will be verified by an official.";
                    header("Location: citizen_vaccinations.php");
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Error saving vaccination record: " . $e->getMessage();
                }
            }
        }
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Vaccination Certificate - COVID Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/citizen_styles.css">
</head>
<body>
    <?php include 'citizen_navbar.php'; ?>
    
    <div class="container">
        <div class="header d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-upload"></i> Upload Vaccination Certificate</h2>
            <a href="citizen_vaccinations.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Vaccinations
            </a>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle"></i> 
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate id="vaccinationForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="vaccine_id" class="form-label">Vaccine Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="vaccine_id" id="vaccine_id" required>
                                <option value="">Select vaccine</option>
                                <?php foreach ($vaccines as $vaccine): ?>
                                    <option value="<?php echo $vaccine['vaccine_id']; ?>">
                                        <?php echo htmlspecialchars($vaccine['vaccine_name']); ?> - 
                                        <?php echo htmlspecialchars($vaccine['manufacturer']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a vaccine type.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="dose_number" class="form-label">Dose Number <span class="text-danger">*</span></label>
                            <select class="form-select" name="dose_number" id="dose_number" required>
                                <option value="">Select dose</option>
                                <option value="1">First Dose</option>
                                <option value="2">Second Dose</option>
                                <option value="3">Third Dose (Booster)</option>
                                <option value="4">Fourth Dose</option>
                            </select>
                            <div class="invalid-feedback">
                                Please select a dose number.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="vaccination_date" class="form-label">Vaccination Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="vaccination_date" id="vaccination_date" required max="<?php echo date('Y-m-d'); ?>">
                            <div class="invalid-feedback">
                                Please enter a valid vaccination date.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="healthcare_provider" class="form-label">Healthcare Provider <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="healthcare_provider" id="healthcare_provider" required>
                            <div class="invalid-feedback">
                                Please enter the healthcare provider name.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="location" class="form-label">Vaccination Location <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="location" id="location" required>
                            <div class="invalid-feedback">
                                Please enter the vaccination location.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="batch_number" class="form-label">Batch Number (Optional)</label>
                            <input type="text" class="form-control" name="batch_number" id="batch_number">
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Note:</strong> Your vaccination record will be marked as unverified until reviewed by a government official.
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Vaccination Record
                    </button>
                    <a href="citizen_vaccinations.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap components
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dropdowns if any exist
            var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
            var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl);
            });
        });
        
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>
</html>
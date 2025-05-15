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
    
    // Get user's vaccination records
    $query = "
        SELECT vr.record_id, vr.dose_number, vr.vaccination_date, vr.healthcare_provider,
               vr.batch_number, vr.location, vr.verified, vr.verification_date,
               v.vaccine_id, v.vaccine_name, v.manufacturer, v.disease, v.doses_required
        FROM vaccination_records vr
        JOIN vaccines v ON vr.vaccine_id = v.vaccine_id
        WHERE vr.user_id = ?
        ORDER BY v.disease, v.vaccine_name, vr.dose_number
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group vaccinations by disease and vaccine
    $groupedVaccinations = [];
    foreach ($vaccinations as $vaccination) {
        $disease = $vaccination['disease'];
        $vaccineId = $vaccination['vaccine_id'];
        
        if (!isset($groupedVaccinations[$disease])) {
            $groupedVaccinations[$disease] = [];
        }
        
        if (!isset($groupedVaccinations[$disease][$vaccineId])) {
            $groupedVaccinations[$disease][$vaccineId] = [
                'vaccine_name' => $vaccination['vaccine_name'],
                'manufacturer' => $vaccination['manufacturer'],
                'doses_required' => $vaccination['doses_required'],
                'doses' => []
            ];
        }
        
        $groupedVaccinations[$disease][$vaccineId]['doses'][] = $vaccination;
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
    <title>My Vaccination Records - COVID Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/citizen_styles.css">
</head>
<body>
    <?php include 'citizen_navbar.php'; ?>
    
    <div class="container">
        <div class="header d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-syringe"></i> My Vaccination Records</h2>
            <a href="dashboard_citizen.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" role="alert">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" role="alert">
                <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-8 mb-4">
                <div class="card fade-in">
                    <div class="card-body">
                        <h5 class="card-title">Vaccination Summary</h5>
                        
                        <?php if (empty($groupedVaccinations)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-check fa-3x mb-3 text-muted"></i>
                                <p class="text-muted">You don't have any vaccination records yet.</p>
                                <a href="citizen_upload_vaccination.php" class="btn btn-primary mt-2">Upload Vaccination Certificate</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($groupedVaccinations as $disease => $vaccines): ?>
                                <div class="disease-header">
                                    <i class="fas fa-virus"></i> <?php echo htmlspecialchars($disease); ?>
                                </div>
                                
                                <?php foreach ($vaccines as $vaccineId => $vaccine): ?>
                                    <div class="card vaccine-card mb-4">
                                        <div class="card-header">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></h6>
                                                <span class="text-muted">Manufacturer: <?php echo htmlspecialchars($vaccine['manufacturer']); ?></span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <?php 
                                                $totalDoses = $vaccine['doses_required'];
                                                $receivedDoses = count($vaccine['doses']);
                                                $progressPercentage = ($receivedDoses / $totalDoses) * 100;
                                            ?>
                                            
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span>Vaccination Progress</span>
                                                    <span><?php echo $receivedDoses; ?> of <?php echo $totalDoses; ?> doses</span>
                                                </div>
                                                <div class="progress">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progressPercentage; ?>%" 
                                                         aria-valuenow="<?php echo $progressPercentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                
                                                <?php if ($receivedDoses >= $totalDoses): ?>
                                                    <div class="text-success small">
                                                        <i class="fas fa-check-circle"></i> Fully vaccinated
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-warning small">
                                                        <i class="fas fa-exclamation-circle"></i> Vaccination incomplete
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <h6>Dose History</h6>
                                            <?php foreach ($vaccine['doses'] as $dose): ?>
                                                <div class="dose-card">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <h6 class="mb-0">Dose <?php echo $dose['dose_number']; ?></h6>
                                                        <?php if ($dose['verified']): ?>
                                                            <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                                                        <?php else: ?>
                                                            <span class="unverified-badge"><i class="fas fa-exclamation-circle"></i> Unverified</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p class="mb-1 small"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($dose['vaccination_date'])); ?></p>
                                                            <p class="mb-1 small"><strong>Healthcare Provider:</strong> <?php echo htmlspecialchars($dose['healthcare_provider']); ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p class="mb-1 small"><strong>Location:</strong> <?php echo htmlspecialchars($dose['location']); ?></p>
                                                            <?php if ($dose['batch_number']): ?>
                                                                <p class="mb-1 small"><strong>Batch #:</strong> <?php echo htmlspecialchars($dose['batch_number']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($dose['verified']): ?>
                                                        <div class="small text-muted mt-1">
                                                            <i class="fas fa-info-circle"></i> Verified on <?php echo date('F j, Y', strtotime($dose['verification_date'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-file-upload"></i> Upload Certificate</h5>
                    </div>
                    <div class="card-body">
                        <p>Need to add a new vaccination record to your profile?</p>
                        <a href="citizen_upload_vaccination.php" class="btn btn-success w-100">
                            <i class="fas fa-plus-circle"></i> Upload New Certificate
                        </a>
                        
                        <hr>
                        
                        <h6 class="mb-3">Accepted Formats</h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><i class="fas fa-file-pdf"></i> PDF Certificate</li>
                            <li class="list-group-item"><i class="fas fa-file-code"></i> FHIR JSON Format</li>
                            <li class="list-group-item"><i class="fas fa-keyboard"></i> Manual Form Entry</li>
                        </ul>
                        
                        <div class="alert alert-info mt-3">
                            <small><i class="fas fa-info-circle"></i> 
                                All uploaded vaccination records will need to be verified by a government official before being marked as verified.
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-question-circle"></i> Need Help?</h5>
                    </div>
                    <div class="card-body">
                        <p class="small">If you're having trouble uploading your vaccination certificates or have questions about your vaccination status, please contact support.</p>
                        <a href="#" class="btn btn-outline-primary w-100">
                            <i class="fas fa-headset"></i> Contact Support
                        </a>
                    </div>
                </div>
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
    </script>
</body>
</html>
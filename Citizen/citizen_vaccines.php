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
    
    // Get all vaccines
    $stmt = $pdo->query("SELECT * FROM vaccines ORDER BY disease, vaccine_name");
    $vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group vaccines by disease
    $vaccines_by_disease = [];
    foreach ($vaccines as $vaccine) {
        $disease = $vaccine['disease'];
        if (!isset($vaccines_by_disease[$disease])) {
            $vaccines_by_disease[$disease] = [];
        }
        $vaccines_by_disease[$disease][] = $vaccine;
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
    <title>Available Vaccines - COVID Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/citizen_styles.css">
</head>
<body>
    <?php include 'citizen_navbar.php'; ?>
    
    <div class="container">
        <div class="header d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-syringe"></i> Available Vaccines</h2>
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
        
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="search-container">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search vaccines by name, manufacturer, or disease...">
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="filter-container">
                    <select id="diseaseFilter" class="form-select">
                        <option value="">All Diseases</option>
                        <?php foreach (array_keys($vaccines_by_disease) as $disease): ?>
                            <option value="<?php echo htmlspecialchars($disease); ?>"><?php echo htmlspecialchars($disease); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <?php if (empty($vaccines)): ?>
            <div class="text-center py-5">
                <i class="fas fa-syringe fa-3x mb-3 text-muted"></i>
                <p class="text-muted">No vaccines available in the system.</p>
            </div>
        <?php else: ?>
            <ul class="nav nav-pills mb-4" id="diseaseTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="all-tab" data-bs-toggle="pill" data-bs-target="#all" type="button" role="tab">
                        All Vaccines
                    </button>
                </li>
                <?php foreach (array_keys($vaccines_by_disease) as $disease): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="<?php echo strtolower(str_replace(' ', '-', $disease)); ?>-tab" 
                                data-bs-toggle="pill" 
                                data-bs-target="#<?php echo strtolower(str_replace(' ', '-', $disease)); ?>" 
                                type="button" role="tab">
                            <?php echo htmlspecialchars($disease); ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <div class="tab-content" id="diseaseTabContent">
                <!-- All Vaccines Tab -->
                <div class="tab-pane fade show active" id="all" role="tabpanel">
                    <div id="vaccineList">
                        <?php foreach ($vaccines_by_disease as $disease => $disease_vaccines): ?>
                            <div class="disease-section" data-disease="<?php echo htmlspecialchars($disease); ?>">
                                <div class="disease-header">
                                    <i class="fas fa-virus"></i> <?php echo htmlspecialchars($disease); ?>
                                </div>
                                
                                <div class="row">
                                    <?php foreach ($disease_vaccines as $vaccine): ?>
                                        <div class="col-md-6 mb-4 vaccine-item" 
                                             data-name="<?php echo strtolower(htmlspecialchars($vaccine['vaccine_name'])); ?>" 
                                             data-manufacturer="<?php echo strtolower(htmlspecialchars($vaccine['manufacturer'])); ?>"
                                             data-disease="<?php echo strtolower(htmlspecialchars($vaccine['disease'])); ?>">
                                            <div class="card vaccine-card">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <span><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></span>
                                                    <span class="badge badge-custom badge-approval">
                                                        Approved: <?php echo date('M j, Y', strtotime($vaccine['approval_date'])); ?>
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <p class="text-muted mb-3">
                                                        <strong>Manufacturer:</strong> <?php echo htmlspecialchars($vaccine['manufacturer']); ?>
                                                    </p>
                                                    
                                                    <h6>Dosage Information:</h6>
                                                    <div class="dose-info">
                                                        <div class="dose-icon"><?php echo $vaccine['doses_required']; ?></div>
                                                        <span>
                                                            <?php echo $vaccine['doses_required']; ?> dose<?php echo $vaccine['doses_required'] > 1 ? 's' : ''; ?> required
                                                        </span>
                                                    </div>
                                                    
                                                    <?php if ($vaccine['doses_required'] > 1 && !empty($vaccine['min_days_between_doses'])): ?>
                                                        <div class="alert alert-info p-2 small">
                                                            <i class="fas fa-info-circle"></i> 
                                                            Minimum <?php echo $vaccine['min_days_between_doses']; ?> days between doses
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Individual Disease Tabs -->
                <?php foreach ($vaccines_by_disease as $disease => $disease_vaccines): ?>
                    <div class="tab-pane fade" id="<?php echo strtolower(str_replace(' ', '-', $disease)); ?>" role="tabpanel">
                        <div class="row">
                            <?php foreach ($disease_vaccines as $vaccine): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card vaccine-card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <span><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></span>
                                            <span class="badge badge-custom badge-approval">
                                                Approved: <?php echo date('M j, Y', strtotime($vaccine['approval_date'])); ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted mb-3">
                                                <strong>Manufacturer:</strong> <?php echo htmlspecialchars($vaccine['manufacturer']); ?>
                                            </p>
                                            
                                            <h6>Dosage Information:</h6>
                                            <div class="dose-info">
                                                <div class="dose-icon"><?php echo $vaccine['doses_required']; ?></div>
                                                <span>
                                                    <?php echo $vaccine['doses_required']; ?> dose<?php echo $vaccine['doses_required'] > 1 ? 's' : ''; ?> required
                                                </span>
                                            </div>
                                            
                                            <?php if ($vaccine['doses_required'] > 1 && !empty($vaccine['min_days_between_doses'])): ?>
                                                <div class="alert alert-info p-2 small">
                                                    <i class="fas fa-info-circle"></i> 
                                                    Minimum <?php echo $vaccine['min_days_between_doses']; ?> days between doses
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-file-upload"></i> Your Vaccination Records</h5>
                    </div>
                    <div class="card-body">
                        <p>Need to add your vaccination records to the system?</p>
                        <div class="d-flex gap-2">
                            <a href="citizen_vaccinations.php" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-clipboard-list"></i> View My Vaccination Records
                            </a>
                            <a href="citizen_upload_vaccination.php" class="btn btn-success flex-grow-1">
                                <i class="fas fa-plus-circle"></i> Upload New Vaccination
                            </a>
                        </div>
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
            
            const searchInput = document.getElementById('searchInput');
            const diseaseFilter = document.getElementById('diseaseFilter');
            const vaccineItems = document.querySelectorAll('.vaccine-item');
            const diseaseSections = document.querySelectorAll('.disease-section');
            
            // Search functionality
            searchInput.addEventListener('input', filterVaccines);
            diseaseFilter.addEventListener('change', filterVaccines);
            
            function filterVaccines() {
                const searchTerm = searchInput.value.toLowerCase();
                const diseaseValue = diseaseFilter.value.toLowerCase();
                
                // First determine if any vaccines in each disease section match
                const visibleSections = new Set();
                
                vaccineItems.forEach(item => {
                    const name = item.dataset.name;
                    const manufacturer = item.dataset.manufacturer;
                    const disease = item.dataset.disease;
                    
                    const matchesSearch = name.includes(searchTerm) || 
                                         manufacturer.includes(searchTerm) || 
                                         disease.includes(searchTerm);
                    
                    const matchesDisease = diseaseValue === '' || disease === diseaseValue;
                    
                    if (matchesSearch && matchesDisease) {
                        item.style.display = '';
                        visibleSections.add(disease);
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                // Then show/hide entire disease sections
                diseaseSections.forEach(section => {
                    const sectionDisease = section.dataset.disease.toLowerCase();
                    if (visibleSections.has(sectionDisease)) {
                        section.style.display = '';
                    } else {
                        section.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>
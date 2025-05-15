<?php
// Get current page to highlight active nav item
$current_page = basename($_SERVER['PHP_SELF']);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only try to fetch user info if session is already started
$user_name = '';
$prs_id = '';

if (isset($_SESSION['user_id'])) {
    try {
        global $pdo;
        // Check if $pdo exists, if not, create connection
        if (!isset($pdo)) {
            $host = 'localhost';
            $db = 'CovidSystem';
            $user = 'root';
            $pass = '';
            $port = 3307;
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        
        $stmt = $pdo->prepare("SELECT full_name, prs_id FROM Users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_info) {
            $user_name = $user_info['full_name'];
            $prs_id = $user_info['prs_id'];
        }
        
        // Fallback to session data if database query fails
        if (empty($user_name) && isset($_SESSION['full_name'])) {
            $user_name = $_SESSION['full_name'];
        }
    } catch (Exception $e) {
        // Silently fail - navbar will still work without the user info
        // Use session data as fallback
        if (isset($_SESSION['full_name'])) {
            $user_name = $_SESSION['full_name'];
        }
    }
}
?>

<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #28a745 0%, #155724 100%);">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard_citizen.php">
            <i class="fas fa-shield-virus"></i> PRS Citizen
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" 
                aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'dashboard_citizen.php' ? 'active' : ''; ?>" 
                       href="dashboard_citizen.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                
                <!-- Supplies Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($current_page, ['citizen_find_supplies.php', 'citizen_purchase_schedule.php', 'citizen_purchase_history.php']) ? 'active' : ''; ?>" 
                       href="#" id="suppliesDropdown" role="button" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                        <i class="fas fa-shopping-basket"></i> Supplies
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="suppliesDropdown">
                        <li>
                            <a class="dropdown-item" href="citizen_find_supplies.php">
                                <i class="fas fa-search"></i> Find Supplies
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="citizen_purchase_schedule.php">
                                <i class="fas fa-calendar-alt"></i> Purchase Schedule
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="citizen_purchase_history.php">
                                <i class="fas fa-history"></i> Purchase History
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Vaccination Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($current_page, ['citizen_vaccinations.php', 'citizen_vaccines.php', 'citizen_upload_vaccination.php']) ? 'active' : ''; ?>" 
                       href="#" id="vaccinationDropdown" role="button" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                        <i class="fas fa-syringe"></i> Vaccinations
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="vaccinationDropdown">
                        <li>
                            <a class="dropdown-item" href="citizen_vaccinations.php">
                                <i class="fas fa-clipboard-list"></i> My Records
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="citizen_upload_vaccination.php">
                                <i class="fas fa-upload"></i> Upload Certificate
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="citizen_vaccines.php">
                                <i class="fas fa-info-circle"></i> Vaccine Information
                            </a>
                        </li>
                    </ul>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="citizen_find_supplies.php">
                        <i class="fas fa-store"></i> Local Merchants
                    </a>
                </li>
            </ul>
            
            <!-- Right side navbar items -->
            <div class="d-flex align-items-center gap-2">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- PRS-ID display -->
                    <?php if (!empty($prs_id)): ?>
                        <div class="navbar-text text-light d-none d-lg-block me-2">
                            <i class="fas fa-id-badge"></i> PRS-ID: <?php echo htmlspecialchars($prs_id); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- User dropdown -->
                    <div class="dropdown">
                        <a class="btn btn-outline-light dropdown-toggle" 
                           href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> 
                            <?php echo !empty($user_name) ? htmlspecialchars($user_name) : 'User'; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item" href="citizen_profile.php">
                                    <i class="fas fa-id-card"></i> My Profile
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="../Authentication/logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Return to Admin button (only if user is admin viewing as citizen) -->
                    <?php if (isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Admin'): ?>
                        <form method="POST" action="../Admin/switch_role.php" style="display: inline;">
                            <input type="hidden" name="target_role" value="Admin">
                            <button type="submit" class="btn btn-warning btn-sm ms-2">
                                <i class="fas fa-arrow-left"></i> Return to Admin
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<script>
    // Initialize Bootstrap dropdowns properly
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Initializing navbar dropdowns...');
        
        // Initialize all dropdowns using Bootstrap 5 API
        const dropdownElementList = document.querySelectorAll('.dropdown-toggle');
        const dropdownList = [...dropdownElementList].map(dropdownToggleEl => new bootstrap.Dropdown(dropdownToggleEl));
        
        console.log('Initialized', dropdownList.length, 'dropdowns');
        
        // Desktop hover functionality
        if (window.innerWidth > 992) {
            document.querySelectorAll('.navbar-nav .dropdown').forEach(function(dropdown) {
                dropdown.addEventListener('mouseenter', function() {
                    const dropdownMenu = this.querySelector('.dropdown-menu');
                    const dropdownToggle = this.querySelector('.dropdown-toggle');
                    if (dropdownMenu && dropdownToggle) {
                        dropdownMenu.classList.add('show');
                        dropdownToggle.setAttribute('aria-expanded', 'true');
                    }
                });
                
                dropdown.addEventListener('mouseleave', function() {
                    const dropdownMenu = this.querySelector('.dropdown-menu');
                    const dropdownToggle = this.querySelector('.dropdown-toggle');
                    if (dropdownMenu && dropdownToggle) {
                        dropdownMenu.classList.remove('show');
                        dropdownToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            });
        }
        
        // Ensure dropdown items are clickable
        document.querySelectorAll('.dropdown-item').forEach(function(item) {
            item.addEventListener('click', function(e) {
                // Only prevent default if it's not a real link
                if (this.getAttribute('href') === '#') {
                    e.preventDefault();
                }
            });
        });
    });
</script>

<style>
    /* Ensure proper dropdown styling */
    .dropdown-menu {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        border-radius: 0.375rem;
    }

    .dropdown-item {
        padding: 0.5rem 1rem;
        transition: background-color 0.15s ease-in-out;
    }

    .dropdown-item:hover,
    .dropdown-item:focus {
        background-color: #f8f9fa;
        color: #212529;
    }

    .dropdown-item i {
        width: 1.25rem;
        text-align: center;
        margin-right: 0.5rem;
    }

    /* Fix navbar dropdown spacing */
    .nav-item.dropdown .dropdown-menu {
        margin-top: 0;
    }

    /* Fix button alignment in navbar */
    .navbar .btn {
        display: inline-flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
        padding: 0.5rem 1rem !important;
        border-radius: 7px !important;
        font-weight: 500 !important;
        transition: all 0.3s ease !important;
        white-space: nowrap !important;
    }

    .navbar .btn i {
        font-size: 0.875rem !important;
        margin-right: 0 !important;
    }

    .navbar .btn-sm {
        padding: 0.375rem 0.75rem !important;
        font-size: 0.875rem !important;
    }

    .navbar .btn-sm i {
        font-size: 0.8rem !important;
    }

    /* Fix navbar layout */
    .navbar .d-flex {
        gap: 0.5rem;
    }

    /* Ensure dropdowns work on all screen sizes */
    @media (max-width: 991.98px) {
        .dropdown-menu {
            position: static !important;
            transform: none !important;
            box-shadow: none;
            border: 1px solid #dee2e6;
            border-radius: 0;
            margin-top: 0;
        }
        
        .navbar .d-flex {
            flex-direction: column;
            align-items: stretch;
            width: 100%;
        }
        
        .navbar .d-flex > * {
            margin: 0.25rem 0;
        }
    }

    /* Desktop hover effect for dropdowns */
    @media (min-width: 992px) {
        .navbar-nav .dropdown:hover .dropdown-menu {
            display: block;
        }
    }
</style>
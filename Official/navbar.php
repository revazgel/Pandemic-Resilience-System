<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="container">
    <a class="navbar-brand text-white" href="dashboard_official.php">
        <i class="fas fa-shield-virus"></i> PRS Official Portal
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav me-auto">
            <li class="nav-item">
                <a class="nav-link text-white <?php echo $current_page === 'dashboard_official.php' ? 'active' : ''; ?>" 
                   href="dashboard_official.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo $current_page === 'manage_merchants.php' ? 'active' : ''; ?>" 
                   href="manage_merchants.php">
                    <i class="fas fa-store"></i> Merchants
                </a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-white <?php echo in_array($current_page, ['manage_citizens.php', 'citizen_details.php']) ? 'active' : ''; ?>" 
                   href="#" id="citizensDropdown" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-users"></i> Citizens
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item text-dark" href="manage_citizens.php">Manage Citizens</a></li>
                    <li><a class="dropdown-item text-dark" href="citizen_purchases.php">Purchase History</a></li>
                </ul>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-white <?php echo in_array($current_page, ['manage_vaccinations.php', 'vaccination_details.php']) ? 'active' : ''; ?>" 
                   href="#" id="vaccinationsDropdown" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-syringe"></i> Vaccinations
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item text-dark" href="manage_vaccinations.php">Manage Records</a></li>
                    <li><a class="dropdown-item text-dark" href="vaccination_reports.php">Reports</a></li>
                </ul>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-white <?php echo in_array($current_page, ['manage_items.php', 'manage_schedules.php']) ? 'active' : ''; ?>" 
                   href="#" id="systemDropdown" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-cogs"></i> System
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item text-dark" href="manage_items.php">Critical Items</a></li>
                    <li><a class="dropdown-item text-dark" href="manage_schedules.php">Purchase Schedules</a></li>
                </ul>
            </li>
        </ul>
        <ul class="navbar-nav">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-white" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item text-dark" href="official_profile.php">Profile</a></li>
                    <li><a class="dropdown-item text-dark" href="change_password.php">Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-dark" href="../Authentication/logout.php">Logout</a></li>
                </ul>
            </li>
        </ul>
    </div>
</div> 
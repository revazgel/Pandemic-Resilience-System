<?php
require_once '../Authentication/session_check.php';
if ($_SESSION['role'] !== 'Official') { header("Location: ../Authentication/login.html"); exit(); }
$host = 'localhost'; $db = 'CovidSystem'; $user = 'root'; $pass = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE user_id = ?");
    $stmt->execute([$id]);
    $citizen = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$citizen) throw new Exception('Citizen not found');
    // Vaccination records
    $stmt = $pdo->prepare("SELECT * FROM vaccination_records WHERE user_id = ? ORDER BY vaccination_date DESC");
    $stmt->execute([$id]);
    $vaccs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Purchase history
    $stmt = $pdo->prepare("SELECT p.*, ci.item_name, ci.item_category, ci.unit_of_measure, m.business_name FROM purchases p JOIN critical_items ci ON p.item_id = ci.item_id JOIN merchants m ON p.merchant_id = m.merchant_id WHERE p.user_id = ? ORDER BY p.purchase_date DESC");
    $stmt->execute([$id]);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Eligibility for today
    $today_day = date('N');
    $dob_year = date('Y', strtotime($citizen['dob']));
    $last_digit = substr($dob_year, -1);
    $stmt = $pdo->prepare("
        SELECT ci.item_name FROM purchase_schedule ps
        JOIN critical_items ci ON ps.item_id = ci.item_id
        WHERE ps.day_of_week = ? AND FIND_IN_SET(?, ps.dob_year_ending) > 0
        AND CURDATE() BETWEEN ps.effective_from AND IFNULL(ps.effective_to, '9999-12-31')");
    $stmt->execute([$today_day, $last_digit]);
    $eligible_today = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { die('Citizen not found or DB error.'); }
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Citizen Details - PRS</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><link rel="stylesheet" href="../css/official_styles.css"></head><body><?php include 'navbar.php'; ?><div class="container py-4"><div class="page-header"><h2><i class="fas fa-user"></i> Citizen Details</h2><div><a href="dashboard_official.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a><a href="manage_citizens.php" class="btn btn-outline-secondary ms-2"><i class="fas fa-users"></i> Back to Citizens</a></div></div><div class="card mb-4"><div class="card-header bg-primary text-white"><h4 class="mb-0"><i class="fas fa-user"></i> <?php echo htmlspecialchars($citizen['full_name']); ?></h4></div><div class="card-body"><div class="row mb-3"><div class="col-md-6"><p><strong>PRS-ID:</strong> <?php echo htmlspecialchars($citizen['prs_id']); ?></p><p><strong>Date of Birth:</strong> <?php echo date('Y-m-d', strtotime($citizen['dob'])); ?></p><p><strong>Registered:</strong> <?php echo date('Y-m-d', strtotime($citizen['created_at'])); ?></p></div><div class="col-md-6"><p><strong>Email:</strong> <?php echo htmlspecialchars($citizen['email'] ?? '-'); ?></p><p><strong>Phone:</strong> <?php echo htmlspecialchars($citizen['phone'] ?? '-'); ?></p><p><strong>Address:</strong> <?php echo htmlspecialchars($citizen['address'] ?? '-'); ?></p></div></div><div class="mb-3"><strong>Eligible to purchase today:</strong> <?php if ($eligible_today) { echo implode(', ', array_map('htmlspecialchars', $eligible_today)); } else { echo '<span class="text-muted">None</span>'; } ?></div></div></div><div class="row"><div class="col-md-6"><div class="card mb-4"><div class="card-header"><i class="fas fa-syringe"></i> Vaccination Records</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Dose</th><th>Date</th><th>Provider</th><th>Batch</th></tr></thead><tbody><?php foreach ($vaccs as $v): ?><tr><td><?php echo htmlspecialchars($v['dose_number']); ?></td><td><?php echo htmlspecialchars($v['vaccination_date']); ?></td><td><?php echo htmlspecialchars($v['healthcare_provider']); ?></td><td><?php echo htmlspecialchars($v['batch_number']); ?></td></tr><?php endforeach; if (!$vaccs): ?><tr><td colspan="4" class="text-muted">No records</td></tr><?php endif; ?></tbody></table></div></div></div></div><div class="col-md-6"><div class="card mb-4"><div class="card-header"><i class="fas fa-shopping-cart"></i> Purchase History</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Item</th><th>Qty</th><th>Date</th><th>Merchant</th></tr></thead><tbody><?php foreach ($purchases as $p): ?><tr><td><?php echo htmlspecialchars($p['item_name']); ?></td><td><?php echo htmlspecialchars($p['quantity']); ?></td><td><?php echo htmlspecialchars($p['purchase_date']); ?></td><td><?php echo htmlspecialchars($p['business_name']); ?></td></tr><?php endforeach; if (!$purchases): ?><tr><td colspan="4" class="text-muted">No purchases</td></tr><?php endif; ?></tbody></table></div></div></div></div></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html> 
<?php
require_once '../Authentication/session_check.php';
if ($_SESSION['role'] !== 'Official') { header("Location: ../Authentication/login.html"); exit(); }
$host = 'localhost'; $db = 'CovidSystem'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT user_id, prs_id, full_name, dob, created_at FROM Users WHERE role = 'Citizen' ORDER BY created_at DESC");
    $citizens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Registration stats for chart
    $reg_stats = [];
    $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as cnt FROM Users WHERE role = 'Citizen' GROUP BY ym ORDER BY ym DESC LIMIT 12");
    foreach ($stmt as $row) $reg_stats[$row['ym']] = $row['cnt'];
    $reg_stats = array_reverse($reg_stats, true);
    // Fetch vaccination and purchase counts for all citizens
    $user_ids = array_column($citizens, 'user_id');
    $vacc_counts = [];
    $purch_counts = [];
    if ($user_ids) {
        $in = str_repeat('?,', count($user_ids)-1) . '?';
        $stmt = $pdo->prepare("SELECT user_id, COUNT(*) as cnt FROM vaccination_records WHERE user_id IN ($in) GROUP BY user_id");
        $stmt->execute($user_ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $vacc_counts[$row['user_id']] = $row['cnt'];
        $stmt = $pdo->prepare("SELECT user_id, COUNT(*) as cnt FROM purchases WHERE user_id IN ($in) GROUP BY user_id");
        $stmt->execute($user_ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $purch_counts[$row['user_id']] = $row['cnt'];
    }
    // Eligibility for today
    $today_day = date('N');
    $eligibility = [];
    $sched_stmt = $pdo->prepare("
        SELECT ci.item_name FROM purchase_schedule ps
        JOIN critical_items ci ON ps.item_id = ci.item_id
        WHERE ps.day_of_week = ? AND FIND_IN_SET(?, ps.dob_year_ending) > 0
        AND CURDATE() BETWEEN ps.effective_from AND IFNULL(ps.effective_to, '9999-12-31')");
    foreach ($citizens as $c) {
        $dob_year = date('Y', strtotime($c['dob']));
        $last_digit = substr($dob_year, -1);
        $sched_stmt->execute([$today_day, $last_digit]);
        $eligibility[$c['user_id']] = $sched_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    // Calculate purchasing activity and eligibility for pie charts
    $purch_with = $purch_without = $elig_with = $elig_without = 0;
    foreach ($citizens as $c) {
        $uid = $c['user_id'];
        if (!empty($purch_counts[$uid])) $purch_with++; else $purch_without++;
        if (!empty($eligibility[$uid])) $elig_with++; else $elig_without++;
    }
    // After eligibility and purchase counts
    $total_citizens = count($citizens);
} catch (PDOException $e) { $_SESSION['error_message'] = "Database error: " . $e->getMessage(); $citizens = []; }
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Manage Citizens - PRS</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><link rel="stylesheet" href="../css/official_styles.css"></head><body>
<nav class="navbar navbar-expand-lg" style="background: linear-gradient(90deg, #8e2de2 0%, #ff6a00 100%);">
<?php include 'navbar.php'; ?>
</nav>
<div class="container py-4">
    <div class="page-header">
        <h2><i class="fas fa-users"></i> Manage Citizens</h2>
        <div>
            <a href="dashboard_official.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    <div class="row mb-3 g-2"><div class="col-md-3"><div class="card text-center p-2"><div class="card-body p-2"><div class="h5 mb-1"><?php echo $total_citizens; ?></div><div class="small text-muted">Total Citizens</div></div></div></div><div class="col-md-3"><div class="card text-center p-2"><div class="card-body p-2"><div class="h5 mb-1"><?php echo $purch_with; ?></div><div class="small text-muted">With Purchases</div></div></div></div><div class="col-md-3"><div class="card text-center p-2"><div class="card-body p-2"><div class="h5 mb-1"><?php echo $elig_with; ?></div><div class="small text-muted">Eligible Today</div></div></div></div><div class="col-md-3"><div class="card text-center p-2"><div class="card-body p-2"><div class="h5 mb-1"><?php echo $purch_without; ?></div><div class="small text-muted">No Purchases</div></div></div></div></div><div class="row mb-3"><div class="col-md-8"><div class="input-group"><span class="input-group-text"><i class="fas fa-search"></i></span><input type="text" id="citizenSearch" class="form-control" placeholder="Search by PRS-ID, name, or DOB..."></div></div></div><div class="mb-3 d-flex flex-wrap gap-2"><button class="btn btn-outline-primary btn-sm" onclick="filterCitizens('all')">All</button><button class="btn btn-outline-success btn-sm" onclick="filterCitizens('eligible')">Eligible Today</button><button class="btn btn-outline-info btn-sm" onclick="filterCitizens('withpurch')">With Purchases</button><button class="btn btn-outline-secondary btn-sm" onclick="filterCitizens('nopurch')">No Purchases</button></div><div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0" id="citizensTable"><thead class="table-light"><tr><th>PRS-ID</th><th>Name</th><th>Date of Birth</th><th>Registered</th><th>Vaccinations</th><th>Purchases</th><th>Eligible Today</th><th>Actions</th></tr></thead><tbody><?php foreach ($citizens as $c): $uid=$c['user_id']; ?><tr><td><?php echo htmlspecialchars($c['prs_id']); ?></td><td><?php echo htmlspecialchars($c['full_name']); ?></td><td><?php echo date('Y-m-d', strtotime($c['dob'])); ?></td><td><?php echo date('Y-m-d', strtotime($c['created_at'])); ?></td><td><?php echo $vacc_counts[$uid] ?? '-'; ?></td><td><?php echo $purch_counts[$uid] ?? '-'; ?></td><td><?php if (!empty($eligibility[$uid])) { echo '<ul class="mb-0">'; foreach ($eligibility[$uid] as $item) echo '<li>'.htmlspecialchars($item).'</li>'; echo '</ul>'; } else { echo '<span class="text-muted">None</span>'; } ?></td><td><a href="citizen_details.php?id=<?php echo $c['user_id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> Details</a></td></tr><?php endforeach; ?></tbody></table></div></div></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>document.getElementById('citizenSearch').addEventListener('input',function(){const v=this.value.toLowerCase();document.querySelectorAll('#citizensTable tbody tr').forEach(row=>{row.style.display=Array.from(row.cells).slice(0,7).some(cell=>cell.textContent.toLowerCase().includes(v))?'':'none';});});
// Quick filter logic
function filterCitizens(type) {
  document.querySelectorAll('#citizensTable tbody tr').forEach(row => {
    let show = true;
    if (type === 'eligible') show = row.querySelector('ul') && row.querySelector('ul').children.length > 0;
    if (type === 'withpurch') show = row.cells[5].textContent.trim() !== '-' && row.cells[5].textContent.trim() !== '0';
    if (type === 'nopurch') show = row.cells[5].textContent.trim() === '-' || row.cells[5].textContent.trim() === '0';
    row.style.display = show ? '' : 'none';
  });
}
</script></body></html> 
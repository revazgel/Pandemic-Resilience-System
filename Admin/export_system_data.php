<?php
// export_system_data.php
require_once '../Authentication/session_check.php';
require_once '../vendor/autoload.php';

// Check if user has the Admin role
if ($_SESSION['role'] !== 'Admin') {
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
    
    // Create PDF export
    $pdf = new TCPDF();
    $pdf->SetCreator('Pandemic Resilience System');
    $pdf->SetAuthor('System Administrator');
    $pdf->SetTitle('System Data Export');
    $pdf->SetSubject('System Overview Report');
    
    // Add page
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 15, 'Pandemic Resilience System - Data Export', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Export Date: ' . date('Y-m-d H:i:s'), 0, 1, 'R');
    $pdf->Ln(10);
    
    // User Statistics
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'User Statistics', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);
    
    $stats = [
        'Admin' => 0,
        'Official' => 0,
        'Merchant' => 0,
        'Citizen' => 0
    ];
    
    foreach ($stats as $role => $count) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
        $stmt->execute([$role]);
        $stats[$role] = $stmt->fetchColumn();
    }
    
    $html = '<table border="1" cellpadding="5">
        <tr style="background-color:#f0f0f0;">
            <th>Role</th>
            <th>Count</th>
        </tr>';
    
    foreach ($stats as $role => $count) {
        $html .= "<tr><td>$role</td><td>$count</td></tr>";
    }
    $html .= '</table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(10);
    
    // System Information
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'System Information', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);
    
    $systemInfo = [
        'PHP Version' => phpversion(),
        'Database' => 'MySQL 8.0',
        'Server' => $_SERVER['SERVER_NAME'] ?? 'localhost',
        'Export By' => $_SESSION['username'],
        'Total Sessions' => session_status()
    ];
    
    $html = '<table border="1" cellpadding="5">
        <tr style="background-color:#f0f0f0;">
            <th>Property</th>
            <th>Value</th>
        </tr>';
    
    foreach ($systemInfo as $property => $value) {
        $html .= "<tr><td>$property</td><td>$value</td></tr>";
    }
    $html .= '</table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(10);
    
    // Recent Activity
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Recent System Activity', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);
    
    $stmt = $pdo->query("
        SELECT al.*, u.username
        FROM access_logs al
        JOIN users u ON al.user_id = u.user_id
        ORDER BY al.timestamp DESC
        LIMIT 10
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($logs)) {
        $html = '<table border="1" cellpadding="5">
            <tr style="background-color:#f0f0f0;">
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Details</th>
            </tr>';
        
        foreach ($logs as $log) {
            $time = date('m/d H:i', strtotime($log['timestamp']));
            $html .= "<tr>
                <td>$time</td>
                <td>{$log['username']}</td>
                <td>{$log['action_type']}</td>
                <td>{$log['action_details']}</td>
            </tr>";
        }
        $html .= '</table>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
    }
    
    // Log the export action
    $stmt = $pdo->prepare("
        INSERT INTO access_logs (
            user_id, 
            ip_address, 
            action_type, 
            action_details, 
            entity_type,
            entity_id
        ) VALUES (?, ?, 'system_export', 'Exported system data to PDF', 'system', 1)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $_SERVER['REMOTE_ADDR']
    ]);
    
    // Output PDF
    $filename = 'PRS_System_Export_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output($filename, 'D');
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error exporting data: " . $e->getMessage();
    header("Location: admin_system.php");
    exit();
}
?>
<?php
// generate_reports.php
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
    
    // Create PDF report
    $pdf = new TCPDF();
    $pdf->SetCreator('Pandemic Resilience System');
    $pdf->SetAuthor('System Administrator');
    $pdf->SetTitle('System Analytics Report');
    $pdf->SetSubject('Detailed System Reports');
    
    // Add page
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->Cell(0, 15, 'Pandemic Resilience System', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Comprehensive Analytics Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Generated on: ' . date('F j, Y \a\t H:i'), 0, 1, 'C');
    $pdf->Ln(15);
    
    // Executive Summary
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, '1. Executive Summary', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);
    
    // Get key metrics
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $new_users_month = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM official_approvals WHERE status = 'Pending'");
    $pending_approvals = $stmt->fetchColumn();
    
    $summary = "
    • Total System Users: $total_users
    • New Users This Month: $new_users_month
    • Pending Official Approvals: $pending_approvals
    • System Status: Operational
    ";
    
    $pdf->writeHTML(nl2br($summary), true, false, true, false, '');
    $pdf->Ln(10);
    
    // User Analysis
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, '2. User Analysis by Role', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);
    
    $roles = ['Admin', 'Official', 'Merchant', 'Citizen'];
    $html = '<table border="1" cellpadding="8">
        <tr style="background-color:#4CAF50; color:white;">
            <th>Role</th>
            <th>Total Users</th>
            <th>Active This Month</th>
            <th>Percentage</th>
        </tr>';
    
    foreach ($roles as $role) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
        $stmt->execute([$role]);
        $count = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT u.user_id) 
            FROM users u 
            JOIN access_logs al ON u.user_id = al.user_id 
            WHERE u.role = ? AND al.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$role]);
        $active = $stmt->fetchColumn();
        
        $percentage = $total_users > 0 ? round(($count / $total_users) * 100, 1) : 0;
        
        $html .= "<tr>
            <td>$role</td>
            <td>$count</td>
            <td>$active</td>
            <td>{$percentage}%</td>
        </tr>";
    }
    $html .= '</table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(15);
    
    // Activity Analysis
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, '3. System Activity Analysis', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);
    
    // Daily activity for last 7 days
    $html = '<table border="1" cellpadding="8">
        <tr style="background-color:#2196F3; color:white;">
            <th>Date</th>
            <th>Login Count</th>
            <th>Total Actions</th>
            <th>Unique Users</th>
        </tr>';
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $display_date = date('M j', strtotime("-$i days"));
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM access_logs 
            WHERE action_type = 'login' 
            AND DATE(timestamp) = ?
        ");
        $stmt->execute([$date]);
        $logins = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM access_logs 
            WHERE DATE(timestamp) = ?
        ");
        $stmt->execute([$date]);
        $total_actions = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT user_id) 
            FROM access_logs 
            WHERE DATE(timestamp) = ?
        ");
        $stmt->execute([$date]);
        $unique_users = $stmt->fetchColumn();
        
        $html .= "<tr>
            <td>$display_date</td>
            <td>$logins</td>
            <td>$total_actions</td>
            <td>$unique_users</td>
        </tr>";
    }
    $html .= '</table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(15);
    
    // Security Analysis
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, '4. Security and Access Analysis', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);
    
    // Top IP addresses
    $stmt = $pdo->query("
        SELECT ip_address, COUNT(*) as access_count 
        FROM access_logs 
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY ip_address 
        ORDER BY access_count DESC 
        LIMIT 5
    ");
    $top_ips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $html = '<p><strong>Top IP Addresses (Last 7 Days):</strong></p>
        <table border="1" cellpadding="8">
        <tr style="background-color:#FF9800; color:white;">
            <th>IP Address</th>
            <th>Access Count</th>
        </tr>';
    
    foreach ($top_ips as $ip) {
        $html .= "<tr>
            <td>{$ip['ip_address']}</td>
            <td>{$ip['access_count']}</td>
        </tr>";
    }
    $html .= '</table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(15);
    
    // Recommendations
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, '5. Recommendations', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);
    
    $recommendations = [
        "Monitor pending official approvals regularly to prevent backlog",
        "Review user activity patterns for potential security issues",
        "Consider implementing automated reports for daily monitoring",
        "Backup system data regularly to prevent data loss",
        "Review and update user permissions quarterly"
    ];
    
    $html = '<ul>';
    foreach ($recommendations as $rec) {
        $html .= "<li style=\"margin-bottom:5px;\">$rec</li>";
    }
    $html .= '</ul>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Add footer with page numbers
    $pdf->setFooterData(array(0,64,0), array(0,64,128));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Log the report generation
    $stmt = $pdo->prepare("
        INSERT INTO access_logs (
            user_id, 
            ip_address, 
            action_type, 
            action_details, 
            entity_type,
            entity_id
        ) VALUES (?, ?, 'generate_report', 'Generated comprehensive system report', 'system', 1)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $_SERVER['REMOTE_ADDR']
    ]);
    
    // Output PDF
    $filename = 'PRS_Analytics_Report_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output($filename, 'D');
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error generating report: " . $e->getMessage();
    header("Location: admin_system.php");
    exit();
}
?>
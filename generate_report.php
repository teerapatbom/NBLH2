<?php

require_once "connect.php";

$FY = $_POST["FY"] ?? "";
$date_start = $_POST["date_start"] ?? "";
$date_end = $_POST["date_end"] ?? "";
$report_id = (int)($_POST["report_id"] ?? 0);

// ตรวจสอบ input
if (empty($FY) || empty($date_start) || empty($date_end) || $report_id <= 0) {
    die("ข้อมูลไม่ครบ");
}

// ดึงข้อมูลรายงานจากฐานข้อมูล
$stmt = $conn->prepare("SELECT report_file FROM reports_pdf WHERE report_id = ? AND active = 1");
$stmt->execute([$report_id]);
$reportData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reportData) {
    die("ไม่พบรายงาน");
}

$report = $reportData['report_file'];

// Base path สำหรับไฟล์ jasper
$reportDir = __DIR__ . '/jasper/templates';

// ลองหลายชื่อ path ที่เป็นไปได้
$possiblePaths = [
    $reportDir . '/' . $report,              // ชื่อจากตาราง
    $reportDir . '/' . pathinfo($report, PATHINFO_FILENAME) . '_myl.jasper',  // เพิ่ม _myl
    __DIR__ . '/' . $report,
];

$foundReport = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $foundReport = $path;
        break;
    }
}

if (!$foundReport) {
    // แสดง debug info
    $debugInfo = "ไม่พบไฟล์รายงาน: $report\n\n";
    $debugInfo .= "Path ที่ลองหา:\n";
    foreach ($possiblePaths as $path) {
        $exists = file_exists($path) ? "✓" : "✗";
        $debugInfo .= "$exists $path\n";
    }
    
    // ตรวจสอบไฟล์ในโฟลเดอร์ jasper/templates
    if (is_dir($reportDir)) {
        $debugInfo .= "\nไฟล์ใน $reportDir:\n";
        foreach (scandir($reportDir) as $file) {
            if ($file !== '.' && $file !== '..' && strpos($file, '.jasper') !== false) {
                $debugInfo .= "  - $file\n";
            }
        }
    }
    
    die(htmlspecialchars($debugInfo));
}

// Java path
$javaExe = "C:\\Program Files\\Java\\jre1.8.0_471\\bin\\java.exe";
$jasperLib = "C:\\xampp\\htdocs\\NBLH\\jasper\\JasperStarter\\lib";

// ตรวจสอบ Java ติดตั้งไหม
if (!file_exists($javaExe)) {
    die("ไม่พบ Java: " . htmlspecialchars($javaExe));
}

// ตรวจสอบ JasperStarter lib
if (!is_dir($jasperLib)) {
    die("ไม่พบ JasperStarter lib: " . htmlspecialchars($jasperLib));
}

$tmp = sys_get_temp_dir();
$output = $tmp . "/report_" . time();

// สร้าง classpath
$classpath = "\"$jasperLib\\*;$jasperLib\\fonts\"";

// เรียก Java โดยตรง เหมือน run_jasper.bat
$cmd = "\"$javaExe\" -cp $classpath de.cenote.jasperstarter.App pr \"$foundReport\" -f pdf -t mysql -H localhost -u root -p 1q2w3e4r5t -n nblh -o \"$output\" -P FY=$FY date_start=$date_start date_end=$date_end 2>&1";

// รันคำสั่งและเก็บ output
$output_result = [];
$return_var = 0;
exec($cmd, $output_result, $return_var);

// ถ้าเกิดข้อผิดพลาด
if ($return_var !== 0) {
    die("Error generating report:\n" . htmlspecialchars(implode("\n", $output_result)));
}

$pdf = $output . ".pdf";

// Check if PDF created
if (!file_exists($pdf)) {
    die("ไม่สามารถสร้าง PDF ได้");
}

header("Content-Type: application/pdf");
header("Content-Disposition: inline; filename=report_" . date('Y_m_d_H_i_s') . ".pdf");
readfile($pdf);

// ลบไฟล์ temp
if (file_exists($pdf)) {
    unlink($pdf);
}
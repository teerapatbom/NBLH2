<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";
require_once __DIR__ . '/vendor/autoload.php';

/* =========================
   ฟังก์ชันช่วยเหลือ
========================= */
function arabicToThaiNumber($number): string {
    if ($number === null) return '';
    return str_replace(
        ['0','1','2','3','4','5','6','7','8','9'],
        ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'],
        (string)$number
    );
}

if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}


/* =========================
   รับ Filter
========================= */
$fiscalYear = $_GET['year'] ?? '';
$startDate  = $_GET['start'] ?? '';
$endDate    = $_GET['end'] ?? '';

$where  = [];
$params = [];

if ($fiscalYear !== '') {
    $where[]  = "d.FiscalYear = ?";
    $params[] = $fiscalYear;
}

if ($startDate !== '') {
    $where[]  = "d.CreatedAt >= ?";
    $params[] = $startDate . ' 00:00:00';
}

if ($endDate !== '') {
    $where[]  = "d.CreatedAt <= ?";
    $params[] = $endDate . ' 23:59:59';
}

/* =========================
   QUERY
========================= */
$sql = "
SELECT d.*, t.DocTypeName,m.Name
FROM documents d
LEFT JOIN doctypes t ON d.DocTypeID = t.DocTypeID
LEFT JOIN member m ON m.memberid = d.memberid
";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY d.DocID ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   สร้างข้อความช่วงวันที่
========================= */
$filterText = '';

if ($startDate && $endDate) {
    $start = new DateTime($startDate);
    $end   = new DateTime($endDate);

    $startStr = arabicToThaiNumber($start->format('d/m/') . ($start->format('Y') + 543));
    $endStr   = arabicToThaiNumber($end->format('d/m/') . ($end->format('Y') + 543));

    $filterText = "ตั้งแต่วันที่ {$startStr} ถึง {$endStr}";
} elseif ($startDate) {
    $start = new DateTime($startDate);
    $startStr = arabicToThaiNumber($start->format('d/m/') . ($start->format('Y') + 543));
    $filterText = "ตั้งแต่วันที่ {$startStr}";
} elseif ($endDate) {
    $end = new DateTime($endDate);
    $endStr = arabicToThaiNumber($end->format('d/m/') . ($end->format('Y') + 543));
    $filterText = "ถึงวันที่ {$endStr}";
}

/* =========================
   เริ่ม HTML
========================= */
$html = '
<style>
body { 
    font-family: "sarabun";
    font-size:16pt;
}

table {
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed;
}

th, td {
    border: 1px solid #000;
    padding: 18px 16px;   /* เพิ่มความกว้างของช่อง */
    font-size: 30pt;      /* ตัวหนังสือใหญ่ขึ้น */
    line-height: 5.5;     /* ระยะห่างบรรทัด */
    vertical-align: top;
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
}

thead th {
    font-size: 30pt;      /* หัวตารางใหญ่ */
    font-weight: bold;
    text-align: center;
}

thead tr { 
    background-color: #e6e6e6; 
}

h1 { 
    text-align:center; 
    margin-bottom:5px;
    font-size:24pt;
}

h2 { 
    text-align:center; 
    margin-top:0;
    font-size:22pt;
}

p  { 
    text-align:center; 
    margin:0;
    font-size:12pt;
}
</style>

<h1>รายงานทะเบียนรับหนังสือ</h1>
<h2>โรงพยาบาลหนองบัวลำภู</h2>
<p>'.$filterText.'</p>
<br>

<table>
<thead>
<tr>
    <th width="8%">เลขที่รับ</th>
    <th width="10%">เลขคุม</th>
    <th width="10%">จำนวนเงิน</th>
    <th width="10%">หน่วยงาน</th>
    <th width="20%">ชื่อเรื่อง</th>
    <th width="10%">วันที่ยื่น</th>
    <th width="10%">เจ้าของเรื่อง</th>
    <th width="17%">หมายเหตุ</th>
    <th width="5%">ลายเซ็น</th>
</tr>
</thead>

<tbody>
';

/* =========================
   แสดงข้อมูล
========================= */
$totalAmount = 0.0;

foreach ($rows as $row) {

    $amount = is_numeric($row['Amount'] ?? null)
        ? (float)$row['Amount']
        : 0.0;

    $totalAmount += $amount;

    // วันที่ พ.ศ.
    $dateThai = '';
    if (!empty($row['SubmitDate'])) {
        $date = new DateTime($row['SubmitDate']);
        $dateThai = arabicToThaiNumber(
            $date->format('d/m/') . ($date->format('Y') + 543)
        );
    }

    $html .= '<tr>';
    $html .= '<td align="center">'.arabicToThaiNumber($row['DocID'] ?? '').'</td>';
    $html .= '<td align="center">'.arabicToThaiNumber($row['ControlNumber'] ?? '').'</td>';
    $html .= '<td align="right">'.arabicToThaiNumber(number_format($amount,2)).'</td>';
    $html .= '<td align="center">'.e($row['DocTypeName']).'</td>';
    $html .= '<td>'.e($row['Title']).'</td>';
    $html .= '<td align="center">'.$dateThai.'</td>';
    $html .= '<td align="center">'.e($row['Name']).'</td>';
    $html .= '<td align="center">'.e($row['Note']).'</td>';
    $html .= '<td align="center">.............</td>';
    $html .= '</tr>';
}

/* =========================
   รวมยอด
========================= */
$html .= '
<tr style="background:#f0f0f0;font-weight:bold;">
    <td colspan="8" align="right">รวมทั้งสิ้น</td>
    <td align="right">'.arabicToThaiNumber(number_format($totalAmount,2)).'</td>
</tr>';

$html .= '</tbody></table>';

/* =========================
   mPDF
========================= */
$defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new \Mpdf\Mpdf([
    'fontDir' => array_merge($fontDirs, [__DIR__ . '/fonts']),
    'fontdata' => $fontData + [
        'sarabun' => [
            'R' => 'sarabun-Regular.ttf',
            'B' => 'sarabun-Bold.ttf',
        ]
    ],
    'default_font' => 'sarabun',
    'format' => 'A4-L',
    'mode' => 'utf-8',
    'margin_top' => 15,
    'margin_bottom' => 15,
    'margin_left' => 10,
    'margin_right' => 10,
]);

$mpdf->SetFooter('{PAGENO}/{nbpg}');
$mpdf->WriteHTML($html);
$mpdf->Output('report_' . date('Ymd_His') . '.pdf', 'I');
exit;

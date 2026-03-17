<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";
require_once "vendor/autoload.php";

use Mpdf\Mpdf;

/* =========================
   AUTH
========================= */
requireLogin();

/* =========================
   PARAM
========================= */
$requestId = (int)($_GET['request_id'] ?? 0);
if ($requestId <= 0) {
    exit('Invalid request');
}

/* =========================
   ดึงข้อมูลหัวเอกสาร
========================= */
$headStmt = $conn->prepare("
    SELECT r.request_id,
           r.request_date,
           r.department,
           r.remark,
           u.Name,
           w.warehouse_name,
           d.DocTypeName
    FROM supply_requests r
    JOIN member u ON r.user_id = u.memberid
    JOIN doctypes d ON d.DocTypeID = u.DocTypeID
    JOIN warehouses w ON r.warehouse_id = w.warehouse_id
    WHERE r.request_id = ?
");

$headStmt->execute([$requestId]);
$head = $headStmt->fetch(PDO::FETCH_ASSOC);

if (!$head) {
    exit('ไม่พบข้อมูลการเบิก');
}

$remark = trim($head['remark'] ?? '');

/* =========================
   ดึงรายการพัสดุ
========================= */
$itemStmt = $conn->prepare("
    SELECT s.ProductCode,s.supply_name, i.qty,s.unit
    FROM supply_request_items i
    JOIN supplies s ON i.supply_id = s.supply_id
    WHERE i.request_id = ?
");
$itemStmt->execute([$requestId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$date = '';
if (!empty($head['request_date'])) {
    $ts = strtotime($head['request_date']);
    $date = date('d/m/', $ts) . (date('Y', $ts) + 543);
}


/* =========================
   HTML สำหรับ PDF
========================= */
$html = '
<style>
* { margin: 0; padding: 0; }
body { font-family: thsarabun; font-size: 11pt; line-height: 1.3; margin: 3px; }
.title { text-align: center; font-weight: bold; font-size: 16pt; margin: 5px 0 3px 0; }
.table { border-collapse: collapse; width: 100%; border: 1px solid #000; }
.table td { border: 1px solid #000; padding: 2px 3px; font-size: 11pt; }
.table th { border: 1px solid #000; padding: 2px 3px; text-align: center; font-weight: bold; font-size: 10pt; }
.center { text-align: center; }
</style>

<div class="title">ใบเบิกหรือใบส่งคืน</div>

<!-- ส่วนบนสุด: ลงนาม ของจำนวน เบิก/ส่งคืน แบบ -->
<table class="table" style="margin-bottom: 1px;">
<tr style="height: 18px;">
    <td width="30%"><strong>ลงนาม</strong> _______________</td>
    <td width="30%"><strong>ของจำนวน</strong> _____ <strong>แผ่น</strong></td>
    <td width="20%"><strong>เบิก/ส่งคืน</strong></td>
    <td width="20%" style="text-align: right;"><strong>แบบ ท. 2502</strong></td>
</tr>
</table>

<!-- ตรง เลขที่ -->
<table class="table" style="margin-bottom: 1px;">
<tr style="height: 18px;">
    <td width="50%"><strong>เลขที่</strong> _______________________</td>
    <td width="50%"></td>
</tr>
</table>

<!-- ส่วนข้อมูล: ชื่อ เบิก -->
<table class="table" style="margin-bottom: 1px;">
<tr style="height: 16px;">
    <td width="50%"><strong>ชื่อ</strong> _______________________</td>
    <td width="50%"><strong>เบิก</strong> _______________________</td>
</tr>
</table>

<!-- จาก - วันที่ต้องการ -->
<table class="table" style="margin-bottom: 1px;">
<tr style="height: 16px;">
    <td width="50%"><strong>จาก</strong> '.$head['DocTypeName'].'</td>
    <td width="50%"><strong>วันที่ต้องการ</strong> '.$date.'</td>
</tr>
</table>

<!-- ที่ - ประเมินการ -->
<table class="table" style="margin-bottom: 1px;">
<tr style="height: 16px;">
    <td width="50%"><strong>ที่</strong> _______________________</td>
    <td width="50%"><strong>ประเมินการ</strong></td>
</tr>
</table>

<!-- คลัง -->
<table class="table" style="margin-bottom: 2px;">
<tr style="height: 16px;">
    <td colspan="2"><strong>คลัง</strong> '.$head['warehouse_name'].'</td>
</tr>
</table>

<!-- ตารางสินค้าหลัก -->
<table class="table" style="margin-bottom: 1px;">
<thead>
<tr style="height: 20px;">
    <th width="5%">ลำดับ</th>
    <th width="10%">หมายเลข<br/>พัสดุ</th>
    <th width="28%">รายการสินค้า</th>
    <th width="5%">สี</th>
    <th width="7%">เบิก</th>
    <th width="7%">ยอด<br/>เบิก</th>
    <th width="7%">ราคา</th>
    <th width="7%">รวม</th>
    <th width="8%">หมายเหตุ</th>
</tr>
</thead>
<tbody>
';

$no = 1;
foreach ($items as $it) {
    $html .= '
    <tr style="height: 16px;">
        <td class="center">'.$no.'</td>
        <td class="center">'.$it['ProductCode'].'</td>
        <td>'.$it['supply_name'].'</td>
        <td class="center">-</td>
        <td class="center">'.$it['qty'].'</td>
        <td class="center">'.$it['qty'].'</td>
        <td class="center">-</td>
        <td class="center">-</td>
        <td></td>
    </tr>';
    $no++;
}

// แถวว่าง - 12 แถว
for ($i = $no; $i <= 12; $i++) {
    $html .= '
    <tr style="height: 16px;">
        <td class="center">'.$i.'</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
    </tr>';
}

$html .= '
</tbody>
</table>

<!-- หมายเหตุจำเพาะเติม/สิ่งสำคัญ -->
<div style="margin-bottom: 2px;">
<strong>หมายเหตุจำเพาะเติม/สิ่งสำคัญ</strong>
<div style="border: 1px solid #000; min-height: 35px; padding: 2px; font-size: 10pt;">
'.($remark !== '' ? nl2br(htmlspecialchars($remark)) : '&nbsp;').'
</div>
</div>

<!-- ลงชื่อผู้ได้รับ -->
<table class="table" style="margin-bottom: 1px; font-size: 10pt;">
<tr style="height: 16px;">
    <td width="50%"><strong>ลงชื่อผู้ได้รับ</strong> ________________</td>
    <td width="50%"><strong>วันที่</strong> _____ <strong>เดือน</strong> _____ <strong>ปี</strong> _____</td>
</tr>
</table>

<!-- ผู้ตรวจสอบ/สิ่งสำคัญ -->
<table class="table" style="margin-bottom: 1px; font-size: 10pt;">
<tr style="height: 16px;">
    <td width="50%"><strong>ผู้ตรวจสอบ/สิ่งสำคัญ</strong> ________________</td>
    <td width="50%"><strong>วันที่</strong> _____ <strong>เดือน</strong> _____ <strong>ปี</strong> _____</td>
</tr>
</table>

<!-- ผู้ตรวจสอบเบิก/สิ่งสำคัญ -->
<table class="table" style="margin-bottom: 2px; font-size: 10pt;">
<tr style="height: 16px;">
    <td colspan="2"><strong>ผู้ตรวจสอบเบิก/สิ่งสำคัญ</strong> ________________ <strong>วันที่</strong> _____ <strong>เดือน</strong> _____ <strong>ปี</strong> _____</td>
</tr>
</table>

<!-- ลงนาม 4 คน -->
<table width="100%" style="border: none;">
<tr style="border: none;">
    <td style="border: none; text-align: center; width: 25%; padding: 0 2px; vertical-align: top;">
        <div style="border-top: 1px solid #000; height: 48px; margin-bottom: 2px;"></div>
        <strong style="font-size: 10pt;">ผู้เตรียมพัสดุ/เบิก</strong>
    </td>
    <td style="border: none; text-align: center; width: 25%; padding: 0 2px; vertical-align: top;">
        <div style="border-top: 1px solid #000; height: 48px; margin-bottom: 2px;"></div>
        <strong style="font-size: 10pt;">ผู้ได้รับ</strong>
    </td>
    <td style="border: none; text-align: center; width: 25%; padding: 0 2px; vertical-align: top;">
        <div style="border-top: 1px solid #000; height: 48px; margin-bottom: 2px;"></div>
        <strong style="font-size: 10pt;">ผู้ตรวจสอบ</strong>
    </td>
    <td style="border: none; text-align: center; width: 25%; padding: 0 2px; vertical-align: top;">
        <div style="border-top: 1px solid #000; height: 48px; margin-bottom: 2px;"></div>
        <strong style="font-size: 10pt;">ผู้อนุมัติ</strong>
    </td>
</tr>
</table>
';
/* =========================
   สร้าง PDF + ลงทะเบียนฟอนต์ไทย
========================= */

$defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'fontDir' => array_merge($fontDirs, [
        __DIR__ . '/fonts',
    ]),
    'fontdata' => $fontData + [
        'thsarabun' => [
            'R'  => 'THSarabunNew.ttf',
            'B'  => 'THSarabunNew-Bold.ttf',
            'I'  => 'THSarabunNew-Italic.ttf',
            'BI' => 'THSarabunNew-BoldItalic.ttf',
        ]
    ],
    'default_font' => 'thsarabun'
]);

$mpdf->WriteHTML($html);
$mpdf->Output("supply_request_{$requestId}.pdf", "I");
exit;

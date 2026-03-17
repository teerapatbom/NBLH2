<?php
session_start();

if (empty($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

$serverName = "DESKTOP-J3M3468\\SQLEXPRESS";
$dbName = "NBLH";
$connectionInfo = [
    "Database" => $dbName,
    "MultipleActiveResultSets" => true,
    "CharacterSet" => "UTF-8"
];

$conn = sqlsrv_connect($serverName, $connectionInfo);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

function formatThaiDateTime($datetime) {
    if (!$datetime) return '-';
    $day = $datetime->format('d');
    $month = $datetime->format('m');
    $year = (int)$datetime->format('Y') + 543;
    $time = $datetime->format('H:i:s');
    return "$day/$month/$year $time";
}

// Handle form submission (รับ/ส่ง/เสร็จสิ้น)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_id'], $_POST['action'])) {
    $docId = intval($_POST['doc_id']);
    $action = $_POST['action'];
    $userId = $_SESSION['UserID'];
      // รับค่าหมายเหตุจากฟอร์ม (ถ้ามี)
    $remark = trim($_POST['remark'] ?? '');

    $sqlMember = "SELECT name, position FROM member WHERE MemberID = ?";
    $stmtMember = sqlsrv_query($conn, $sqlMember, [$userId]);
    if ($stmtMember === false) die(print_r(sqlsrv_errors(), true));
    $member = sqlsrv_fetch_array($stmtMember, SQLSRV_FETCH_ASSOC);
    if (!$member) die("ไม่พบผู้ใช้ในระบบ");

    $name = $member['name'];
    $position = $member['position'];

    $statusMap = ['receive' => 3, 'send' => 4, 'complete' => 5];
    if (isset($statusMap[$action])) {
        $newStatus = $statusMap[$action];

        $updateSql = "UPDATE Documents SET StatusID = ? WHERE DocID = ?";
        sqlsrv_query($conn, $updateSql, [$newStatus, $docId]);

        $insertSql = "INSERT INTO DocumentHistory (DocID, StatusID, UserID, Name, Position, CreatedAt, Remark)
              VALUES (?, ?, ?, ?, ?, GETDATE(), ?)";
      sqlsrv_query($conn, $insertSql, [$docId, $newStatus, $userId, $name, $position, $remark]);


        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// รับค่าค้นหา (search) จาก GET
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// เตรียม SQL โดยรองรับการค้นหา (ค้นหาจาก Title กับ ControlNumber)
if ($search !== '') {
    $sql = "SELECT TOP 10 d.DocID, d.ControlNumber, d.FiscalYear, d.DocType, d.Title, d.Amount, d.CreatedAt, d.Submitter, s.StatusName, d.StatusID
            FROM Documents d
            JOIN StatusTypes s ON d.StatusID = s.StatusID
            WHERE d.Title LIKE ? OR d.ControlNumber LIKE ? OR d.Submitter LIKE ? OR d.Amount LIKE ? OR CAST(d.DocID AS NVARCHAR) LIKE ?
            ORDER BY d.FiscalYear DESC, d.ControlNumber, d.CreatedAt DESC";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"];
} else {
    $sql = "SELECT TOP 10 d.DocID, d.ControlNumber, d.FiscalYear, d.DocType, d.Title, d.Amount, d.CreatedAt, d.Submitter, s.StatusName, d.StatusID
            FROM Documents d
            JOIN StatusTypes s ON d.StatusID = s.StatusID
            ORDER BY d.FiscalYear DESC, d.ControlNumber, d.CreatedAt DESC";
    $params = [];
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) die(print_r(sqlsrv_errors(), true));

function statusBadgeClass($statusId) {
    return match($statusId) {
        3 => 'success',
        4 => 'warning',
        5 => 'primary',
        default => 'secondary',
    };
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>สถานะเอกสาร</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
 <style>
  body {
    font-family: 'Sarabun', sans-serif;
    background: linear-gradient(to bottom right, #f8bbd0, #ffffff);
    min-height: 100vh;
  }

  header {
    padding: 1.5rem 0;
    text-align: center;
    color: #d63384;
    font-weight: 700;
    font-size: 2rem;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
  }

  .btn-main {
    border-radius: 50px;
    font-weight: 600;
    background-color: #f368b5;
    color: white;
    border: none;
    box-shadow: 0 4px 8px rgba(243, 104, 181, 0.4);
    transition: all 0.3s ease;
  }

  .btn-main:hover {
    background-color: #e7519f;
    box-shadow: 0 6px 12px rgba(243, 104, 181, 0.6);
    transform: translateY(-2px);
  }

  .table thead {
    background: #f368b5;
    color: white;
  }

  .table tbody tr:hover {
    background: #ffe4ef;
    cursor: pointer;
  }

  .table-responsive {
    box-shadow: 0 8px 16px #f48fb1;
    border-radius: 16px;
  
    
  }

  .input-group-text {
    background-color: #f368b5 !important;
    color: white;
  }

  .btn-primary {
    background-color: #f368b5;
    border-color: #f368b5;
  }

  .btn-primary:hover {
    background-color: #e7519f;
    border-color: #e7519f;
  }

  /* Timeline */
  .timeline {
    position: relative;
    padding-left: 40px;
    list-style: none;
    max-height: 350px;
    overflow-y: auto;
    border-left: 3px solid #f368b5;
  }

  .timeline::-webkit-scrollbar {
    width: 6px;
  }

  .timeline::-webkit-scrollbar-thumb {
    background-color: #f368b5;
    border-radius: 3px;
  }

  .timeline-item {
    position: relative;
    margin-bottom: 24px;
    padding-left: 20px;
    font-size: 0.95rem;
    color: #333;
  }

  .timeline-item::before {
    content: '';
    position: absolute;
    left: -33px;
    top: 5px;
    width: 14px;
    height: 14px;
    background: #f368b5;
    border-radius: 50%;
    box-shadow: 0 0 8px #f368b566;
  }

  .modal-header {
    background: #f368b5;
    color: white;
  }

  .modal-content {
    border-radius: 16px;
  }

  .btn-secondary {
    background-color: #e4d1d9;
    border: none;
    color: #333;
  }

  .btn-secondary:hover {
    background-color: #d4b6c3;
  }

  .btn-outline-dark {
    border-color: #d63384;
    color: #d63384;
  }

  .btn-outline-dark:hover {
    background-color: #fce4f0;
  }

  /* Signature Pad Canvas */
  #signature-pad {
    touch-action: none;
    height: 200px;
    width: 100%;
    border: 2px dashed #f368b5;
  }

  /* ปรับ badge สีตามสถานะ */
  .badge.bg-success {
    background-color: #51cf66 !important;
  }

  .badge.bg-warning {
    background-color: #f9c74f !important;
    color: #333 !important;
  }

  .badge.bg-primary {
    background-color: #339af0 !important;
  }

  .badge.bg-secondary {
    background-color: #dee2e6 !important;
    color: #333 !important;
  }
</style>
<style>
  .bg-pink {
    background-color: #f48fb1 !important;
  }
  .btn-pink:hover {
    background-color: #ec407a !important;
    box-shadow: 0 4px 12px rgba(236, 64, 122, 0.6);
    color: white !important;
  }
  .btn-main {
    border-radius: 12px;
    transition: all 0.3s ease;
  }
  .btn-main:focus, .btn-main:active {
    box-shadow: 0 0 10px #f48fb1;
    outline: none;
  }
  .btn-outline-pink {
    background-color: transparent;
  }
  .btn-outline-pink:hover {
    background-color: #f48fb1;
    color: white !important;
    box-shadow: 0 4px 12px rgba(244, 143, 177, 0.7);
  }
</style>
<style>
  /* ตาราง */
  .table-responsive {
    box-shadow: 0 8px 20px #f48fb1;
    border-radius: 1rem;
    overflow: hidden;
  }
  table.table {
    border-collapse: separate;
    border-spacing: 0;
    background-color: #fff0f6;
  }
  table.table thead tr {
    background: linear-gradient(90deg, #f48fb1, #f06292);
    color: #fff;
    font-weight: 600;
  }
  table.table thead th {
    border: none;
    text-align: center;
    vertical-align: middle;
    padding: 1rem 0.75rem;
    color:rgb(0, 0, 0); /* สีขาว */
  }

  table.table tbody tr {
    cursor: pointer;
    transition: background-color 0.3s ease;
  }
  table.table tbody tr:hover {
    background-color: #fce4ec;
  }
  table.table tbody td {
    vertical-align: middle;
    padding: 0.75rem;
    color: #880e4f;
  }
  table.table tbody td.fw-semibold {
    font-weight: 700;
    color:rgb(0, 0, 0);
  }
  table.table tbody td.text-truncate {
    max-width: 250px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* Badge */
  .badge.bg-success {
    background-color: #81c784 !important;
    color: #2e7d32 !important;
    font-weight: 600;
  }
  .badge.bg-warning {
    background-color: #ffb74d !important;
    color: #f57c00 !important;
    font-weight: 600;
  }
  .badge.bg-primary {
    background: linear-gradient(45deg, #f48fb1, #f06292) !important;
    color: #fff !important;
    font-weight: 600;
  }
  .badge.bg-secondary {
    background-color: #ce93d8 !important;
    color: #4a148c !important;
    font-weight: 600;
  }

  /* Modal */
  .modal-content {
    border-radius: 1rem;
    box-shadow: 0 8px 24px rgba(244, 143, 177, 0.4);
    background-color: #fff0f6;
    color: #4a148c;
  }
  .modal-header {
    background: linear-gradient(90deg, #f48fb1, #f06292);
    color: #fff;
    border-top-left-radius: 1rem;
    border-top-right-radius: 1rem;
    font-weight: 700;
  }
  .modal-title i {
    font-size: 1.25rem;
  }
  .modal-body {
    font-size: 1rem;
    color: #6a1b9a;
  }
  .modal-footer {
    border-top: none;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  .modal-footer form {
    flex-grow: 1;
  }

  /* ปุ่มใน Modal */
  .btn-main {
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
  }
  .btn-main.shadow {
    box-shadow: 0 4px 12px rgba(244, 143, 177, 0.5);
  }
  .btn-success.btn-main {
    background-color: #81c784;
    border: none;
    color: #2e7d32;
  }
  .btn-success.btn-main:hover {
    background-color: #66bb6a;
  }
  .btn-warning.btn-main {
    background: linear-gradient(45deg, #f48fb1, #f06292);
    border: none;
    color: white;
  }
  .btn-warning.btn-main:hover {
    background-color: #ffa726;
  }
  .btn-primary.btn-main {
    background: linear-gradient(45deg, #1E90FF, #1E90FF);
    border: none;
    color: white;
  }
  .btn-secondary {
    border-radius: 50px;
    font-weight: 600;
    background-color: #ce93d8;
    border: none;
    color: #4a148c;
    box-shadow: 0 3px 6px rgba(206, 147, 216, 0.6);
    transition: background-color 0.3s ease;
  }
  .btn-secondary:hover {
    background-color: #ba68c8;
    color: #fff;
  }
  .btn-outline-dark {
    border-radius: 50px;
    font-weight: 600;
    color: #4a148c;
    border: 1.5px solid #4a148c;
    background-color: transparent;
    transition: all 0.3s ease;
  }
  .btn-outline-dark:hover {
    background-color: #4a148c;
    color: white;
  }
  .btn-outline-secondary.btn-main {
    color: #880e4f;
    border-color: #f48fb1;
    background-color: #fff0f6;
    border-radius: 50px;
    font-weight: 600;
    padding: 0.5rem 1rem;
    transition: all 0.3s ease;
  }
  .btn-outline-secondary.btn-main:hover {
    background-color: #f48fb1;
    color: white;
  }
  .btn-close.btn-close-white {
    filter: drop-shadow(0 0 1px #fff);
  }
</style>


</head>
<body class="container py-4">

  <header>⏳สถานะเอกสาร</header>
  <!-- เพิ่มฟอร์มค้นหา -->
<form method="get" class="mb-4 d-flex justify-content-center gap-2" style="max-width: 580px; margin: 0 auto;">
  <div class="input-group input-group-lg shadow-sm rounded-3" style="min-width: 280px; flex-grow: 1; border: 1.5px solid #f8bbd0; background-color: #fff0f6;">
    <span class="input-group-text bg-pink text-white" style="background: #f48fb1; border:none; border-top-left-radius: 12px; border-bottom-left-radius: 12px;">
      <i class="bi bi-search"></i>
    </span>
    <input type="search" name="search" class="form-control border-0" placeholder="ชื่อเรื่อง,เลขที่รับ,เจ้าของเรื่อง" 
           value="<?= htmlspecialchars($search) ?>" autocomplete="off" aria-label="ค้นหา" style="background: #fff0f6; color: #880e4f;" />
    <button type="submit" class="btn btn-pink btn-main" style="background: #f48fb1; border:none; border-top-right-radius: 12px; border-bottom-right-radius: 12px; font-weight: 600; color: white;">
      <i class="bi bi-filter-circle-fill"></i> ค้นหา
    </button>
  </div>
  <button type="button" onclick="window.location.href=window.location.pathname" class="btn btn-outline-pink btn-main shadow" style="border: 1.5px solid #f48fb1; color: #f48fb1; font-weight: 600; border-radius: 12px; min-width: 120px;">
    <i class="bi bi-arrow-clockwise me-1"></i> รีเซ็ต
  </button>
</form>
  <div class="table-responsive mb-5 shadow rounded-4">
    <table class="table table-striped align-middle mb-0">
      <thead style="background-color: #f48fb1; color: white;">
        <tr class="text-center align-middle">
          <th>ปีงบประมาณ</th>
          <th>กลุ่มงาน</th>
          <th>เลขที่รับ</th>
          <th>ชื่อเรื่อง</th>
          <th>จำนวนเงิน</th>
          <th>วันที่บันทึก</th>
          <th>เจ้าของเรื่อง</th>
          <th>สถานะ</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
        <tr data-bs-toggle="modal" data-bs-target="#statusModal"
            data-doc-id="<?= htmlspecialchars($row['DocID']) ?>"
            data-status="<?= $row['StatusID'] ?>"
            data-title="<?= htmlspecialchars($row['Title']) ?>"
            style="cursor:pointer;"
        >
          <td class="text-center fw-semibold"><?= $row['FiscalYear'] ?></td>
          <td class="text-center fw-semibold"><?= htmlspecialchars($row['DocType']) ?></td>
          <td class="text-center fw-semibold"><?= htmlspecialchars($row['ControlNumber']) ?></td>
          <td class="fw-semibold text-truncate" style="max-width:250px;"><?= htmlspecialchars($row['Title']) ?></td>
          <td class="text-end fw-semibold"><?= number_format($row['Amount']) ?></td>
          <td class="text-center fw-semibold"><?= formatThaiDateTime($row['CreatedAt']) ?></td>
          <td class="text-center fw-semibold"><?= htmlspecialchars($row['Submitter']) ?></td>
          <td class="text-center">
            <span class="badge bg-<?= statusBadgeClass($row['StatusID']) ?> py-2 px-3 fs-6">
              <i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($row['StatusName']) ?>
            </span>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content shadow-lg rounded-4">
        <div class="modal-header bg-primary text-white rounded-top">
          <h5 class="modal-title fw-bold"><i class="bi bi-card-list me-2"></i> รายละเอียดสถานะเอกสาร</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <h6 id="docTitle" class="fw-bold fs-5 text-truncate"></h6>
          <div id="historyArea" class="mt-3">
            <div class="text-center text-muted">กำลังโหลดข้อมูล...</div>
          </div>
        </div>
        <div class="modal-footer">
 <form method="post" class="me-auto d-flex gap-2 flex-wrap" id="actionForm">
  <input type="hidden" name="doc_id" id="modal_doc_id" value="">
  
  <div class="w-100">
    <label for="remark" class="form-label">***หมายเหตุ</label>
    <textarea name="remark" id="remark" class="form-control" rows="2" placeholder="กรุณากรอกหมายเหตุก่อน กดรับ/กดส่งเอกสาร" required></textarea>
  </div>

  <button type="submit" name="action" value="receive" class="btn btn-success btn-main shadow" id="btnReceive">
    <i class="bi bi-check-circle me-1"></i> รับเอกสาร
  </button>
  <button type="submit" name="action" value="send" class="btn btn-warning btn-main shadow" id="btnSend">
    <i class="bi bi-send me-1"></i> ส่งคืนเอกสาร
  </button>
  <button type="submit" name="action" value="complete" class="btn btn-primary btn-main shadow" id="btnComplete">
    <i class="bi bi-flag-fill me-1"></i> ดำเนินการเสร็จสิ้น
  </button>
    <!-- ปุ่มพิมพ์ QR Code -->
  <button type="button" class="btn btn-secondary shadow" onclick="printQRCode()">
    <i class="bi bi-qr-code me-1"></i> ปริ้น QR Code
  </button>

  <!-- ปุ่มแสดงรูปลายเซ็น -->
<button type="button" class="btn btn-outline-dark shadow" data-bs-toggle="modal" data-bs-target="#signatureModal">
  <i class="bi bi-pen me-1"></i> ลายเซ็นผู้เสนอ
</button>

</form>
          <button type="button" class="btn btn-outline-secondary btn-main shadow" data-bs-dismiss="modal">ปิด</button>
        </div>
      </div>
    </div>
  </div>

<div class="modal fade" id="signatureModal" tabindex="-1" aria-labelledby="signatureModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header">
        <h5 class="modal-title" id="signatureModalLabel">เซ็นลายเซ็น</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body">
        <canvas id="signature-pad" class="w-100 border rounded" style="height: 200px;"></canvas>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="clearSignature()">ล้างลายเซ็น</button>
        <button type="button" class="btn btn-success" onclick="saveSignature()">บันทึกลายเซ็น</button>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('statusModal');
  modal.addEventListener('show.bs.modal', event => {
    const button = event.relatedTarget;
    const docId = button.getAttribute('data-doc-id');
    const title = button.getAttribute('data-title');
    const status = parseInt(button.getAttribute('data-status'));

    document.getElementById('docTitle').textContent = title;
    document.getElementById('modal_doc_id').value = docId;

    const form = document.getElementById('actionForm');
    form.style.display = (status === 5) ? 'none' : 'flex';
    form.style.display = 'flex'; // ให้แสดงเสมอ แต่จะ disable ปุ่มตามสถานะ
document.getElementById('btnReceive').disabled = (status === 5);
document.getElementById('btnSend').disabled = (status === 5);
document.getElementById('btnComplete').disabled = (status === 5);


    // โหลดประวัติเอกสารผ่าน AJAX
    fetch(`get_history.php?doc_id=${docId}`)
      .then(res => res.text())
      .then(html => {
        const historyArea = document.getElementById('historyArea');
        historyArea.innerHTML = html;

        // เลื่อน timeline ลงล่างสุดอัตโนมัติ
        const timeline = historyArea.querySelector('.timeline');
        if (timeline) timeline.scrollTop = timeline.scrollHeight;
      })
      .catch(() => {
        document.getElementById('historyArea').innerHTML = '<div class="text-danger text-center">ไม่สามารถโหลดข้อมูลได้</div>';
      });
  });
});
</script>
<script>
function printQRCode() {
  const docId = document.getElementById('modal_doc_id').value;
  if (!docId) {
    alert("ไม่พบรหัสเอกสาร");
    return;
  }

  // เปิดหน้าใหม่สำหรับ QR Code
  window.open('print_qrcode.php?doc_id=' + encodeURIComponent(docId), '_blank');
}
</script>
<!-- CSS: เพื่อให้ canvas รับการลาก -->
<style>
  #signature-pad {
    touch-action: none;
    height: 200px; /* ให้แน่ใจว่ามีความสูง */
    width: 100%;
  }
</style>

<!-- โหลด SignaturePad -->
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
<script>
  let signaturePad;

  function resizeCanvas(canvas) {
    const ratio = window.devicePixelRatio || 1;
    const width = canvas.offsetWidth;
    const height = canvas.offsetHeight;

    if (width === 0 || height === 0) return;

    canvas.width = width * ratio;
    canvas.height = height * ratio;
    canvas.getContext("2d").scale(ratio, ratio);
  }

  function clearSignature() {
    signaturePad?.clear();
  }

  function saveSignature() {
    if (!signaturePad || signaturePad.isEmpty()) {
      alert("กรุณาเซ็นลายเซ็นก่อน");
      return;
    }

    const dataURL = signaturePad.toDataURL();
    const docId = document.getElementById("modal_doc_id").value;

    fetch("save_signature.php", {
      method: "POST",
      headers: {"Content-Type": "application/json"},
      body: JSON.stringify({ doc_id: docId, signature: dataURL })
    })
    .then(res => res.text())
    .then(() => {
      alert("บันทึกลายเซ็นสำเร็จ");
      bootstrap.Modal.getInstance(document.getElementById('signatureModal')).hide();
    })
    .catch(() => alert("เกิดข้อผิดพลาดในการบันทึก"));
  }

  document.addEventListener("DOMContentLoaded", () => {
    const canvas = document.getElementById("signature-pad");
    const signatureModal = document.getElementById("signatureModal");

    signatureModal.addEventListener("shown.bs.modal", () => {
      resizeCanvas(canvas);
      signaturePad = new SignaturePad(canvas);
      signaturePad.clear();

      const docId = document.getElementById("modal_doc_id").value;
      if (docId) {
        const url = `signatures/signature_${docId}.png?${Date.now()}`;
        const ctx = canvas.getContext("2d");
        const image = new Image();
        image.onload = () => {
          ctx.clearRect(0, 0, canvas.width, canvas.height);
          ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
        };
        image.src = url;
      }
    });

    window.addEventListener("resize", () => {
      resizeCanvas(canvas);
    });
  });
</script>

</body>
</html>
<?php sqlsrv_close($conn); ?>

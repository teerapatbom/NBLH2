<?php
require_once "security.php";
require_once "connect.php";

requireLogin();
if (!hasPermission('REPORT_NEW')) { 
    http_response_code(403);
    exit("คุณไม่มีสิทธิ์เข้าหน้านี้");
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ศูนย์รายงาน</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
 <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet" />

<style>

body {
  background: linear-gradient(135deg, #fce4ec 0%, #f3e5f5 100%);
  font-family: 'Sarabun', sans-serif;
  min-height: 100vh;
  padding: 40px 20px;
}

.card {
  border-radius: 20px;
  border: none;
  box-shadow: 0 10px 40px rgba(236, 64, 122, 0.15);
  background: white;
}

.card.shadow {
  padding: 2.5rem;
}

.header {
  font-size: 32px;
  font-weight: 700;
  color: #ad1457;
  margin-bottom: 2rem;
  display: flex;
  align-items: center;
  gap: 12px;
}

.header i {
  color: #ec407a;
  font-size: 36px;
}

.form-label {
  font-weight: 600;
  color: #ad1457;
  margin-bottom: 8px;
  font-size: 0.95rem;
}

.form-select, .form-control {
  border: 2px solid #f8bbd0;
  border-radius: 12px;
  padding: 10px 15px;
  font-family: 'Sarabun', sans-serif;
  font-size: 0.95rem;
  transition: all 0.3s ease;
}

.form-select:focus, .form-control:focus {
  border-color: #ec407a;
  box-shadow: 0 0 0 0.2rem rgba(236, 64, 122, 0.15);
  outline: none;
}

.form-select:hover, .form-control:hover {
  border-color: #ec407a;
}

.form-select option {
  padding: 8px;
}

.btn-primary {
  background: linear-gradient(135deg, #ec407a 0%, #e91e63 100%);
  border: none;
  border-radius: 12px;
  padding: 12px 40px;
  font-weight: 700;
  font-size: 1.05rem;
  color: white;
  box-shadow: 0 6px 20px rgba(236, 64, 122, 0.3);
  transition: all 0.3s ease;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #e91e63 0%, #c2185b 100%);
  box-shadow: 0 8px 25px rgba(236, 64, 122, 0.4);
  transform: translateY(-2px);
}

.btn-primary:active {
  transform: translateY(0);
}

.btn-primary i {
  margin-right: 8px;
}

.row.g-3 {
  row-gap: 1.5rem !important;
}

.col-md-3, .col-md-4 {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
}

hr {
  border: none;
  border-top: 2px dashed #f8bbd0;
  margin: 2rem 0;
}

.text-center {
  display: flex;
  justify-content: center;
  gap: 15px;
  flex-wrap: wrap;
}

/* Responsive */
@media (max-width: 768px) {
  .card.shadow {
    padding: 1.5rem;
  }

  .header {
    font-size: 24px;
  }

  .btn-primary {
    width: 100%;
  }

  .row.g-3 {
    flex-direction: column;
  }
}

/* Animation */
@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.card {
  animation: slideUp 0.5s ease;
}

/* Loading Spinner */
.loading-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: none;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.loading-modal.show {
  display: flex;
  opacity: 1;
}

.spinner-container {
  background: white;
  padding: 40px;
  border-radius: 20px;
  text-align: center;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.spinner {
  width: 60px;
  height: 60px;
  border: 4px solid #f8bbd0;
  border-top: 4px solid #ec407a;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 20px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.loading-text {
  color: #ad1457;
  font-weight: 600;
  font-size: 1.1rem;
  font-family: 'Sarabun', sans-serif;
}

</style>

</head>

<body class="container py-4">

<div class="card shadow p-4">

<div class="header mb-4">
<i class="bi bi-bar-chart-fill"></i>
รายงานทะเบียนรับหนังสือ
</div>

<form id="reportForm">

<div class="row g-3">

<div class="col-12 col-sm-6 col-md-3">
<label class="form-label fw-bold">เลือกรายงาน</label>
<select name="report_id" class="form-select" required>
<?php
// ดึงรายงานจากตาราง reports_pdf
$reports = $conn->query(
    "SELECT report_id, report_name FROM reports_pdf WHERE active = 1 ORDER BY report_id"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($reports as $report) {
    echo "<option value='" . (int)$report['report_id'] . "'>" . htmlspecialchars($report['report_name']) . "</option>";
}
?>
</select>
</div>

<div class="col-12 col-sm-6 col-md-2">
<label class="form-label fw-bold">ปีงบประมาณ</label>
<select name="FY" class="form-select" required>
<?php
// ดึงปีงบประมาณจากตาราง budgetyear
$years = $conn->query(
    "SELECT FiscalYear FROM budgetyear ORDER BY FiscalYear DESC"
)->fetchAll(PDO::FETCH_COLUMN);

foreach ($years as $fy) {
    echo "<option value='$fy'>" . htmlspecialchars($fy) . "</option>";
}
?>
</select>
</div>

<div class="col-12 col-sm-6 col-md-3">
<label class="form-label fw-bold">วันที่ตั้งแต่</label>
<input type="date"
       name="date_start"
       class="form-control"
       required>
</div>

<div class="col-12 col-sm-6 col-md-3">
<label class="form-label fw-bold">วันที่สิ้นสุด</label>
<input type="date"
       name="date_end"
       class="form-control"
       required>
</div>

<div class="col-12 d-flex align-items-end">
<button type="submit"
        class="btn btn-primary btn-lg w-100">
<i class="bi bi-file-earmark-bar-graph"></i>
เปิดรายงาน
</button>
</div>

</div>

</form>

<!-- Loading Modal -->
<div id="loadingModal" class="loading-modal">
  <div class="spinner-container">
    <div class="spinner"></div>
    <div class="loading-text">กำลังสร้างรายงาน...</div>
  </div>
</div>

</div>

<script>

const form = document.getElementById("reportForm");
const loadingModal = document.getElementById("loadingModal");

form.addEventListener("submit", function(e) {
  e.preventDefault();

  // แสดง loading popup
  loadingModal.classList.add("show");

  const formData = new FormData(this);

  fetch("generate_report.php", {
    method: "POST",
    body: formData
  })
    .then(res => {
      if (!res.ok) {
        return res.text().then(text => {
          throw new Error(text);
        });
      }
      return res.blob();
    })
    .then(blob => {
      // ซ่อน loading popup
      loadingModal.classList.remove("show");
      
      // เปิด PDF
      const url = URL.createObjectURL(blob);
      window.open(url, "_blank");
    })
    .catch(err => {
      // ซ่อน loading popup
      loadingModal.classList.remove("show");
      
      // แสดง error
      alert("เกิดข้อผิดพลาด: " + err.message);
    });
});

</script>

</body>
</html>
<?php
declare(strict_types=1);

require_once "security.php"; // session + csrf + helper
require_once "connect.php";  // PDO MySQL

/* =========================
   Auth Guard
========================= */
requireLogin(); // ต้อง login ก่อน


/* =========================
   Get current admin info
========================= */
$stmt = $conn->prepare("
    SELECT MemberID, Name, Username, Position
    FROM member
    WHERE MemberID = ?
    LIMIT 1
");
$stmt->execute([$_SESSION['UserID']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: index.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>ระบบงานพัสดุ</title>
    <?php require_once "layout_head.php"; ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">

  <style>
    /* โทนสีชมพูไล่ระดับ */
    body {
      font-family: 'Sarabun', sans-serif;
      background: linear-gradient(180deg, #ffe6f0 0%, #ffffff 100%);
      margin: 0;
      color: #880e4f; /* ชมพูเข้ม */
      overflow-x: hidden;
      min-height: 100vh;
    }

    /* ปุ่ม hamburger toggle */
    .menu-toggle {
      position: fixed;
      top: 15px;
      left: 15px;
      background: #ad1457; /* ชมพูเข้ม */
      border: none;
      color: white;
      font-size: 1.8rem;
      padding: 10px 14px;
      border-radius: 12px;
      cursor: pointer;
      z-index: 1100;
      box-shadow: 0 5px 15px rgba(173,20,87,0.3);
      transition: background-color 0.3s ease, transform 0.2s ease;
    }
    .menu-toggle:hover {
      background-color: #6a1b4d; /* ชมพูเข้มกว่า */
      transform: scale(1.1);
    }

    /* Sidebar */
    #sidebar {
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      width: 260px;
      background: linear-gradient(180deg, #ec407a, #f8bbd0); /* ชมพูสดใสไล่สี */
      color: white;
      padding-top: 70px;
      display: flex;
      flex-direction: column;
      box-shadow: 5px 0 20px rgba(236,64,122,0.6);
      transition: transform 0.35s ease;
      border-radius: 0 20px 20px 0;
      z-index: 1050;
    }
    #sidebar.collapsed {
      transform: translateX(-260px);
      box-shadow: none !important;
    }
    #sidebar a {
      color: white;
      text-decoration: none;
      font-weight: 600;
      padding: 15px 30px;
      font-size: 1.05rem;
      display: flex;
      align-items: center;
      gap: 12px;
      transition: background-color 0.3s ease, color 0.3s ease;
      border-radius: 12px 0 0 12px;
      margin: 4px 10px;
      box-shadow: inset 0 0 0 0 transparent;
    }
    #sidebar a:hover, #sidebar a.active-menu {
      background-color: #ad1457; /* ชมพูเข้ม */
      color: #fce4ec; /* ชมพูอ่อน */
      box-shadow: inset 5px 0 10px rgba(255,255,255,0.2);
      text-decoration: none;
    }

    /* Main content */
   main {
  margin-left: 260px;
  padding: 2rem 3rem;
  height: 100vh;
  display: flex;
  flex-direction: column;
  background: #fff0f6;
  transition: margin-left 0.35s ease, padding 0.35s ease;
}

    main.expanded {
      margin-left: 0 !important;
      padding-left: 2rem !important;
      padding-right: 2rem !important;
    }

    /* Header top */
    .header-top {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      gap: 20px;
      margin-bottom: 2.5rem;
      color: #ad1457;
    }
    .header-top .user-info {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 700;
      font-size: 1.1rem;
    }
    .header-top .user-info i {
      font-size: 1.6rem;
      color: #880e4f;
    }
    .header-top .btn-logout {
      padding: 8px 22px;
      font-weight: 600;
      border-radius: 30px;
      color: #ad1457;
      border: 2px solid #ad1457;
      background: transparent;
      transition: all 0.3s ease;
    }
    .header-top .btn-logout:hover {
      background: #ad1457;
      color: white;
      text-decoration: none;
    }

    iframe[name="contentFrame"] {
  flex: 1;
  width: 100%;
  border: none;
  border-radius: 20px;
  box-shadow: 0 10px 25px rgba(173,20,87,0.15);
  background: white;
}


    /* Responsive */
    @media (max-width: 992px) {
      .menu-toggle {
        display: block;
      }
      #sidebar {
        border-radius: 0;
      }
      #sidebar.collapsed {
        transform: translateX(-260px);
      }
      main {
        margin-left: 0 !important;
        padding: 2rem 1.5rem !important;
      }
      main.expanded {
        padding-left: 1.5rem !important;
        padding-right: 1.5rem !important;
      }
      .sidebar-header {
  background-color: #ffe6f0;
  border-bottom: 1px solid #ffd6eb;
}
    }
  </style>
</head>
<body>
  <button id="menuToggle" title="เปิด/ปิดเมนู" aria-label="Toggle menu" aria-expanded="true" aria-controls="sidebar" class="menu-toggle">
    <i class="bi bi-list"></i>
  </button>

 <nav id="sidebar" aria-label="Sidebar menu">
  <!-- หัวข้อมูลด้านบน -->
  <div class="sidebar-header text-center py-3 px-3">
    <h3 class="mb-0 text-white">ระบบงานพัสดุ</h3>
    <small class="text-light">
      ยินดีต้อนรับ,
      <?= htmlspecialchars($_SESSION['UserName'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>
    </small>
    <hr style="border-top: 1px solid #ff99cc;">
  </div>

  <?php if (hasPermission('DOC_MANAGE')): ?>
    <a href="admin_docs_manage_ajax.php" target="contentFrame">
      <i class="bi bi-folder-fill"></i> จัดการเอกสาร
    </a>
  <?php endif; ?>

  <?php if (hasPermission('DOC_UPDATE')): ?>
    <a href="admin_update_document.php" target="contentFrame">
      <i class="bi bi-arrow-repeat"></i> อัปเดตสถานะเอกสาร
    </a>
  <?php endif; ?>

  <?php if (hasPermission('DOC_STATUS')): ?>
    <a href="admin_status_document.php" target="contentFrame">
      <i class="bi bi-activity"></i> สถานะเอกสาร
    </a>
  <?php endif; ?>

  <?php if (hasPermission('SUPPLIES_STATUS')): ?>
    <a href="admin_supplies.php" target="contentFrame">
      <i class="bi bi-box-arrow-up"></i> เบิกพัสดุ
    </a>
  <?php endif; ?>

  <?php if (hasPermission('STOCK_STATUS')): ?>
    <a href="admin_stock.php" target="contentFrame">
      <i class="bi bi-box-arrow-in-down"></i> รับพัสดุ
    </a>
  <?php endif; ?>

    <?php if (hasPermission('EDIT_STOCK')): ?>
    <a href="admin_edit_stock.php" target="contentFrame">
      <i class="bi bi-box-arrow-in-down"></i> จัดการสินค้า
    </a>
  <?php endif; ?>

  <?php if (hasPermission('BUDGET')): ?>
    <a href="admin_budget_manage.php" target="contentFrame" class="active-menu">
      <i class="bi bi-cash-coin"></i> จัดการปีงบประมาณ
    </a>
  <?php endif; ?>

  <?php if (hasPermission('WORKGROUP')): ?>
    <a href="admin_workgroup_manage.php" target="contentFrame">
      <i class="bi bi-diagram-3-fill"></i> จัดการกลุ่มงาน
    </a>
  <?php endif; ?>

  <?php if (hasPermission('REPORT')): ?>
    <a href="admin_report.php" target="contentFrame">
      <i class="bi bi-bar-chart-fill"></i> รายงานบันทึกใหม่
    </a>
  <?php endif; ?>

  <?php if (hasPermission('REPORT')): ?>
  <a href="admin_report_dashboard.php" target="contentFrame">
    <i class="bi bi-graph-up-arrow"></i> รายงาน
  </a>
<?php endif; ?>

  <?php if (hasPermission('USER_MANAGE')): ?>
    <a href="admin_manage_users.php" target="contentFrame">
      <i class="bi bi-people-fill"></i> จัดการผู้ใช้
    </a>
  <?php endif; ?>
</nav>



  <main id="mainContent">
    <div class="header-top">
      <div class="user-info">
        <i class="bi bi-person-circle"></i>
        <span>
  <?php echo htmlspecialchars($user["Name"]); ?> 
  (<?php echo htmlspecialchars($user["Position"] ?? 'ไม่ระบุ'); ?>)
</span>
      </div>
      <a href="logout.php" class="btn btn-outline-danger btn-logout">ออกจากระบบ</a>
    </div>

    <iframe name="contentFrame"></iframe>

    <footer class="text-center py-3 border-top mt-4">
    <small class="text-muted">
        © 2026 งานพัสดุ | ระบบติดตามและเบิกจ่ายงานพัสดุ v2.0
    </small>
</footer>
  </main>

  <script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');

    menuToggle.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      mainContent.classList.toggle('expanded');

      const isCollapsed = sidebar.classList.contains('collapsed');
      menuToggle.setAttribute('aria-expanded', !isCollapsed);
    });

    const links = document.querySelectorAll('#sidebar a');
    links.forEach(link => {
      link.addEventListener('click', () => {
        links.forEach(l => l.classList.remove('active-menu'));
        link.classList.add('active-menu');
      });
    });
  </script>
</body>
</html>


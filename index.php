<?php
require_once "security.php";
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>ระบบงานพัสดุ</title>
  <?php require_once "layout_head.php"; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">

  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: 'Sarabun', sans-serif;
      background: linear-gradient(to bottom right, #f8bbd0, #ffffff);
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .login-container {
      background: #fff;
      padding: 40px 30px;
      border-radius: 16px;
      box-shadow: 0 12px 40px rgba(236, 64, 122, 0.2);
      width: 100%;
      max-width: 400px;
    }

    .logo {
      display: flex;
      justify-content: center;
      margin-bottom: 20px;
    }

    .logo img {
      width: 150px;
      height: 150px;
      object-fit: cover;
      border-radius: 100%;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .login-container h2 {
      text-align: center;
      margin-bottom: 24px;
      color: #ad1457;
      font-weight: 600;
    }

    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin-bottom: 6px;
      font-weight: bold;
      color: #880e4f;
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 12px;
      border: 2px solid #f8bbd0;
      border-radius: 12px;
      font-size: 16px;
      transition: border-color 0.3s ease, box-shadow 0.3s;
    }

    input:focus {
      border-color: #ec407a;
      box-shadow: 0 0 6px rgba(236, 64, 122, 0.3);
      outline: none;
    }

    .btn-submit {
      width: 100%;
      padding: 12px;
      background: linear-gradient(to right, #ec407a, #f8bbd0);
      color: white;
      font-size: 16px;
      font-weight: bold;
      border: 2px solid #ec407a;
      border-radius: 30px;
      cursor: pointer;
      transition: 0.3s;
      box-shadow: 0 6px 14px rgba(236, 64, 122, 0.2);
    }

    .btn-submit:hover {
      background: linear-gradient(to right, #d81b60, #f06292);
      box-shadow: 0 8px 18px rgba(194, 24, 91, 0.3);
    }

    .forgot-password,
    .forgot-register {
      margin-top: 15px;
      font-size: 14px;
    }

    .forgot-register {
      text-align: left;
    }

    .forgot-password {
      text-align: right;
    }

    .forgot-password a,
    .forgot-register a {
      color: #ad1457;
      text-decoration: none;
      transition: color 0.3s ease;
    }

    .forgot-password a:hover,
    .forgot-register a:hover {
      text-decoration: underline;
      color: #d81b60;
    }

    @media (max-width: 500px) {
      .login-container {
        padding: 30px 20px;
      }
      .logo {
  max-width: 220px;
  margin: 1.5rem auto; /* อยู่กลาง */
  padding: 1rem;
  background: #fff0f6; /* พื้นหลังชมพูอ่อน */
  border: 3px solid #f48fb1; /* ขอบชมพู */
  border-radius: 1rem;
  box-shadow: 0 6px 15px rgba(244, 143, 177, 0.35);
  text-align: center;
  transition: box-shadow 0.3s ease;
}

.logo:hover {
  box-shadow: 0 10px 30px rgba(244, 143, 177, 0.6);
}

.logo img {
  max-width: 100%;
  height: auto;
  filter: drop-shadow(0 4px 6px rgba(244, 143, 177, 0.3));
  transition: transform 0.3s ease;
}

.logo img:hover {
  transform: scale(1.05);
}

    }
  </style>
  
</head>
<body>
  <div class="login-container">
    <div class="logo">
      <img src="images/hspm.png" alt="My Logo">
    </div>

    <h2>ระบบงานพัสดุ</h2>

<div style="display:flex; justify-content:center; margin-bottom:15px;">
  <div style="width:100%; max-width:350px; text-align:center;">
    
    <?php if (isset($_GET['error'])): ?>
  <div class="alert alert-danger" role="alert">
    ❌ ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง! <br>

    <?php if (isset($_GET['attempts'], $_GET['remain'])): ?>
      คุณใส่ผิดไปแล้ว 
      <strong><?= (int)$_GET['attempts'] ?></strong> ครั้ง <br>
      เหลืออีก 
      <strong><?= (int)$_GET['remain'] ?></strong> ครั้ง ก่อนถูกล็อก
    <?php endif; ?>
  </div>
<?php endif; ?>

    <?php if (isset($_GET['timeout'])): ?>
      <div class="alert alert-warning">
        ⏳ ไม่มีการใช้งานเกิน 15 นาที ระบบได้ออกจากระบบอัตโนมัติ
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['locked'])): ?>
<div class="alert alert-danger">
    🔒 บัญชีถูกล็อกชั่วคราว 3 นาที (กรอกรหัสผิดเกิน 5 ครั้ง)
</div>
<?php endif; ?>
    <?php if (isset($_GET['logout'])): ?>
      <div class="alert alert-success">
        ✅ ออกจากระบบเรียบร้อยแล้ว
      </div>
    <?php endif; ?>

  </div>
</div>



<form name="form1" method="post" action="check_login.php">

  <!-- CSRF TOKEN (มองไม่เห็น ไม่กระทบ CSS) -->
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

  <div class="form-group">
    <label for="txtUsername">ชื่อผู้ใช้งาน</label>
    <input type="text" name="txtUsername" id="txtUsername" required>
  </div>

  <div class="form-group">
    <label for="txtPassword">รหัสผ่าน</label>
    <input type="password" name="txtPassword" id="txtPassword" required>
  </div>

  <button type="submit" class="btn-submit">เข้าใช้งาน</button>
</form>

    <div class="forgot-register">
      <a href="register.php">สมัครสมาชิก</a>
    </div>
  </div>

</body>
</html>


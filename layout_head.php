<?php
// layout_head.php
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
* {
  box-sizing: border-box;
}

body {
  margin: 0;
  padding: 20px;

  /* ใช้ฟอนต์ระบบแทน */
  font-family: 
    -apple-system,
    BlinkMacSystemFont,
    "Segoe UI",
    Tahoma,
    sans-serif;

  min-height: 100dvh; /* รองรับ iOS ใหม่ */
}

/* Container กลาง */
.container {
  width: 100%;
  max-width: 1200px;
  margin: auto;
}

/* Tablet */
@media (max-width: 1024px) {
  body {
    padding: 15px;
  }

  .container {
    max-width: 95%;
  }
}

/* Mobile */
@media (max-width: 600px) {
  body {
    padding: 12px;
  }

  .container {
    max-width: 100%;
  }

  input,
  button {
    font-size: 16px; /* ป้องกัน zoom iPhone */
  }
}
</style>
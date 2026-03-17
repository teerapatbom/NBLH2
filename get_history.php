<?php
declare(strict_types=1);

require_once "security.php";
require_once "connect.php";

requireLogin();

if (!isset($_GET['doc_id'])) {
    echo "<div class='text-danger text-center'>ไม่พบรหัสเอกสาร</div>";
    exit;
}

$docId = (int)$_GET['doc_id'];

/* =========================
   Helper: Thai datetime
========================= */
function formatThaiDateTime(?string $datetime): string {
    if (!$datetime) return '-';
    $dt = new DateTime($datetime);
    $dt->modify('+543 years');
    return $dt->format('d/m/Y H:i:s');
}

/* =========================
   Fetch history
========================= */
$stmt = $conn->prepare("
    SELECT 
        dh.StatusID,
        dh.Name,
        dh.Position,
        dh.CreatedAt,
        dh.Remark,
        s.StatusName
    FROM DocumentHistory dh
    LEFT JOIN StatusTypes s 
        ON dh.StatusID = s.StatusID
    WHERE dh.DocID = ?
    ORDER BY dh.CreatedAt ASC
");
$stmt->execute([$docId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "<div class='text-muted text-center'>ยังไม่มีประวัติเอกสาร</div>";
    exit;
}
?>

<ul class="timeline">
<?php foreach ($rows as $row): ?>
    <li class="timeline-item">
        <div class="timeline-card">

            <div class="timeline-header">
                <span class="badge bg-primary">
                    <?= htmlspecialchars($row['StatusName'] ?? 'ไม่ระบุสถานะ') ?>
                </span>
                <span class="timeline-date">
                    <?= formatThaiDateTime($row['CreatedAt']) ?>
                </span>
            </div>

            <div class="timeline-body">
                <div class="timeline-user">
                    👤 <?= htmlspecialchars($row['Name'] ?? '-') ?>
                    <small class="text-muted">
                        (<?= htmlspecialchars($row['Position'] ?? '-') ?>)
                    </small>
                </div>

                <?php if (!empty($row['Remark'])): ?>
                    <div class="timeline-remark text-danger mt-1">
                        หมายเหตุ: <?= htmlspecialchars($row['Remark']) ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </li>
<?php endforeach; ?>
</ul>

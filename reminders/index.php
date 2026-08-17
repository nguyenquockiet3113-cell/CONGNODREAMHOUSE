<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if (isset($_POST['toggle_id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $tid = (int)$_POST['toggle_id'];
    $pdo->prepare('UPDATE reminders SET is_done = 1 - is_done WHERE id = ?')->execute([$tid]);
    redirect('/reminders/index.php');
}

$showDone = isset($_GET['done']) && $_GET['done'] === '1';

$stmt = $pdo->prepare('SELECT * FROM reminders WHERE is_done = ? ORDER BY due_date ASC');
$stmt->execute([$showDone ? 1 : 0]);
$reminders = $stmt->fetchAll();

$pageTitle = 'Nhắc nhở';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-bell"></i> Nhắc nhở</h4>
  <a href="<?= url('/reminders/form.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm nhắc nhở</a>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?= !$showDone ? 'active' : '' ?>" href="<?= url('/reminders/index.php') ?>">Đang chờ</a></li>
  <li class="nav-item"><a class="nav-link <?= $showDone ? 'active' : '' ?>" href="<?= url('/reminders/index.php?done=1') ?>">Đã hoàn thành</a></li>
</ul>

<div class="card">
  <div class="list-group list-group-flush">
    <?php if (!$reminders): ?>
      <div class="list-group-item text-muted text-center py-4">Không có nhắc nhở nào.</div>
    <?php endif; ?>
    <?php $today = date('Y-m-d'); ?>
    <?php foreach ($reminders as $r): ?>
      <?php $overdue = !$showDone && $r['due_date'] < $today; ?>
      <div class="list-group-item d-flex justify-content-between align-items-center <?= $overdue ? 'bg-danger-subtle' : '' ?>">
        <div>
          <div class="fw-semibold"><?= e($r['title']) ?></div>
          <div class="small text-muted">Hạn: <?= vndate($r['due_date']) ?><?= $overdue ? ' · <span class="text-danger">Quá hạn</span>' : '' ?></div>
          <?php if ($r['note']): ?><div class="small mt-1"><?= nl2br(e($r['note'])) ?></div><?php endif; ?>
        </div>
        <div class="d-flex gap-2">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="toggle_id" value="<?= $r['id'] ?>">
            <button class="btn btn-sm btn-outline-<?= $showDone ? 'secondary' : 'success' ?>">
              <i class="bi bi-<?= $showDone ? 'arrow-counterclockwise' : 'check-lg' ?>"></i> <?= $showDone ? 'Bỏ đánh dấu' : 'Hoàn thành' ?>
            </button>
          </form>
          <a href="<?= url('/reminders/form.php?id=' . $r['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
          <form method="post" action="<?= url('/reminders/delete.php') ?>" data-confirm="Xóa nhắc nhở này?">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

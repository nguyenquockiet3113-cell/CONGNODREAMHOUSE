<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$ticket = [
    'id' => 0, 'room_id' => '', 'title' => '', 'description' => '',
    'priority' => 'normal', 'status' => 'open', 'reported_by' => '', 'resolution_note' => '',
];
$errors = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Không tìm thấy ticket.');
        redirect('/tickets/index.php');
    }
    $ticket = $found;
}

$rooms = $pdo->query('SELECT * FROM rooms ORDER BY zone, room_code')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ticket['room_id'] = (int)($_POST['room_id'] ?? 0);
    $ticket['title'] = trim($_POST['title'] ?? '');
    $ticket['description'] = trim($_POST['description'] ?? '');
    $ticket['priority'] = $_POST['priority'] ?? 'normal';
    $ticket['status'] = $_POST['status'] ?? 'open';
    $ticket['reported_by'] = trim($_POST['reported_by'] ?? '');
    $ticket['resolution_note'] = trim($_POST['resolution_note'] ?? '');

    if (!$ticket['room_id']) $errors[] = 'Vui lòng chọn phòng.';
    if ($ticket['title'] === '') $errors[] = 'Vui lòng nhập tiêu đề.';
    if (!array_key_exists($ticket['priority'], TICKET_PRIORITY_LABELS)) $errors[] = 'Mức độ không hợp lệ.';
    if (!array_key_exists($ticket['status'], TICKET_STATUS_LABELS)) $errors[] = 'Trạng thái không hợp lệ.';

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        $resolvedAt = null;
        if ($id) {
            $prevStmt = $pdo->prepare('SELECT status, resolved_at FROM tickets WHERE id = ?');
            $prevStmt->execute([$id]);
            $prev = $prevStmt->fetch();
            $resolvedAt = $prev['resolved_at'];
        }
        if ($ticket['status'] === 'resolved' && !$resolvedAt) {
            $resolvedAt = $now;
        } elseif ($ticket['status'] !== 'resolved') {
            $resolvedAt = null;
        }

        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE tickets SET room_id=?, title=?, description=?, priority=?, status=?, reported_by=?, resolution_note=?, resolved_at=? WHERE id=?'
            );
            $stmt->execute([
                $ticket['room_id'], $ticket['title'], $ticket['description'], $ticket['priority'],
                $ticket['status'], $ticket['reported_by'], $ticket['resolution_note'], $resolvedAt, $id,
            ]);
            flash('success', 'Đã cập nhật ticket.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO tickets (room_id, title, description, priority, status, reported_by, resolution_note, resolved_at, created_at) VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $ticket['room_id'], $ticket['title'], $ticket['description'], $ticket['priority'],
                $ticket['status'], $ticket['reported_by'], $ticket['resolution_note'], $resolvedAt, $now,
            ]);
            flash('success', 'Đã tạo ticket mới.');
        }
        redirect('/tickets/index.php');
    }
}

$pageTitle = $id ? 'Sửa ticket' : 'Tạo ticket';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-tools"></i> <?= $id ? 'Sửa ticket' : 'Tạo ticket bảo trì' ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Phòng *</label>
          <select name="room_id" class="form-select" required>
            <option value="">-- Chọn phòng --</option>
            <?php foreach ($rooms as $r): ?>
              <option value="<?= $r['id'] ?>" <?= (int)$ticket['room_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                <?= e($r['room_code']) ?><?= $r['zone'] ? ' - ' . e($r['zone']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Tiêu đề *</label>
          <input type="text" name="title" class="form-control" required value="<?= e($ticket['title']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Người báo</label>
          <input type="text" name="reported_by" class="form-control" placeholder="Khách thuê / nhân viên" value="<?= e($ticket['reported_by']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Mức độ</label>
          <select name="priority" class="form-select">
            <?php foreach (TICKET_PRIORITY_LABELS as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= $ticket['priority'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Trạng thái</label>
          <select name="status" class="form-select">
            <?php foreach (TICKET_STATUS_LABELS as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= $ticket['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Mô tả sự cố</label>
          <textarea name="description" class="form-control" rows="3"><?= e($ticket['description']) ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Ghi chú xử lý</label>
          <textarea name="resolution_note" class="form-control" rows="2"><?= e($ticket['resolution_note']) ?></textarea>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu</button>
        <a href="<?= url('/tickets/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

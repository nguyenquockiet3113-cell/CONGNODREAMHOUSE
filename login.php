<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['user'])) {
    redirect('/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'role' => $user['role'],
            'permissions' => $user['permissions'] ?? '',
        ];
        redirect('/dashboard.php');
    }

    $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đăng nhập - <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= url('/assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
<div class="login-wrapper">
  <div class="login-card">
    <div class="text-center mb-4">
      <i class="bi bi-building" style="font-size:2.2rem;color:var(--brand);"></i>
      <h4 class="mt-2 mb-0"><?= APP_NAME ?></h4>
      <div class="text-muted small">Đăng nhập để tiếp tục</div>
    </div>
    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Tên đăng nhập</label>
        <input type="text" name="username" class="form-control" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Mật khẩu</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn w-100 text-white" style="background:var(--brand);">Đăng nhập</button>
    </form>
  </div>
</div>
</body>
</html>

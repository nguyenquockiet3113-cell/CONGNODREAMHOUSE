<?php
require_login();
$currentUser = current_user();
$flashes = get_flashes();
$path = $_SERVER['SCRIPT_NAME'];
function nav_active(string $needle, string $path): string
{
    return str_contains($path, $needle) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= url('/assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
<div class="app-wrapper">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <i class="bi bi-building"></i>
      <span><?= APP_NAME ?></span>
    </div>
    <nav class="sidebar-nav">
      <a href="<?= url('/dashboard.php') ?>" class="<?= nav_active('dashboard.php', $path) ?>"><i class="bi bi-speedometer2"></i> Tổng quan</a>

      <?php if (has_permission('rooms') || has_permission('contracts')): ?>
      <div class="sidebar-section">Vận hành</div>
      <?php if (has_permission('rooms')): ?>
      <a href="<?= url('/rooms/index.php') ?>" class="<?= nav_active('/rooms/', $path) ?>"><i class="bi bi-door-closed"></i> Khu &amp; Phòng</a>
      <?php endif; ?>
      <?php if (has_permission('contracts')): ?>
      <a href="<?= url('/contracts/index.php') ?>" class="<?= nav_active('/contracts/', $path) ?>"><i class="bi bi-file-earmark-text"></i> Hợp đồng</a>
      <?php endif; ?>
      <?php endif; ?>

      <?php if (has_permission('deals') || has_permission('billing') || has_permission('expenses') || has_permission('cleaning') || has_permission('funds') || has_permission('reconciliation') || has_permission('reports')): ?>
      <div class="sidebar-section">Tài chính</div>
      <?php if (has_permission('deals')): ?>
      <a href="<?= url('/deals/short.php') ?>" class="<?= nav_active('/deals/short', $path) ?>"><i class="bi bi-calendar-check"></i> Doanh thu ngắn hạn</a>
      <a href="<?= url('/deals/long.php') ?>" class="<?= nav_active('/deals/long', $path) ?>"><i class="bi bi-receipt"></i> Doanh thu dài hạn</a>
      <?php endif; ?>
      <?php if (has_permission('billing')): ?>
      <a href="<?= url('/billing/index.php') ?>" class="<?= nav_active('/billing/', $path) ?>"><i class="bi bi-journal-text"></i> Chi phí khác</a>
      <?php endif; ?>
      <?php if (has_permission('expenses')): ?>
      <a href="<?= url('/expenses/index.php') ?>" class="<?= nav_active('/expenses/', $path) ?>"><i class="bi bi-cash-coin"></i> Chi phí</a>
      <?php endif; ?>
      <?php if (has_permission('cleaning')): ?>
      <a href="<?= url('/cleaning/index.php') ?>" class="<?= nav_active('/cleaning/', $path) ?>"><i class="bi bi-bucket"></i> Tiền lương vệ sinh</a>
      <?php endif; ?>
      <?php if (has_permission('funds')): ?>
      <a href="<?= url('/funds/index.php') ?>" class="<?= nav_active('/funds/', $path) ?>"><i class="bi bi-journal-text"></i> Sổ quỹ</a>
      <?php endif; ?>
      <?php if (has_permission('reconciliation')): ?>
      <a href="<?= url('/reconciliation/index.php') ?>" class="<?= nav_active('/reconciliation/', $path) ?>"><i class="bi bi-bank"></i> Đối soát ngân hàng</a>
      <?php endif; ?>
      <?php if (has_permission('reports')): ?>
      <a href="<?= url('/reports/index.php') ?>" class="<?= nav_active('/reports/', $path) ?>"><i class="bi bi-graph-up"></i> Báo cáo</a>
      <?php endif; ?>
      <?php endif; ?>

      <div class="sidebar-section">Hệ thống</div>
      <?php if (has_permission('reminders')): ?>
      <a href="<?= url('/reminders/index.php') ?>" class="<?= nav_active('/reminders/', $path) ?>"><i class="bi bi-bell"></i> Nhắc nhở</a>
      <?php endif; ?>
      <?php if (is_admin()): ?>
      <a href="<?= url('/users/index.php') ?>" class="<?= nav_active('/users/', $path) ?>"><i class="bi bi-person-gear"></i> Tài khoản</a>
      <a href="<?= url('/settings/index.php') ?>" class="<?= nav_active('/settings/', $path) ?>"><i class="bi bi-gear"></i> Cài đặt</a>
      <?php endif; ?>
    </nav>
  </aside>

  <div class="main-col">
    <header class="topbar">
      <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
      <div class="ms-auto d-flex align-items-center gap-3">
        <span class="text-muted small"><i class="bi bi-person-circle"></i> <?= e($currentUser['full_name']) ?> <span class="badge bg-light text-dark border"><?= $currentUser['role'] === 'admin' ? 'Quản trị' : 'Nhân viên' ?></span></span>
        <a href="<?= url('/logout.php') ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
      </div>
    </header>

    <main class="content">
      <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
          <?= e($f['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endforeach; ?>

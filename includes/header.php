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
<script>
if (localStorage.getItem('sidebarCollapsed') === '1') {
  document.documentElement.classList.add('sidebar-collapsed');
}
</script>
</head>
<body>
<div class="app-wrapper">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <button type="button" class="sidebar-hamburger-btn d-none d-lg-inline-flex" id="sidebarCollapseToggle" title="Thu gọn/mở rộng menu"><i class="bi bi-list"></i></button>
      <i class="bi bi-building"></i>
      <span class="nav-label"><?= APP_NAME ?></span>
    </div>
    <nav class="sidebar-nav">
      <a href="<?= url('/dashboard.php') ?>" class="<?= nav_active('dashboard.php', $path) ?>" title="Tổng quan"><i class="bi bi-speedometer2"></i> <span class="nav-label">Tổng quan</span></a>

      <?php if (has_permission('rooms') || has_permission('contracts')): ?>
      <div class="sidebar-section nav-label">Vận hành</div>
      <?php if (has_permission('rooms')): ?>
      <a href="<?= url('/rooms/index.php') ?>" class="<?= nav_active('/rooms/', $path) ?>" title="Khu &amp; Phòng"><i class="bi bi-door-closed"></i> <span class="nav-label">Khu &amp; Phòng</span></a>
      <?php endif; ?>
      <?php if (has_permission('contracts')): ?>
      <a href="<?= url('/contracts/index.php') ?>" class="<?= nav_active('/contracts/', $path) ?>" title="Hợp đồng"><i class="bi bi-file-earmark-text"></i> <span class="nav-label">Hợp đồng</span></a>
      <?php endif; ?>
      <?php endif; ?>

      <?php if (has_permission('deals') || has_permission('debts') || has_permission('billing') || has_permission('expenses') || has_permission('cleaning') || has_permission('funds') || has_permission('reconciliation') || has_permission('reports')): ?>
      <div class="sidebar-section nav-label">Tài chính</div>
      <?php if (has_permission('deals')): ?>
      <a href="<?= url('/deals/short.php') ?>" class="<?= nav_active('/deals/short', $path) ?>" title="Doanh thu ngắn hạn"><i class="bi bi-calendar-check"></i> <span class="nav-label">Doanh thu ngắn hạn</span></a>
      <a href="<?= url('/deals/long.php') ?>" class="<?= nav_active('/deals/long', $path) ?>" title="Doanh thu dài hạn"><i class="bi bi-receipt"></i> <span class="nav-label">Doanh thu dài hạn</span></a>
      <?php endif; ?>
      <?php if (has_permission('debts')): ?>
      <a href="<?= url('/debts/index.php') ?>" class="<?= nav_active('/debts/', $path) ?>" title="Công nợ tổng hợp"><i class="bi bi-exclamation-diamond"></i> <span class="nav-label">Công nợ tổng hợp</span></a>
      <?php endif; ?>
      <?php if (has_permission('billing')): ?>
      <a href="<?= url('/billing/index.php') ?>" class="<?= nav_active('/billing/', $path) ?>" title="Chi phí khác"><i class="bi bi-journal-text"></i> <span class="nav-label">Chi phí khác</span></a>
      <?php endif; ?>
      <?php if (has_permission('expenses')): ?>
      <a href="<?= url('/expenses/index.php') ?>" class="<?= nav_active('/expenses/', $path) ?>" title="Chi phí"><i class="bi bi-cash-coin"></i> <span class="nav-label">Chi phí</span></a>
      <?php endif; ?>
      <?php if (has_permission('cleaning')): ?>
      <a href="<?= url('/cleaning/index.php') ?>" class="<?= nav_active('/cleaning/', $path) ?>" title="Tiền lương vệ sinh"><i class="bi bi-bucket"></i> <span class="nav-label">Tiền lương vệ sinh</span></a>
      <?php endif; ?>
      <?php if (has_permission('funds')): ?>
      <a href="<?= url('/funds/index.php') ?>" class="<?= nav_active('/funds/', $path) ?>" title="Sổ quỹ"><i class="bi bi-journal-text"></i> <span class="nav-label">Sổ quỹ</span></a>
      <?php endif; ?>
      <?php if (has_permission('reconciliation')): ?>
      <a href="<?= url('/reconciliation/index.php') ?>" class="<?= nav_active('/reconciliation/', $path) ?>" title="Đối soát ngân hàng"><i class="bi bi-bank"></i> <span class="nav-label">Đối soát ngân hàng</span></a>
      <?php endif; ?>
      <?php if (has_permission('reports')): ?>
      <a href="<?= url('/reports/index.php') ?>" class="<?= nav_active('/reports/', $path) ?>" title="Báo cáo"><i class="bi bi-graph-up"></i> <span class="nav-label">Báo cáo</span></a>
      <?php endif; ?>
      <?php endif; ?>

      <div class="sidebar-section nav-label">Hệ thống</div>
      <?php if (has_permission('reminders')): ?>
      <a href="<?= url('/reminders/index.php') ?>" class="<?= nav_active('/reminders/', $path) ?>" title="Nhắc nhở"><i class="bi bi-bell"></i> <span class="nav-label">Nhắc nhở</span></a>
      <?php endif; ?>
      <?php if (is_admin()): ?>
      <a href="<?= url('/users/index.php') ?>" class="<?= nav_active('/users/', $path) ?>" title="Tài khoản"><i class="bi bi-person-gear"></i> <span class="nav-label">Tài khoản</span></a>
      <a href="<?= url('/settings/index.php') ?>" class="<?= nav_active('/settings/', $path) ?>" title="Cài đặt"><i class="bi bi-gear"></i> <span class="nav-label">Cài đặt</span></a>
      <?php endif; ?>
    </nav>
  </aside>

  <div class="main-col">
    <header class="topbar">
      <button class="sidebar-hamburger-btn d-lg-none" id="sidebarToggle" title="Mở/đóng menu"><i class="bi bi-list"></i></button>
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

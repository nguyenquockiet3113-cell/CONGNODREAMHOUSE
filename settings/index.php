<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/sync_helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_google'])) {
    verify_csrf();
    $json = trim($_POST['google_service_account_json'] ?? '');
    $spreadsheetId = trim($_POST['google_spreadsheet_id'] ?? '');

    if ($json !== '' && json_decode($json) === null) {
        flash('danger', 'Nội dung Service Account JSON không đúng định dạng JSON.');
    } else {
        set_setting($pdo, 'google_service_account_json', $json);
        set_setting($pdo, 'google_spreadsheet_id', $spreadsheetId);
        flash('success', 'Đã lưu cấu hình Google Sheets.');
    }
    redirect('/settings/index.php');
}

$savedJson = get_setting($pdo, 'google_service_account_json');
$savedSpreadsheetId = get_setting($pdo, 'google_spreadsheet_id');
$serviceAccountEmail = '';
if ($savedJson !== '') {
    $decoded = json_decode($savedJson, true);
    $serviceAccountEmail = $decoded['client_email'] ?? '';
}

$pageTitle = 'Cài đặt';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-gear"></i> Cài đặt - Đồng bộ Google Sheets</h4>

<div class="card mb-3">
  <div class="card-header">1. Kết nối Google Sheets (Service Account)</div>
  <div class="card-body">
    <div class="alert alert-info small">
      <strong>Các bước chuẩn bị (thực hiện trên tài khoản Google của bạn):</strong>
      <ol class="mb-0 mt-1">
        <li>Vào <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>, tạo một Project mới (hoặc dùng project có sẵn).</li>
        <li>Vào <strong>APIs &amp; Services → Library</strong>, tìm và bật <strong>Google Sheets API</strong>.</li>
        <li>Vào <strong>APIs &amp; Services → Credentials → Create Credentials → Service Account</strong>, tạo service account mới.</li>
        <li>Mở service account vừa tạo → tab <strong>Keys → Add Key → Create new key → JSON</strong>, tải file JSON về.</li>
        <li>Mở file Google Sheet bạn muốn đồng bộ, bấm <strong>Share</strong>, thêm email của service account (dạng <code>...@...iam.gserviceaccount.com</code>) với quyền <strong>Editor</strong>.</li>
        <li>Copy toàn bộ nội dung file JSON và ID của Google Sheet (nằm trong URL, giữa <code>/d/</code> và <code>/edit</code>) rồi dán vào form bên dưới.</li>
      </ol>
    </div>

    <?php if ($serviceAccountEmail): ?>
      <div class="alert alert-success small">
        Đã cấu hình. Service account đang dùng: <strong><?= e($serviceAccountEmail) ?></strong> — nhớ đảm bảo email này đã được share quyền Editor vào Google Sheet.
      </div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="save_google" value="1">
      <div class="mb-3">
        <label class="form-label">Nội dung Service Account JSON</label>
        <textarea name="google_service_account_json" class="form-control" rows="6" placeholder='{"type": "service_account", "client_email": "...", "private_key": "...", ...}'><?= e($savedJson) ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Spreadsheet ID</label>
        <input type="text" name="google_spreadsheet_id" class="form-control" placeholder="VD: 1AbCDefGhIjKLmnoPQRsTUvWxyz1234567890" value="<?= e($savedSpreadsheetId) ?>">
      </div>
      <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu cấu hình</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">2. Đồng bộ dữ liệu</div>
  <div class="card-body">
    <?php if (!$serviceAccountEmail || !$savedSpreadsheetId): ?>
      <div class="text-muted small">Hãy lưu cấu hình Service Account + Spreadsheet ID ở trên trước khi đồng bộ.</div>
    <?php else: ?>
      <div class="d-flex gap-2 mb-3">
        <form method="post" action="<?= url('/settings/sync.php') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="push_all">
          <button class="btn btn-primary"><i class="bi bi-cloud-upload"></i> Đẩy toàn bộ dữ liệu lên Sheet</button>
        </form>
        <form method="post" action="<?= url('/settings/sync.php') ?>" data-confirm="Kéo dữ liệu từ Sheet có thể ghi đè dữ liệu trong hệ thống. Tiếp tục?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="pull_all">
          <button class="btn btn-outline-primary"><i class="bi bi-cloud-download"></i> Kéo toàn bộ dữ liệu từ Sheet</button>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>Bảng dữ liệu</th><th></th></tr></thead>
          <tbody>
            <?php foreach (SYNC_TABLES as $t): ?>
              <tr>
                <td class="align-middle"><?= e($t) ?></td>
                <td class="text-end">
                  <form method="post" action="<?= url('/settings/sync.php') ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="push_one">
                    <input type="hidden" name="table" value="<?= e($t) ?>">
                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-cloud-upload"></i> Đẩy lên</button>
                  </form>
                  <form method="post" action="<?= url('/settings/sync.php') ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="pull_one">
                    <input type="hidden" name="table" value="<?= e($t) ?>">
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-cloud-download"></i> Kéo về</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="small text-muted">
        <strong>Lưu ý:</strong> "Đẩy lên" sẽ ghi đè toàn bộ nội dung tab tương ứng trên Sheet bằng dữ liệu hiện tại trong hệ thống.
        "Kéo về" sẽ đọc tab tương ứng và cập nhật vào hệ thống: dòng có cột <code>id</code> khớp dữ liệu sẵn có sẽ được cập nhật, dòng còn lại sẽ được thêm mới.
        Nên sao lưu dữ liệu trước khi "Kéo về" lần đầu.
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

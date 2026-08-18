<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if (!class_exists('ZipArchive')) {
    die('Máy chủ chưa bật extension "zip" của PHP, không thể tạo file Word. Vui lòng bật extension zip trong php.ini (hoặc hPanel nếu deploy trên Hostinger).');
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ?');
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) {
    flash('danger', 'Không tìm thấy hợp đồng.');
    redirect('/contracts/index.php');
}

/** Escape van ban cho XML (dung chung cho ca noi dung .docx) */
function xw(?string $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
}
function xwv(?string $v): string
{
    $v = trim((string)($v ?? ''));
    return $v !== '' ? xw($v) : '……………………';
}

/** Tao 1 run (doan van ban) voi dinh dang */
function run(string $text, array $opt = []): string
{
    $props = '';
    if (!empty($opt['b'])) $props .= '<w:b/>';
    if (!empty($opt['i'])) $props .= '<w:i/>';
    $props .= '<w:sz w:val="' . ($opt['sz'] ?? 22) . '"/><w:szCs w:val="' . ($opt['sz'] ?? 22) . '"/>';
    $props .= '<w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/>';
    $lines = explode("\n", $text);
    $t = '';
    foreach ($lines as $i => $line) {
        if ($i > 0) $t .= '<w:br/>';
        $t .= '<w:t xml:space="preserve">' . xw($line) . '</w:t>';
    }
    return '<w:r><w:rPr>' . $props . '</w:rPr>' . $t . '</w:r>';
}

/** Tao 1 doan (paragraph) tu 1 hoac nhieu run() da dung san */
function para(string $runsXml, array $opt = []): string
{
    $jc = $opt['align'] ?? 'left'; // left | center | right | both
    $spacingAfter = $opt['after'] ?? 120;
    $pPr = '<w:jc w:val="' . $jc . '"/><w:spacing w:after="' . $spacingAfter . '" w:line="276" w:lineRule="auto"/>';
    return '<w:p><w:pPr>' . $pPr . '</w:pPr>' . $runsXml . '</w:p>';
}

$today = date('Y-m-d');
$signDay = date('j', strtotime($c['created_at'] ?? 'now'));
$signMonth = date('n', strtotime($c['created_at'] ?? 'now'));
$signYear = date('Y', strtotime($c['created_at'] ?? 'now'));

// Logo (neu co file assets/img/logo.png)
$logoPath = __DIR__ . '/../assets/img/logo.png';
$logoXml = '';
$hasLogo = file_exists($logoPath);
if ($hasLogo) {
    [$logoW, $logoH] = getimagesize($logoPath);
    $cy = 609600; // ~0.667 inch, EMU
    $cx = (int)round($cy * ($logoW / $logoH));
    $logoXml = '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:drawing>'
        . '<wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
        . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
        . '<wp:docPr id="1" name="Logo"/>'
        . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
        . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:nvPicPr><pic:cNvPr id="0" name="logo.png"/><pic:cNvPicPr/></pic:nvPicPr>'
        . '<pic:blipFill><a:blip r:embed="rIdLogo"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
        . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
        . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
}

$body = '';
$body .= $logoXml;
$body .= para(run('HỢP ĐỒNG THUÊ CĂN HỘ', ['b' => true, 'sz' => 30]), ['align' => 'center', 'after' => 40]);
$body .= para(run('APARTMENT LEASE AGREEMENT', ['i' => true, 'sz' => 22]), ['align' => 'center', 'after' => 200]);
$body .= para(run('CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM', ['b' => true]), ['align' => 'center', 'after' => 20]);
$body .= para(run('Độc Lập – Tự Do – Hạnh Phúc', ['b' => true]), ['align' => 'center', 'after' => 20]);
$body .= para(run('The Socialist Republic of Vietnam — Independence – Freedom – Happiness', ['i' => true]), ['align' => 'center', 'after' => 200]);

$body .= para(run('Số/No: ') . run(xwv($c['contract_code']), ['b' => true]));
$body .= para(run('Thành phố Hồ Chí Minh, ngày ' . $signDay . ' tháng ' . $signMonth . ' năm ' . $signYear . "\n" . 'Ho Chi Minh City, ' . $signDay . '/' . $signMonth . '/' . $signYear, ['i' => true]), ['after' => 200]);

$body .= para(run('GIỮA / BETWEEN', ['i' => true]));

$body .= para(run('BÊN CHO THUÊ / THE LESSOR:', ['b' => true]), ['after' => 60]);
$body .= para(run('Tên/Name: ') . run(xwv($c['lessor_name']), ['b' => true]) . run('        Ngày sinh: ' . ($c['lessor_dob'] ? vndate($c['lessor_dob']) : '……………')));
$body .= para(run('Hộ chiếu/CCCD số: ' . xwv($c['lessor_id_number']) . '        Cấp ngày/Tại: ' . ($c['lessor_id_issue_date'] ? vndate($c['lessor_id_issue_date']) : '……') . ' / ' . xwv($c['lessor_id_issue_place'])));
$body .= para(run('Địa chỉ liên lạc: ' . xwv($c['lessor_address'])));
$body .= para(run('Chủ sở hữu hợp pháp căn hộ số/Legal owner of the apartment No: ') . run(xwv($c['room_code']), ['b' => true]));
$body .= para(run('Dưới đây được gọi là Bên cho thuê / Hereinafter called "the Lessor"', ['i' => true]), ['after' => 160]);

$body .= para(run('BÊN THUÊ / THE LESSEE:', ['b' => true]), ['after' => 60]);
$body .= para(run('Tên/Name: ') . run(xwv($c['lessee_name']), ['b' => true]) . run('        Ngày sinh: ' . ($c['lessee_dob'] ? vndate($c['lessee_dob']) : '……………')));
$body .= para(run('Quốc tịch: ' . xwv($c['lessee_nationality']) . '        SĐT/Email: ' . xwv($c['lessee_phone']) . ' / ' . xwv($c['lessee_email'])));
$body .= para(run('Hộ chiếu/CCCD số: ' . xwv($c['lessee_id_number']) . '        Cấp ngày/Tại: ' . ($c['lessee_id_issue_date'] ? vndate($c['lessee_id_issue_date']) : '……') . ' / ' . xwv($c['lessee_id_issue_place'])));
$body .= para(run('Địa chỉ liên lạc: ' . xwv($c['lessee_address'])));
$body .= para(run('Dưới đây được gọi là Bên Thuê / Hereinafter called "the Lessee"', ['i' => true]), ['after' => 200]);

$body .= para(run('Điều/Article 1: Đối tượng hợp đồng / Object of the contract', ['b' => true]), ['after' => 60]);
$body .= para(run('Bên Thuê đồng ý thuê và Bên Cho Thuê đồng ý cho thuê căn hộ: ') . run(xwv($c['room_code']), ['b' => true]) . run(' tại ') . run(xwv($c['zone']), ['b' => true]) . run('.'));
$body .= para(run('The Lessee agrees to rent and the Lessor agrees to lease the apartment No: ' . xwv($c['room_code']) . ' at ' . xwv($c['zone']) . '.', ['i' => true]), ['after' => 160]);

$body .= para(run('Điều/Article 2: Mục đích thuê / Purpose of use', ['b' => true]), ['after' => 60]);
$body .= para(run('2.1: Mục đích thuê là để ở. Purpose for using the premises for personal residence.'));
$body .= para(run('2.2: Trang thiết bị nội thất và các tiện ích: đầy đủ. Furniture and appliances: full.'), ['after' => 160]);

$body .= para(run('Điều/Article 3: Tiền thuê / Apartment rental', ['b' => true]), ['after' => 60]);
$body .= para(run('3.1: Tiền thuê nhà là: ') . run(money($c['monthly_rent']) . '/tháng', ['b' => true]) . run(' — The apartment rental shall be: ' . money($c['monthly_rent']) . '/month.', ['i' => true]));
if ($c['rent_note']) $body .= para(run('3.2: ' . $c['rent_note'] . '.'));
$body .= para(run('3.3: Tiền thuê nhà sẽ được cố định trong thời hạn Hợp đồng thuê. The Rental price shall be fixed during the term of the Lease.'));
$body .= para(run('3.4: Tiền đặt cọc/Security deposit: Ngay sau khi ký kết hợp đồng này, Bên Thuê nhà sẽ đặt cọc là: ') . run(money($c['deposit_amount']), ['b' => true]) . run('. Khoản đặt cọc sẽ được hoàn trả lại ngay sau khi kết thúc hợp đồng theo đúng thỏa thuận hai bên.'), ['after' => 160]);

$body .= para(run('Điều/Article 4: Thời hạn thuê / Duration of the lease', ['b' => true]), ['after' => 60]);
$body .= para(run('4.1: Thời hạn thuê bắt đầu từ ' . xwv($c['checkin_time']) . ' ngày ') . run(vndate($c['start_date']), ['b' => true]) . run(' đến ' . xwv($c['checkout_time']) . ' ngày ') . run(vndate($c['end_date']), ['b' => true]) . run('.'), ['after' => 160]);

$body .= para(run('Điều/Article 5: Thanh toán / Method of payment', ['b' => true]), ['after' => 60]);
$body .= para(run('5.1: Tiền thuê nhà sẽ được thanh toán bằng hình thức: ') . run($c['payment_method'] === 'tien_mat' ? 'Tiền mặt' : 'Chuyển khoản', ['b' => true]) . run('.'));
$body .= para(run('Số tài khoản: ' . xwv($c['receiving_account']) . ' — Ngân hàng: ' . xwv($c['bank_name']) . ' — Người thụ hưởng: ' . xwv($c['beneficiary_name'])));
if ($c['payment_note']) $body .= para(run('5.2: ' . $c['payment_note']));
$body .= para(run(''), ['after' => 60]);

$body .= para(run('Điều/Article 6: Nghĩa vụ của hai bên / Both parties responsibilities', ['b' => true]), ['after' => 60]);
$body .= para(run('6.1: Bên Cho Thuê có trách nhiệm bàn giao căn hộ đúng hiện trạng, đảm bảo quyền sử dụng riêng biệt cho Bên Thuê trong suốt thời gian thuê, và nhanh chóng sửa chữa các hư hỏng thuộc phần xây dựng của căn hộ.'));
$body .= para(run('6.2: Bên Thuê có trách nhiệm thanh toán đúng hẹn, sử dụng căn hộ đúng mục đích, bàn giao lại căn hộ với đầy đủ trang thiết bị trong tình trạng tốt khi hết hạn hợp đồng, và chịu trách nhiệm với các hư hỏng do mình gây ra.'), ['after' => 160]);

$body .= para(run('Điều/Article 7: Kết thúc hợp đồng / Termination of this lease agreement', ['b' => true]), ['after' => 60]);
$body .= para(run('Hợp đồng kết thúc khi hết hạn hoặc khi hai bên thỏa thuận chấm dứt trước hạn. Trường hợp Bên Thuê đơn phương chấm dứt trước hạn, tiền cọc và phần tiền thuê đã trả trước cho thời gian chưa sử dụng (nếu có) sẽ không được hoàn trả, trừ khi hai bên có thỏa thuận khác.'), ['after' => 160]);

$body .= para(run('Điều/Article 8: Điều khoản chung / General Provisions', ['b' => true]), ['after' => 60]);
$body .= para(run('8.1: Hợp đồng này được thực hiện đầy đủ bởi hai bên. Mọi điều chỉnh, bổ sung phải được sự đồng ý bằng văn bản của hai bên. Nếu có tranh chấp, hai bên ưu tiên giải quyết qua hòa giải, thương lượng.'));
$body .= para(run('8.2: Hợp đồng này được lập thành hai (02) bản có giá trị như nhau; mỗi bên giữ một bản.'), ['after' => 160]);

if ($c['note']) {
    $body .= para(run('Ghi chú thêm', ['b' => true]), ['after' => 60]);
    $body .= para(run($c['note']), ['after' => 160]);
}

// Bang ky ten
$body .= '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
    . '<w:top w:val="none"/><w:left w:val="none"/><w:bottom w:val="none"/><w:right w:val="none"/>'
    . '<w:insideH w:val="none"/><w:insideV w:val="none"/></w:tblBorders></w:tblPr>'
    . '<w:tblGrid><w:gridCol w:w="4500"/><w:gridCol w:w="4500"/></w:tblGrid>'
    . '<w:tr>'
    . '<w:tc><w:tcPr><w:tcW w:w="4500" w:type="dxa"/></w:tcPr>'
    . para(run('BÊN CHO THUÊ', ['b' => true]), ['align' => 'center', 'after' => 0])
    . para(run('THE LESSOR', ['i' => true]), ['align' => 'center', 'after' => 800])
    . para(run(xwv($c['lessor_name']), ['b' => true]), ['align' => 'center'])
    . '</w:tc>'
    . '<w:tc><w:tcPr><w:tcW w:w="4500" w:type="dxa"/></w:tcPr>'
    . para(run('BÊN THUÊ', ['b' => true]), ['align' => 'center', 'after' => 0])
    . para(run('THE LESSEE', ['i' => true]), ['align' => 'center', 'after' => 800])
    . para(run(xwv($c['lessee_name']), ['b' => true]), ['align' => 'center'])
    . '</w:tc>'
    . '</w:tr></w:tbl>';

$documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
    . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<w:body>' . $body
    . '<w:sectPr><w:pgSz w:w="11907" w:h="16840"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr>'
    . '</w:body></w:document>';

$contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Default Extension="png" ContentType="image/png"/>'
    . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
    . '</Types>';

$relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
    . '</Relationships>';

$documentRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rIdLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo.png"/>'
    . '</Relationships>';

$tmpFile = tempnam(sys_get_temp_dir(), 'hd_') . '.docx';
$zip = new ZipArchive();
$zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml', $contentTypesXml);
$zip->addFromString('_rels/.rels', $relsXml);
if ($hasLogo) {
    $zip->addFromString('word/_rels/document.xml.rels', $documentRelsXml);
    $zip->addFile($logoPath, 'word/media/logo.png');
}
$zip->addFromString('word/document.xml', $documentXml);
$zip->close();

$downloadName = 'HopDong_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $c['contract_code']) . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($tmpFile));
readfile($tmpFile);
unlink($tmpFile);
exit;

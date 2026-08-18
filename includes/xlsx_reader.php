<?php
/**
 * Doc du lieu tu file .xlsx (Excel) toi gian, khong can thu vien Composer -
 * chi dung ZipArchive + SimpleXML co san trong PHP.
 * Chi doc sheet dau tien, tra ve mang cac dong (moi dong la mang gia tri
 * theo thu tu cot A,B,C..., cac o trong duoc dien '').
 */
function read_xlsx_rows(string $filePath): array
{
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new RuntimeException('Không thể mở file Excel (.xlsx). File có thể bị hỏng.');
    }

    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = @simplexml_load_string($ssXml);
        if ($ss !== false) {
            foreach ($ss->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }
    }

    $sheetPath = 'xl/worksheets/sheet1.xml';
    if ($zip->locateName($sheetPath) === false) {
        $sheetPath = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/.*\.xml$#', $name)) {
                $sheetPath = $name;
                break;
            }
        }
    }

    $sheetXml = $sheetPath ? $zip->getFromName($sheetPath) : false;
    $zip->close();

    if ($sheetXml === false) {
        throw new RuntimeException('Không tìm thấy dữ liệu (sheet) trong file Excel.');
    }

    $xml = @simplexml_load_string($sheetXml);
    if ($xml === false || !isset($xml->sheetData)) {
        throw new RuntimeException('Không đọc được nội dung file Excel.');
    }

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            if (!preg_match('/^([A-Z]+)\d+$/', $ref, $m)) continue;
            $colIndex = xlsx_col_to_index($m[1]);

            $type = (string)$c['t'];
            if ($type === 's') {
                $idx = (int)$c->v;
                $value = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = isset($c->is->t) ? (string)$c->is->t : '';
            } else {
                $value = (string)$c->v;
            }
            $cells[$colIndex] = $value;
        }
        if ($cells) {
            $max = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $max; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            $rows[] = $line;
        } else {
            $rows[] = [];
        }
    }

    return $rows;
}

function xlsx_col_to_index(string $letters): int
{
    $letters = strtoupper($letters);
    $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

/**
 * Chuyen so serial ngay cua Excel (vd 46000) sang chuoi YYYY-MM-DD.
 * Neu chuoi dau vao khong phai so (da la text ngay binh thuong) thi giu nguyen.
 */
function xlsx_maybe_date(string $value): string
{
    if ($value !== '' && ctype_digit($value) && (int)$value > 20000 && (int)$value < 80000) {
        $unixTs = ((int)$value - 25569) * 86400; // 25569 = so ngay tu 1899-12-30 den 1970-01-01
        return gmdate('Y-m-d', $unixTs);
    }
    return $value;
}

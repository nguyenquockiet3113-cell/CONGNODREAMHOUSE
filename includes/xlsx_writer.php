<?php
/**
 * Ghi file .xlsx toi thieu (khong can Composer/PHPSpreadsheet) va xuat thang ra trinh duyet.
 * $rows: mang cac dong, moi dong la mang gia tri theo dung thu tu $headers.
 * $numericCols: chi so cot (0-based) can xuat dang so (de Excel cong/can phai duoc) thay vi chuoi.
 */
function write_xlsx_and_exit(string $filename, array $headers, array $rows, array $numericCols = []): void
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::OVERWRITE);

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
        '</Types>'
    );

    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>'
    );

    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
        '</Relationships>'
    );

    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets>' .
        '</workbook>'
    );

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<sheetData>';

    $sheetXml .= '<row r="1">';
    foreach (array_values($headers) as $i => $h) {
        $sheetXml .= xlsx_cell_xml(xlsx_col_letter($i + 1), 1, $h, false);
    }
    $sheetXml .= '</row>';

    $rowNum = 2;
    foreach ($rows as $row) {
        $sheetXml .= '<row r="' . $rowNum . '">';
        foreach (array_values($row) as $i => $val) {
            $sheetXml .= xlsx_cell_xml(xlsx_col_letter($i + 1), $rowNum, $val, in_array($i, $numericCols, true));
        }
        $sheetXml .= '</row>';
        $rowNum++;
    }

    $sheetXml .= '</sheetData></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

function xlsx_col_letter(int $index): string
{
    $letters = '';
    while ($index > 0) {
        $rem = ($index - 1) % 26;
        $letters = chr(65 + $rem) . $letters;
        $index = intdiv($index - 1, 26);
    }
    return $letters;
}

function xlsx_cell_xml(string $col, int $row, $value, bool $numeric): string
{
    $ref = $col . $row;
    if ($numeric && $value !== '' && $value !== null && is_numeric($value)) {
        return '<c r="' . $ref . '"><v>' . htmlspecialchars((string)(0 + $value), ENT_QUOTES | ENT_XML1) . '</v></c>';
    }
    $text = htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    return '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
}

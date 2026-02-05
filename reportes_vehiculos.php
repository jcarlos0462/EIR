<?php
session_start();
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header("Location: index.html");
    exit();
}

// Enable errors for debugging (remove or disable on production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection (same credentials used elsewhere)
$servername = "localhost";
$username = "u174025152_Administrador";
$password = "0066jv_A2";
$dbname = "u174025152_EIR";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Conexión fallida: " . $conn->connect_error);

// Fetch filter lists
$buques = [];
$res = $conn->query("SELECT DISTINCT Buque FROM vehiculo WHERE IFNULL(Buque,'')<>'' ORDER BY Buque");
if ($res) { while ($r = $res->fetch_assoc()) $buques[] = $r['Buque']; }

$areas = [];
$res = $conn->query("SELECT CodAreaDano, NomAreaDano FROM areadano ORDER BY CodAreaDano");
if ($res) { while ($r = $res->fetch_assoc()) $areas[] = $r; }

// Origen values from RegistroDanio if available
$origenes = [];
// table RegistroDanio does not have column 'Origen' on this schema — use TipoOperacion values instead
$res = $conn->query("SELECT DISTINCT IFNULL(TipoOperacion,'') AS Origen FROM RegistroDanio WHERE IFNULL(TipoOperacion,'')<>'' ORDER BY Origen");
if ($res) { while ($r = $res->fetch_assoc()) $origenes[] = $r['Origen']; }

// Check if RegistroDanio has any rows; if empty, we will force queries to return no results
$rd_count = 0;
$tmp = $conn->query("SELECT COUNT(*) AS c FROM RegistroDanio");
if ($tmp) {
    $rowc = $tmp->fetch_assoc();
    $rd_count = intval($rowc['c']);
}
$registro_danio_empty = ($rd_count === 0);

// read inputs
$vin = isset($_REQUEST['vin']) ? trim($_REQUEST['vin']) : '';
$buque = isset($_REQUEST['buque']) ? trim($_REQUEST['buque']) : '';
$date_from = isset($_REQUEST['date_from']) ? trim($_REQUEST['date_from']) : '';
$date_to = isset($_REQUEST['date_to']) ? trim($_REQUEST['date_to']) : '';
$area = isset($_REQUEST['area']) ? trim($_REQUEST['area']) : '';
$maniobra = isset($_REQUEST['maniobra']) ? trim($_REQUEST['maniobra']) : '';
$origen = isset($_REQUEST['origen']) ? trim($_REQUEST['origen']) : '';
// print mode (open printable view)
$print_mode = isset($_GET['print']) || isset($_POST['print']);

// build WHERE separately for vehicle filters and damage filters so each can work independently
$where_v = []; // conditions on vehiculo
$where_rd = []; // conditions on RegistroDanio

if ($vin !== '') $where_v[] = "v.VIN LIKE '%" . $conn->real_escape_string($vin) . "%'";
if ($buque !== '') $where_v[] = "v.Buque = '" . $conn->real_escape_string($buque) . "'";
if ($date_from !== '') {
    $d = $conn->real_escape_string($date_from) . " 00:00:00";
    $where_rd[] = "rd.FechaRegistro >= '" . $d . "'";
}
if ($date_to !== '') {
    // include entire day for date_to by setting time to 23:59:59
    $d = $conn->real_escape_string($date_to) . " 23:59:59";
    $where_rd[] = "rd.FechaRegistro <= '" . $d . "'";
}
if ($area !== '') $where_rd[] = "rd.CodAreaDano = '" . $conn->real_escape_string($area) . "'";
if ($maniobra !== '') $where_rd[] = "rd.TipoOperacion LIKE '%" . $conn->real_escape_string($maniobra) . "%'";
if ($origen !== '') $where_rd[] = "rd.TipoOperacion = '" . $conn->real_escape_string($origen) . "'";

// Decide which base to use:
// - If only vehicle filters provided (VIN/Buque) and no damage filters, start from vehiculo LEFT JOIN RegistroDanio
// - Otherwise, start from RegistroDanio (existing behaviour)

$has_v = count($where_v) > 0;
$has_rd = count($where_rd) > 0;

if ($has_v && !$has_rd) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_v);
    $sql = "SELECT rd.FechaRegistro, v.VIN, v.Marca, v.Modelo, v.Color, v.`Año` AS Ano, v.Puerto, v.Terminal, v.Buque, v.Viaje,
                rd.CodAreaDano AS CodAreaDano, rd.CodTipoDano AS CodTipoDano, rd.CodSeveridadDano AS CodSeveridadDano, rd.TipoOperacion AS Origen, rd.TipoOperacion
            FROM vehiculo v
            LEFT JOIN RegistroDanio rd ON v.VIN = rd.VIN
            LEFT JOIN areadano a ON rd.CodAreaDano = a.CodAreaDano
            LEFT JOIN tipodano t ON rd.CodTipoDano = t.CodTipoDano
            LEFT JOIN severidaddano s ON rd.CodSeveridadDano = s.CodSeveridadDano
            " . $where_sql . "
            ORDER BY rd.FechaRegistro DESC";
} else {
    // merge both sets so filters combine when user provides multiple filter types
    $all_where = array_merge($where_rd, $where_v);
    $where_sql = '';
    if (count($all_where) > 0) $where_sql = 'WHERE ' . implode(' AND ', $all_where);
    $sql = "SELECT rd.FechaRegistro, rd.VIN, v.Marca, v.Modelo, v.Color, v.`Año` AS Ano, v.Puerto, v.Terminal, v.Buque, v.Viaje,
                rd.CodAreaDano AS CodAreaDano, rd.CodTipoDano AS CodTipoDano, rd.CodSeveridadDano AS CodSeveridadDano, rd.TipoOperacion AS Origen, rd.TipoOperacion
            FROM RegistroDanio rd
            LEFT JOIN vehiculo v ON rd.VIN = v.VIN
            LEFT JOIN areadano a ON rd.CodAreaDano = a.CodAreaDano
            LEFT JOIN tipodano t ON rd.CodTipoDano = t.CodTipoDano
            LEFT JOIN severidaddano s ON rd.CodSeveridadDano = s.CodSeveridadDano
            " . $where_sql . "
            ORDER BY rd.FechaRegistro DESC";
}

// Export CSV if requested
if (isset($_POST['export_csv'])) {
    $res = $conn->query($sql);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=report_vehiculos.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['FechaRegistro','VIN','Marca','Modelo','Color','Año','Puerto','Terminal','Buque','Viaje','CodAreaDano','CodTipoDano','CodSeveridadDano','Origen','Maniobra']);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [
                $r['FechaRegistro'] ?? '',
                $r['VIN'] ?? '',
                $r['Marca'] ?? '',
                $r['Modelo'] ?? '',
                $r['Color'] ?? '',
                $r['Ano'] ?? '',
                $r['Puerto'] ?? '',
                $r['Terminal'] ?? '',
                $r['Buque'] ?? '',
                $r['Viaje'] ?? '',
                $r['CodAreaDano'] ?? '',
                $r['CodTipoDano'] ?? '',
                $r['CodSeveridadDano'] ?? '',
                $r['Origen'] ?? '',
                $r['TipoOperacion'] ?? ''
            ]);
        }
    }
    fclose($out);
    exit();
}

// Export Excel 2003 XML (compatible con Excel sin librerías externas)
if (isset($_POST['export_xml'])) {
    $resx = $conn->query($sql);
    $rows = [];
    if ($resx) while ($r = $resx->fetch_assoc()) $rows[] = $r;

    $headers_xml = ['FechaRegistro','VIN','Marca','Modelo','Color','Año','Puerto','Terminal','Buque','Viaje','CodAreaDano','CodTipoDano','CodSeveridadDano','Origen','Maniobra'];

    // send headers for Excel
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="report_vehiculos.xls"');
    echo '<?xml version="1.0"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet'
        . '" xmlns:o="urn:schemas-microsoft-com:office:office"'
        . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
        . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    echo "<Worksheet ss:Name=\"Report\">\n<Table>\n";

    // header row
    echo "<Row>\n";
    foreach ($headers_xml as $h) {
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($h) . '</Data></Cell>\n';
    }
    echo "</Row>\n";

    // data rows
    foreach ($rows as $r) {
        echo "<Row>\n";
        $vals = [
            $r['FechaRegistro'] ?? '', $r['VIN'] ?? '', $r['Marca'] ?? '', $r['Modelo'] ?? '', $r['Color'] ?? '', $r['Ano'] ?? '',
            $r['Puerto'] ?? '', $r['Terminal'] ?? '', $r['Buque'] ?? '', $r['Viaje'] ?? '', $r['CodAreaDano'] ?? '', $r['CodTipoDano'] ?? '',
            $r['CodSeveridadDano'] ?? '', $r['Origen'] ?? '', $r['TipoOperacion'] ?? ''
        ];
        foreach ($vals as $val) {
            $type = is_numeric($val) ? 'Number' : 'String';
            echo '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars($val) . '</Data></Cell>\n';
        }
        echo "</Row>\n";
    }

    echo "</Table>\n</Worksheet>\n</Workbook>";
    exit();
}

// Export PDF (server-side) using Dompdf if available
if (isset($_POST['export_pdf'])) {
    $resx = $conn->query($sql);
    $rows = [];
    if ($resx) while ($r = $resx->fetch_assoc()) $rows[] = $r;

    $generated_at = date('Y-m-d H:i');
    $filters = [];
    if ($vin !== '') $filters[] = 'VIN: '.htmlspecialchars($vin);
    if ($buque !== '') $filters[] = 'Buque: '.htmlspecialchars($buque);
    if ($date_from !== '') $filters[] = 'Desde: '.htmlspecialchars($date_from);
    if ($date_to !== '') $filters[] = 'Hasta: '.htmlspecialchars($date_to);
    if ($area !== '') $filters[] = 'Área: '.htmlspecialchars($area);
    if ($maniobra !== '') $filters[] = 'Maniobra: '.htmlspecialchars($maniobra);
    if ($origen !== '') $filters[] = 'Origen: '.htmlspecialchars($origen);
    $filters_text = $filters ? implode(' / ', $filters) : '(sin filtros)';

    $html = '<!doctype html><html><head><meta charset="utf-8"><style>' .
        'body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#222} ' .
        '.hdr{margin-bottom:10px} .title{font-size:18px;font-weight:700} .meta{font-size:11px;color:#444;margin-top:4px} ' .
        'table{border-collapse:collapse;width:100%;margin-top:8px} th,td{border:1px solid #ddd;padding:6px 8px;text-align:left} th{background:#f1f5f9;font-weight:700}' .
        '</style></head><body>';
    $html .= '<div class="hdr"><div class="title">Reporte - Vehículos y Daños</div>';
    $html .= '<div class="meta">Generado: '.htmlspecialchars($generated_at).' &nbsp; | &nbsp; Filtros: '.htmlspecialchars($filters_text).'</div></div>';

    $html .= '<table><thead><tr>' .
        '<th>Fecha</th><th>VIN</th><th>Marca</th><th>Modelo</th><th>Color</th><th>Año</th>' .
        '<th>Puerto</th><th>Terminal</th><th>Buque</th><th>Viaje</th>' .
        '<th>CodAreaDano</th><th>CodTipoDano</th><th>CodSeveridadDano</th><th>Origen</th><th>Maniobra</th>' .
        '</tr></thead><tbody>';

    foreach ($rows as $r) {
        $html .= '<tr>' .
            '<td>'.htmlspecialchars($r['FechaRegistro'] ?? '').'</td>' .
            '<td>'.htmlspecialchars($r['VIN'] ?? '').'</td>' .
            '<td>'.htmlspecialchars($r['Marca'] ?? '').'</td>' .
            '<td>'.htmlspecialchars($r['Modelo'] ?? '').'</td>' .
            '<td>'.htmlspecialchars($r['Color'] ?? '').'</td>' .
            '<td>'.htmlspecialchars($r['Ano'] ?? '').'</td>' .
            '<td>'.htmlspecialchars($r['Puerto'] ?? '').'</td>' .
            '<td>'.htmlspecialchars($r['Terminal'] ?? '').'</td>' .
            '<td>'.htmlspecialchars($r['Buque'] ?? '').'</td>' .
            '<td>'.htmlspecialchars($r['Viaje'] ?? '').'</td>' .
            '<td>'.htmlspecialchars($r['CodAreaDano'] ?? '').'</td>' .
            '<td>'.htmlspecialchars($r['CodTipoDano'] ?? '').'</td>' .
            '<td>'.htmlspecialchars($r['CodSeveridadDano'] ?? '').'</td>' .
            '<td>'.htmlspecialchars($r['Origen'] ?? '').'</td>' .
            '<td>'.htmlspecialchars($r['TipoOperacion'] ?? '').'</td>' .
            '</tr>';
    }

    $html .= '</tbody></table></body></html>';

    $vendor = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($vendor)) {
        echo '<div class="alert alert-danger">Dompdf no está instalado en el servidor. Para habilitar PDF server-side ejecuta en el servidor:<br><code>composer require dompdf/dompdf</code></div>';
        exit();
    }
    require $vendor;
    try {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('report_vehiculos.pdf', ['Attachment' => 1]);
        exit();
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error generando PDF: '.htmlspecialchars($e->getMessage()).'</div>';
        exit();
    }
}

// Export XLSX (PhpSpreadsheet) if requested
if (isset($_POST['export_xlsx'])) {
    // execute same query (no limit)
    $res = $conn->query($sql);

    // try to load PhpSpreadsheet
    $vendor = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($vendor)) {
        echo '<div class="alert alert-danger">PhpSpreadsheet no está instalado. Para habilitar exportación .xlsx ejecuta en el servidor:<br><code>composer require phpoffice/phpspreadsheet</code></div>';
        exit();
    }
    require $vendor;

    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header row
        $headers = ['FechaRegistro','VIN','Marca','Modelo','Color','Año','Puerto','Terminal','Buque','Viaje','CodAreaDano','CodTipoDano','CodSeveridadDano','Origen','Maniobra'];
        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($col, 1, $h);
            $col++;
        }

        // Data rows
        $rowNum = 2;
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $col = 1;
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['FechaRegistro'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['VIN'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['Marca'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['Modelo'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['Color'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['Ano'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['Puerto'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['Terminal'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['Buque'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['Viaje'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['CodAreaDano'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['CodTipoDano'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['CodSeveridadDano'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['Origen'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $r['TipoOperacion'] ?? '');
                $rowNum++;
            }
        }

        // Style header: bold + background
        $styleArray = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DDEBF7']
            ]
        ];
        $sheet->getStyle('A1:O1')->applyFromArray($styleArray);

        // Auto-size columns
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Freeze header
        $sheet->freezePane('A2');

        // Send to browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="report_vehiculos.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit();
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error generando XLSX: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit();
    }
}

// Export XLSX (simple generator without external libs)
if (isset($_POST['export_xlsx'])) {
    // run query without limit
    if ($registro_danio_empty) {
        // output empty xlsx with header only
        $rows = [];
    } else {
        $resx = $conn->query($sql);
        $rows = [];
        if ($resx) while ($r = $resx->fetch_assoc()) $rows[] = $r;
    }

    $headers_x = ['FechaRegistro','VIN','Marca','Modelo','Color','Año','Puerto','Terminal','Buque','Viaje','CodAreaDano','CodTipoDano','CodSeveridadDano','Origen','Maniobra'];

    // build sheet rows (array of arrays)
    $sheet_rows = [];
    $sheet_rows[] = $headers_x;
    foreach ($rows as $r) {
        $sheet_rows[] = [
            $r['FechaRegistro'] ?? '',
            $r['VIN'] ?? '',
            $r['Marca'] ?? '',
            $r['Modelo'] ?? '',
            $r['Color'] ?? '',
            $r['Ano'] ?? '',
            $r['Puerto'] ?? '',
            $r['Terminal'] ?? '',
            $r['Buque'] ?? '',
            $r['Viaje'] ?? '',
            $r['CodAreaDano'] ?? '',
            $r['CodTipoDano'] ?? '',
            $r['CodSeveridadDano'] ?? '',
            $r['Origen'] ?? '',
            $r['TipoOperacion'] ?? ''
        ];
    }

    // Function to create minimal xlsx using inline strings and a header style
    $zip = new ZipArchive();
    $tmpfile = tempnam(sys_get_temp_dir(), 'xlsx');
    if ($zip->open($tmpfile, ZipArchive::OVERWRITE) !== true) {
        header('HTTP/1.1 500 Internal Server Error');
        echo 'No se pudo crear archivo temporal.';
        exit();
    }

    // [Content_Types].xml
    $content_types = '<?xml version="1.0" encoding="UTF-8"?>\n<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">\n' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>\n' .
        '<Default Extension="xml" ContentType="application/xml"/>\n' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>\n' .
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>\n' .
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>\n' .
        '</Types>';
    $zip->addFromString('[Content_Types].xml', $content_types);

    // _rels/.rels
    $rels = '<?xml version="1.0" encoding="UTF-8"?>\n<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">\n' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="/xl/workbook.xml"/>\n' .
        '</Relationships>';
    $zip->addFromString('_rels/.rels', $rels);

    // xl/workbook.xml
    $workbook = '<?xml version="1.0" encoding="UTF-8"?>\n<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"\n xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">\n<sheets>\n' .
        '<sheet name="Report" sheetId="1" r:id="rId1"/>\n</sheets>\n</workbook>';
    $zip->addFromString('xl/workbook.xml', $workbook);

    // xl/_rels/workbook.xml.rels
    $wb_rels = '<?xml version="1.0" encoding="UTF-8"?>\n<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">\n' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>\n' .
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>\n' .
        '</Relationships>';
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wb_rels);

    // xl/styles.xml (basic: header style with bold and fill)
    $styles = '<?xml version="1.0" encoding="UTF-8"?>\n<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">\n' .
        '<fonts count="2">\n<font><sz val="11"/><color theme="1"/><name val="Calibri"/></font>\n' .
        '<font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/></font>\n</fonts>\n' .
        '<fills count="2">\n<fill><patternFill patternType="none"/></fill>\n' .
        '<fill><patternFill patternType="solid"><fgColor rgb="FFD9E1F2"/><bgColor indexed="64"/></patternFill></fill>\n</fills>\n' .
        '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>\n' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>\n' .
        '<cellXfs count="2">\n<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>\n' .
        '<xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/>\n</cellXfs>\n' .
        '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>\n' .
        '</styleSheet>';
    $zip->addFromString('xl/styles.xml', $styles);

    // xl/worksheets/sheet1.xml
    $sheetXml = '<?xml version="1.0" encoding="UTF-8"?>\n<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">\n<sheetData>\n';

    // helper to convert column index to letter
    $colLetter = function($c) {
        $letters = '';
        while ($c >= 0) {
            $letters = chr(65 + ($c % 26)) . $letters;
            $c = intval($c / 26) - 1;
        }
        return $letters;
    };

    foreach ($sheet_rows as $rindex => $rvals) {
        $rowNum = $rindex + 1;
        $sheetXml .= "<row r=\"$rowNum\">\n";
        foreach ($rvals as $cindex => $val) {
            $col = $colLetter($cindex);
            $cellRef = $col . $rowNum;
            // header row -> style 1
            if ($rindex === 0) {
                $sheetXml .= "<c r=\"$cellRef\" t=\"inlineStr\" s=\"1\"><is><t>" . htmlspecialchars($val) . "</t></is></c>\n";
            } else {
                // try numeric detection
                if (is_numeric($val)) {
                    $sheetXml .= "<c r=\"$cellRef\"><v>" . $val . "</v></c>\n";
                } else {
                    $sheetXml .= "<c r=\"$cellRef\" t=\"inlineStr\"><is><t>" . htmlspecialchars($val) . "</t></is></c>\n";
                }
            }
        }
        $sheetXml .= "</row>\n";
    }

    $sheetXml .= '</sheetData>\n</worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

    $zip->close();

    // send file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="report_vehiculos.xlsx"');
    header('Content-Length: ' . filesize($tmpfile));
    readfile($tmpfile);
    unlink($tmpfile);
    exit();
}

// Decide whether to run the display query: only when user applied any filter or requested export
$res = null;
$has_filter = false;
if (
    $vin !== '' || $buque !== '' || $date_from !== '' || $date_to !== '' ||
    $area !== '' || $maniobra !== '' || $origen !== '' || isset($_POST['export_csv']) || isset($_POST['export_xml']) || $print_mode
) {
    $has_filter = true;
}

if ($has_filter) {
    // run query for display (limit to 200 rows to avoid heavy pages)
    if ($registro_danio_empty) {
        // force empty result set
        $display_sql = "SELECT rd.FechaRegistro, rd.VIN FROM RegistroDanio rd WHERE 0 LIMIT 0";
        $res = $conn->query($display_sql);
    } else {
        // if printing, return full result; otherwise limit to 200 for display
        if ($print_mode) {
            $display_sql = $sql; // full
        } else {
            $display_sql = $sql . " LIMIT 200";
        }
        $res = $conn->query($display_sql);
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reportes Vehículos - EIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="navbar_styles.css">
    <style>
        /* Report styling */
        .report-title { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; }
        .report-meta { font-size: 0.9rem; color: #444; margin-bottom: 1rem; }
        table.report-table { border-collapse: collapse; width: 100%; }
        table.report-table th, table.report-table td { padding: 6px 8px; border: 1px solid #ddd; }
        table.report-table thead th { background:#f1f5f9; font-weight:600; }
        @media print {
            /* Force A4 landscape and tighten margins */
            @page { size: A4 landscape; margin: 8mm; }
            /* Some browsers also accept 'landscape' alone */
            @page { size: landscape; }

            /* Avoid forcing absolute viewport sizes (can break print previews) */
            body { font-size: 10px; color:#222; -webkit-print-color-adjust: exact; }
            .no-print { display:none !important; }
            .container-fluid { padding: 0; }
            /* Hide the UI chrome and the filters card only (keep result card visible) */
            .sidebar, .navbar, .filters-card { display: none !important; }
            .main-content { margin: 0; padding: 0; }
            .report-meta { font-size: 10px; margin-bottom: 6px; }
            table.report-table { font-size: 9px; table-layout: fixed; border-collapse: collapse; width:100%; }
            table.report-table th, table.report-table td { padding: 4px 6px; border: 1px solid #bbb; }
            table.report-table thead th { background:#f1f5f9; font-weight:700; }
            table.report-table th { white-space: nowrap; }
            table.report-table td { word-wrap: break-word; white-space: normal; }
            .table-responsive { overflow: visible !important; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
            tr { page-break-inside: avoid; }
            a[href]:after { content: none !important; }
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <?php include 'sidebar.php'; ?>
        </div>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="card filters-card no-print">
                <div class="card-body">
                    <h4>Reportes - Vehículos y Daños</h4>
                    <?php
                        // build print URL with current filters
                        $print_params = [];
                        if ($vin !== '') $print_params['vin'] = $vin;
                        if ($buque !== '') $print_params['buque'] = $buque;
                        if ($date_from !== '') $print_params['date_from'] = $date_from;
                        if ($date_to !== '') $print_params['date_to'] = $date_to;
                        if ($area !== '') $print_params['area'] = $area;
                        if ($maniobra !== '') $print_params['maniobra'] = $maniobra;
                        if ($origen !== '') $print_params['origen'] = $origen;
                        $print_params['print'] = 1;
                        $print_url = 'reportes_vehiculos.php' . (count($print_params) ? ('?' . http_build_query($print_params)) : '');
                    ?>
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">VIN</label>
                            <input class="form-control" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Buque</label>
                            <select name="buque" class="form-select">
                                <option value="">(todos)</option>
                                <?php foreach ($buques as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>" <?php if ($b===$buque) echo 'selected'; ?>><?php echo htmlspecialchars($b); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha desde</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha hasta</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Maniobra / TipoOperacion</label>
                            <input class="form-control" name="maniobra" value="<?php echo htmlspecialchars($maniobra); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Área (Maniobra)</label>
                            <select name="area" class="form-select">
                                <option value="">(todas)</option>
                                <?php foreach ($areas as $a): ?>
                                    <option value="<?php echo htmlspecialchars($a['CodAreaDano']); ?>" <?php if ($a['CodAreaDano']==$area) echo 'selected'; ?>><?php echo htmlspecialchars($a['CodAreaDano']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Origen</label>
                            <select name="origen" class="form-select">
                                <option value="">(todos)</option>
                                <?php foreach ($origenes as $o): ?>
                                    <option value="<?php echo htmlspecialchars($o); ?>" <?php if ($o===$origen) echo 'selected'; ?>><?php echo htmlspecialchars($o); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 mt-2 no-print">
                            <button type="submit" class="btn btn-primary">Generar reporte</button>
                            <button type="submit" name="export_xml" value="1" formaction="reportes_vehiculos.php" formmethod="post" class="btn btn-warning ms-2">Exportar XLS (XML)</button>
                            <button type="button" id="btnPrint" class="btn btn-secondary ms-2">Imprimir PDF</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <h5>Resultados (muestra hasta 200 filas)</h5>
                    <?php if (!$has_filter): ?>
                        <div class="alert alert-secondary">No se muestran datos. Aplique filtros y presione "Generar reporte".</div>
                    <?php elseif ($registro_danio_empty): ?>
                        <div class="alert alert-warning">La tabla <strong>RegistroDanio</strong> no contiene registros. No se mostrarán resultados.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <?php if ($has_filter): ?>
                            <?php $generated_at = date('Y-m-d H:i');
                                $f = [];
                                if ($vin!=='') $f[] = 'VIN="'.htmlspecialchars($vin).'"';
                                if ($buque!=='') $f[] = 'Buque="'.htmlspecialchars($buque).'"';
                                if ($date_from!=='') $f[] = 'Desde="'.htmlspecialchars($date_from).'"';
                                if ($date_to!=='') $f[] = 'Hasta="'.htmlspecialchars($date_to).'"';
                                if ($area!=='') $f[] = 'Area="'.htmlspecialchars($area).'"';
                                if ($maniobra!=='') $f[] = 'Maniobra="'.htmlspecialchars($maniobra).'"';
                                if ($origen!=='') $f[] = 'Origen="'.htmlspecialchars($origen).'"';
                                $filters_text = $f ? implode(' / ', $f) : '(sin filtros)';
                            ?>
                            <div class="report-meta">Generado: <?php echo $generated_at; ?> — Filtros: <?php echo $filters_text; ?></div>
                        <?php endif; ?>
                        <table class="table table-sm table-striped report-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>VIN</th>
                                    <th>Marca</th>
                                    <th>Modelo</th>
                                    <th>Color</th>
                                    <th>Año</th>
                                    <th>Puerto</th>
                                    <th>Terminal</th>
                                    <th>Buque</th>
                                    <th>Viaje</th>
                                    <th>CodAreaDano</th>
                                    <th>CodTipoDano</th>
                                    <th>CodSeveridadDano</th>
                                    <th>Origen</th>
                                    <th>Maniobra</th>
                                </tr>
                            </thead>
                            <tbody>
<?php if ($res && $res->num_rows>0): while($row = $res->fetch_assoc()): ?>
    <tr>
        <td><?php echo htmlspecialchars($row['FechaRegistro'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['VIN'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Marca'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Modelo'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Color'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Ano'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Puerto'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Terminal'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Buque'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Viaje'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['CodAreaDano'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['CodTipoDano'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['CodSeveridadDano'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Origen'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['TipoOperacion'] ?? ''); ?></td>
    </tr>
<?php endwhile; else: ?>
    <tr><td colspan="15" class="text-center">No se encontraron resultados</td></tr>
<?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($print_mode): ?>
<script>
    // Give the browser more time to layout before opening the print dialog
    window.onload = function() { setTimeout(function(){ window.print(); }, 800); };
</script>
<?php endif; ?>
<script>
// Attach print handler to button so it doesn't trigger a page reload/search
document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('btnPrint');
    if (btn) {
        btn.addEventListener('click', function(e){
            // small delay to allow any UI changes before printing
            setTimeout(function(){ window.print(); }, 250);
        });
    }
});
</script>
</body>
</html>
<?php $conn->close(); ?>

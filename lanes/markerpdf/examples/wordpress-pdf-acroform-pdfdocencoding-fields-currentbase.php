<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$bullet = chr(0x80);
$dagger = chr(0x81);
$doubleDagger = chr(0x82);
$ellipsis = chr(0x83);
$leftQuote = chr(0x8d);
$rightQuote = chr(0x8e);
$fi = chr(0x93);
$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm PDFDocEncoding body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (workflow{$bullet}title) /TU (Title {$leftQuote}PDF{$rightQuote} label) "
    . "/TM (workflow{$bullet}title{$dagger}export) /V (Draft{$dagger} value) /DV (Default{$fi} value) "
    . "/MaxLen 48 /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Ch /T (workflow{$dagger}status) /V (publish{$bullet}state) "
    . "/DV (draft{$dagger}state) /Opt [[(draft{$dagger}state) (Draft{$doubleDagger} label)] "
    . "[(publish{$bullet}state) (Publish{$ellipsis} label)]] /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$fields = $form['fields'];
$encoded = json_encode($form, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if (!is_string($encoded)) {
    throw new RuntimeException('Expected AcroForm PDFDocEncoding review metadata to encode as UTF-8 JSON.');
}

$fieldNames = array_column($fields, 'name');
$expectedTitle = "workflow\u{2022}title";
$expectedStatus = "workflow\u{2020}status";
$pdfDocDecoded = in_array($expectedTitle, $fieldNames, true)
    && in_array($expectedStatus, $fieldNames, true)
    && str_contains($encoded, "Title \u{201C}PDF\u{201D} label")
    && str_contains($encoded, "Publish\u{2026} label");
$visibleTextClean = $plainText === 'Visible AcroForm PDFDocEncoding body'
    && !str_contains($plainText, "Draft\u{2020} value")
    && !str_contains($plainText, chr(0x80));

if (!$pdfDocDecoded || !$visibleTextClean) {
    throw new RuntimeException('AcroForm PDFDocEncoding field boundary smoke failed.');
}

$summary = [
    'native_boundary' => 'AcroForm field text strings decode PDFDocEncoding before WordPress review metadata',
    'field_count' => count($fields),
    'field_names' => $fieldNames,
    'pdfdocencoding_fields_decoded' => $pdfDocDecoded,
    'visible_text' => $plainText,
    'visible_text_excludes_form_values' => $visibleTextClean,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
];

echo '<!-- markerpdf-acroform-pdfdocencoding-fields-currentbase-smoke '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";

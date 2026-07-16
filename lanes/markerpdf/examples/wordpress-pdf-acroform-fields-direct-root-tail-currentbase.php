<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$malformedText = 'BT /F1 12 Tf 72 720 Td (Visible direct AcroForm root tail body) Tj ET';
$malformedPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm << /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >> 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($malformedText) . " >>\nstream\n{$malformedText}\nendstream\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (direct.root.tail) /TU (Direct root tail label) /TM (direct-root-tail-export) /V (Direct root tail value must not surface) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "50 0 obj\n<< /Fields [52 0 R] /NeedAppearances true >>\nendobj\n"
    . "52 0 obj\n<< /FT /Tx /T (trailing.direct.root.decoy) /V (Trailing direct root decoy value) >>\nendobj\n"
    . "%%EOF";

$validText = 'BT /F1 12 Tf 72 720 Td (Visible direct AcroForm root comment body) Tj ET';
$validPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm << /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >> % comment-only direct AcroForm tail\n/Lang (en-US) >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($validText) . " >>\nstream\n{$validText}\nendstream\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (direct.root.comment) /TU (Direct root comment label) /TM (direct-root-comment-export) /V (Direct root comment value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfAcroFormExtractor();
$textExtractor = new PdfTextExtractor();
$malformedForm = $extractor->extractForm($malformedPdf);
$validForm = $extractor->extractForm($validPdf);
$malformedVisibleText = $textExtractor->extractPlainText($malformedPdf);
$validVisibleText = $textExtractor->extractPlainText($validPdf);
$validField = $validForm['fields'][0] ?? null;

if ($malformedForm['fields'] !== [] || $malformedForm['need_appearances'] !== false) {
    throw new RuntimeException('Malformed direct AcroForm root tail must fail closed before field review.');
}
if (!is_array($validField) || ($validField['name'] ?? null) !== 'direct.root.comment') {
    throw new RuntimeException('Valid direct AcroForm root dictionary was not preserved.');
}
if (str_contains(json_encode($malformedForm, JSON_UNESCAPED_SLASHES) ?: '', 'direct.root.tail')
    || str_contains($malformedVisibleText, 'Direct root tail value must not surface')
    || str_contains($validVisibleText, 'Direct root comment value')
) {
    throw new RuntimeException('AcroForm review values leaked into visible WordPress text.');
}

$widgets = is_array($validField['widgets'] ?? null) ? $validField['widgets'] : [];
$summary = [
    'source' => 'native-pdf-catalog-direct-acroform-root-tail-boundary',
    'native_boundary' => 'Direct catalog /AcroForm dictionaries followed by stray top-level operands fail closed before form-field review; comment-only tails and following catalog keys remain valid.',
    'malformed_direct_root_tail_rejected' => $malformedForm['fields'] === [],
    'malformed_need_appearances_ignored' => $malformedForm['need_appearances'] === false,
    'malformed_value_visible' => str_contains($malformedVisibleText, 'Direct root tail value must not surface'),
    'valid_direct_root_field_preserved' => ($validField['name'] ?? null) === 'direct.root.comment',
    'valid_widget_objects' => array_column($widgets, 'object'),
    'valid_widget_page_objects' => array_column($widgets, 'page_object'),
    'valid_widget_page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
    'field_values_review_only' => !str_contains($validVisibleText, 'Direct root comment value'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-acroform-fields-direct-root-tail-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Widget</th></tr>\n";
echo '<tr><td>' . htmlspecialchars((string) ($validField['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) ($validField['field_type_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) ($validField['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars('objects ' . implode(',', array_map('strval', array_column($widgets, 'object'))), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[(string) ($field['name'] ?? '')] = $field;
    }

    return $indexed;
};

$fieldsArrayText = 'BT /F1 12 Tf 72 720 Td (Visible WordPress direct Fields array tail body) Tj ET';
$fieldsArrayPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($fieldsArrayText) . " >>\nstream\n{$fieldsArrayText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] 90 0 R /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (tailed.direct.fields.decoy) /TU (Tailed direct Fields label) /TM (tailed-direct-fields-export) /V (Tailed direct Fields value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (valid.page.repair) /TU (Valid page repair label) /TM (valid-page-repair-export) /V (Valid page repair value) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "90 0 obj\n<< /FT /Tx /T (tailed.direct.fields.tail.decoy) /V (Trailing direct Fields operand value) >>\nendobj\n"
    . "%%EOF";

$kidsArrayText = 'BT /F1 12 Tf 72 720 Td (Visible WordPress direct Kids array tail body) Tj ET';
$kidsArrayPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($kidsArrayText) . " >>\nstream\n{$kidsArrayText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (valid.parent) /TU (Valid parent label) /TM (valid-parent-export) /V (Valid parent value) /DV (Valid parent default) /Kids [10 0 R] 90 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /Parent 6 0 R /T (malformed.direct.kids.decoy) /TU (Malformed direct Kids label) /TM (malformed-direct-kids-export) /V (Malformed direct Kids value) >>\nendobj\n"
    . "90 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfAcroFormExtractor();
$textExtractor = new PdfTextExtractor();
$fieldsArrayForm = $extractor->extractForm($fieldsArrayPdf);
$kidsArrayForm = $extractor->extractForm($kidsArrayPdf);
$fieldsArrayFields = $fieldsByName($fieldsArrayForm['fields']);
$kidsArrayFields = $fieldsByName($kidsArrayForm['fields']);
$fieldsArrayVisibleText = $textExtractor->extractPlainText($fieldsArrayPdf);
$kidsArrayVisibleText = $textExtractor->extractPlainText($kidsArrayPdf);
$fieldsArrayJson = json_encode($fieldsArrayForm, JSON_UNESCAPED_SLASHES) ?: '';
$kidsArrayJson = json_encode($kidsArrayForm, JSON_UNESCAPED_SLASHES) ?: '';

if (array_keys($fieldsArrayFields) !== ['valid.page.repair']) {
    throw new RuntimeException('Tailed direct AcroForm /Fields array did not fail closed before decoy review.');
}
if (array_keys($kidsArrayFields) !== ['valid.parent']) {
    throw new RuntimeException('Tailed direct AcroForm /Kids array did not fail closed before child traversal.');
}
if (str_contains($fieldsArrayJson, 'tailed.direct.fields.decoy')
    || str_contains($fieldsArrayJson, 'tailed.direct.fields.tail.decoy')
    || str_contains($kidsArrayJson, 'malformed.direct.kids.decoy')
) {
    throw new RuntimeException('Malformed AcroForm array-tail entries leaked into form output.');
}
if (str_contains($fieldsArrayVisibleText, 'Tailed direct Fields value')
    || str_contains($kidsArrayVisibleText, 'Malformed direct Kids value')
    || str_contains($fieldsArrayVisibleText, 'Valid page repair value')
    || str_contains($kidsArrayVisibleText, 'Valid parent value')
) {
    throw new RuntimeException('AcroForm review values leaked into visible WordPress text.');
}

$summary = [
    'source' => 'native-pdf-acroform-direct-array-tail-boundary',
    'native_boundary' => 'Direct /Fields and /Kids array values followed by stray top-level operands fail closed before field traversal; page-owned widget repair still recovers valid fields.',
    'direct_fields_array_tail_rejected' => true,
    'direct_kids_array_tail_rejected' => true,
    'page_widget_repair_preserved' => true,
    'valid_field_names' => [array_keys($fieldsArrayFields)[0], array_keys($kidsArrayFields)[0]],
    'fields_array_widget_objects' => array_column($fieldsArrayFields['valid.page.repair']['widgets'], 'object'),
    'kids_array_widget_objects' => array_column($kidsArrayFields['valid.parent']['widgets'], 'object'),
    'form_values_visible_in_text' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-acroform-fields-direct-array-tail-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Case</th><th>Field</th><th>Value</th><th>Widget</th></tr>\n";
foreach ([
    'Fields tail repair' => $fieldsArrayFields['valid.page.repair'],
    'Kids tail repair' => $kidsArrayFields['valid.parent'],
] as $case => $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    echo '<tr><td>' . htmlspecialchars($case, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($field['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars('objects ' . implode(',', array_map('strval', array_column($widgets, 'object'))), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

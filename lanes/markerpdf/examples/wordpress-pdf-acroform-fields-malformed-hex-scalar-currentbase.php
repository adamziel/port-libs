<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdfHexTextString = static function (string $value): string {
    $encoded = iconv('UTF-8', 'UTF-16BE', $value);
    assert(is_string($encoded));

    return '<FEFF' . strtoupper(bin2hex($encoded)) . '>';
};

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm malformed hex scalar body) Tj ET';
$fieldName = $pdfHexTextString('workflow.hex_status');
$fieldLabel = $pdfHexTextString('Hex status label');
$mappingName = $pdfHexTextString('workflow.hex_status.export');
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Ch /T {$fieldName} /TU {$fieldLabel} /TM {$mappingName} /Ff 2097152 "
    . "/V [<2F /private_choice_decoy> (publish)] /DV [<2F /draft_decoy> (draft)] "
    . "/Opt [<2F /private_choice_decoy> [(draft) (Draft label)] [(publish) (Published label)] <2F /archive_decoy>] "
    . "/Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

$field = $fieldsByName['workflow.hex_status'] ?? null;
if (!is_array($field)) {
    throw new RuntimeException('Expected malformed-hex AcroForm choice field to be present.');
}
if (($field['value'] ?? null) !== ['publish'] || ($field['default_value'] ?? null) !== ['draft']) {
    throw new RuntimeException('Malformed AcroForm hex scalar values were not skipped before field review.');
}
if (($field['options'] ?? null) !== [
    ['export' => 'draft', 'label' => 'Draft label'],
    ['export' => 'publish', 'label' => 'Published label'],
]) {
    throw new RuntimeException('Malformed AcroForm hex scalar options were not skipped before field review.');
}
foreach (['private_choice_decoy', 'draft_decoy', 'archive_decoy'] as $decoy) {
    if (str_contains((string) json_encode($form, JSON_UNESCAPED_SLASHES), $decoy)) {
        throw new RuntimeException("Malformed hex scalar decoy leaked into form review metadata: {$decoy}");
    }
    if (str_contains($visibleText, $decoy)) {
        throw new RuntimeException("Malformed hex scalar decoy leaked into visible text: {$decoy}");
    }
}
if (str_contains($visibleText, 'publish') || str_contains($visibleText, 'Hex status label')) {
    throw new RuntimeException('AcroForm choice values and labels must stay review metadata, not visible WordPress text.');
}

$rows = [[
    'name' => $field['name'] ?? null,
    'label' => $field['field_name_review']['wordpress_label'] ?? null,
    'value' => $field['value_state']['display_value'] ?? null,
    'default' => implode(', ', array_map('strval', $field['default_value'] ?? [])),
    'options' => implode(', ', array_map(
        static fn (array $option): string => $option['export'] . ':' . $option['label'],
        is_array($field['options'] ?? null) ? $field['options'] : []
    )),
]];

echo '<!-- markerpdf:pdf-acroform-fields-malformed-hex-scalar-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-malformed-hex-scalar-boundary',
    'native_boundary' => 'Malformed PDF hex-string operands inside AcroForm choice scalar arrays are consumed fail-closed before /V, /DV, and /Opt review metadata, so embedded name tokens cannot become WordPress form values.',
    'field_names' => array_column($rows, 'name'),
    'valid_utf16_field_names_preserved' => ($field['name'] ?? null) === 'workflow.hex_status'
        && ($field['alternate_name'] ?? null) === 'Hex status label',
    'malformed_hex_choice_values_excluded' => ($field['value'] ?? null) === ['publish'],
    'malformed_hex_default_values_excluded' => ($field['default_value'] ?? null) === ['draft'],
    'malformed_hex_options_excluded' => ($field['options'] ?? null) === [
        ['export' => 'draft', 'label' => 'Draft label'],
        ['export' => 'publish', 'label' => 'Published label'],
    ],
    'decoy_names_excluded' => !str_contains((string) json_encode($form, JSON_UNESCAPED_SLASHES), 'private_choice_decoy')
        && !str_contains((string) json_encode($form, JSON_UNESCAPED_SLASHES), 'draft_decoy')
        && !str_contains((string) json_encode($form, JSON_UNESCAPED_SLASHES), 'archive_decoy'),
    'field_values_visible_in_text' => str_contains($visibleText, 'publish') || str_contains($visibleText, 'Hex status label'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Label</th><th>Value</th><th>Default</th><th>Options</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['default'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['options'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

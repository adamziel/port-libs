<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm choice option operand boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Ch /T (workflow.option_boundary) /V (publish) /Opt ["
    . "[(draft) [(decoy.export) (Nested array label decoy)] << /Nested (Dictionary label decoy) >> (Draft label)] "
    . "[(publish) << /Nested (Wrong published label decoy) >> (Published label)] "
    . "(archive)"
    . "] /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

$field = $fieldsByName['workflow.option_boundary'] ?? null;
if (!is_array($field)) {
    throw new RuntimeException('Expected AcroForm choice field was not extracted.');
}

$expectedOptions = [
    ['export' => 'draft', 'label' => 'Draft label'],
    ['export' => 'publish', 'label' => 'Published label'],
    ['export' => 'archive', 'label' => 'archive'],
];
if (($field['options'] ?? null) !== $expectedOptions) {
    throw new RuntimeException('Nested AcroForm option operands leaked into option labels.');
}
if (($field['value_state']['selected_options'] ?? null) !== [['index' => 1, 'export' => 'publish', 'label' => 'Published label']]) {
    throw new RuntimeException('Selected AcroForm choice option did not use the top-level option label.');
}

$encoded = json_encode($form, JSON_UNESCAPED_SLASHES);
foreach (['decoy.export', 'Nested array label decoy', 'Dictionary label decoy', 'Wrong published label decoy'] as $decoyText) {
    if (!is_string($encoded) || str_contains($encoded, $decoyText) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException("Nested AcroForm option decoy {$decoyText} must stay excluded from WordPress review output.");
    }
}
foreach (['Draft label', 'Published label', 'archive', 'publish'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("AcroForm option text {$reviewOnlyText} must not become visible WordPress paragraph text.");
    }
}

$selectedLabels = array_column($field['value_state']['selected_options'] ?? [], 'label');
$rows = array_map(
    static fn (array $option): array => [
        'export' => $option['export'],
        'label' => $option['label'],
        'selected' => in_array($option['label'], $selectedLabels, true),
    ],
    $field['options'] ?? []
);

echo '<!-- markerpdf:pdf-acroform-fields-choice-option-operand-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-choice-option-operand-boundary',
    'native_boundary' => 'AcroForm choice /Opt tuple labels are collected from top-level scalar operands only; nested arrays and dictionaries inside an option tuple are ignored before WordPress review metadata.',
    'field_name' => $field['name'] ?? null,
    'field_value' => $field['value'] ?? null,
    'options' => $field['options'] ?? [],
    'selected_options' => $field['value_state']['selected_options'] ?? [],
    'nested_option_decoys_excluded' => true,
    'option_text_visible_in_page_text' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Export</th><th>Label</th><th>Selected</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars($row['export'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($row['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . ($row['selected'] ? 'yes' : 'no') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

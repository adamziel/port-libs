<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm scalar generation boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R] /NeedAppearances true >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T 30 1 R /TU 31 1 R /TM 32 1 R /V 33 1 R /DV 34 1 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Ch /T (profile.choice) /V 35 1 R /Opt [[36 1 R 37 1 R] [38 0 R 39 0 R]] /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /FT /Tx /T (profile.invalid) /TU 42 0 R /TM 43 0 R /V 40 0 R /DV 41 0 R /Kids [16 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 1 obj\n(profile.title)\nendobj\n"
    . "31 1 obj\n(Current title label)\nendobj\n"
    . "32 1 obj\n(profile.title.export)\nendobj\n"
    . "33 1 obj\n(Current title value)\nendobj\n"
    . "34 1 obj\n(Default title value)\nendobj\n"
    . "35 1 obj\n(page)\nendobj\n"
    . "36 1 obj\n(post)\nendobj\n"
    . "37 1 obj\n(Post label)\nendobj\n"
    . "38 1 obj\n(stale option export must not surface)\nendobj\n"
    . "39 1 obj\n(stale option label must not surface)\nendobj\n"
    . "40 1 obj\n(stale current value must not surface)\nendobj\n"
    . "41 1 obj\n(stale default value must not surface)\nendobj\n"
    . "42 1 obj\n(Stale alternate label must not surface)\nendobj\n"
    . "43 1 obj\n(stale.mapping.must.not.surface)\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['profile.title', 'profile.choice', 'profile.invalid'] as $fieldName) {
    if (!isset($fieldsByName[$fieldName])) {
        throw new RuntimeException("Expected AcroForm field {$fieldName} to be present.");
    }
}

$title = $fieldsByName['profile.title'];
$choice = $fieldsByName['profile.choice'];
$invalid = $fieldsByName['profile.invalid'];

if (($title['value'] ?? null) !== 'Current title value' || ($title['default_value'] ?? null) !== 'Default title value') {
    throw new RuntimeException('Exact-generation indirect AcroForm title values were not resolved.');
}
if (($title['alternate_name'] ?? null) !== 'Current title label' || ($title['mapping_name'] ?? null) !== 'profile.title.export') {
    throw new RuntimeException('Exact-generation indirect AcroForm title names were not resolved.');
}
if (($choice['options'] ?? null) !== [['export' => 'post', 'label' => 'Post label']]) {
    throw new RuntimeException('Generation-mismatched indirect AcroForm option strings were not excluded.');
}
if (($invalid['value'] ?? null) !== null || ($invalid['default_value'] ?? null) !== null || ($invalid['alternate_name'] ?? null) !== null) {
    throw new RuntimeException('Generation-mismatched indirect AcroForm scalar operands must stay unresolved.');
}

$staleTexts = [
    'stale option export must not surface',
    'stale option label must not surface',
    'stale current value must not surface',
    'stale default value must not surface',
    'Stale alternate label must not surface',
    'stale.mapping.must.not.surface',
];
$encoded = json_encode($form, JSON_UNESCAPED_SLASHES);
foreach ($staleTexts as $staleText) {
    if (is_string($encoded) && str_contains($encoded, $staleText)) {
        throw new RuntimeException("Stale AcroForm scalar operand leaked into review metadata: {$staleText}");
    }
    if (str_contains($visibleText, $staleText)) {
        throw new RuntimeException("Stale AcroForm scalar operand leaked into visible text: {$staleText}");
    }
}
if (str_contains($visibleText, 'Current title value') || str_contains($visibleText, 'Default title value')) {
    throw new RuntimeException('AcroForm field values must remain review metadata, not visible WordPress text.');
}

$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'label' => $field['field_name_review']['wordpress_label'] ?? $field['alternate_name'] ?? $field['mapping_name'] ?? $field['name'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'object' => $field['object'] ?? null,
        'widget_objects' => array_column($widgets, 'object'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-scalar-generation-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-indirect-scalar-generation-boundary',
    'native_boundary' => 'AcroForm indirect scalar operands for field names, alternate names, mapping names, current/default values, and choice options resolve only when object generations match.',
    'field_names' => array_column($rows, 'name'),
    'exact_indirect_scalars_resolved' => ($title['name'] ?? null) === 'profile.title'
        && ($title['alternate_name'] ?? null) === 'Current title label'
        && ($title['mapping_name'] ?? null) === 'profile.title.export'
        && ($title['value'] ?? null) === 'Current title value'
        && ($title['default_value'] ?? null) === 'Default title value',
    'generation_mismatched_option_excluded' => ($choice['options'] ?? null) === [['export' => 'post', 'label' => 'Post label']],
    'generation_mismatched_value_unresolved' => ($invalid['value'] ?? null) === null && ($invalid['default_value'] ?? null) === null,
    'stale_scalar_operands_excluded' => is_string($encoded)
        && count(array_filter($staleTexts, static fn (string $text): bool => str_contains($encoded, $text) || str_contains($visibleText, $text))) === 0,
    'form_values_visible_in_text' => str_contains($visibleText, 'Current title value')
        || str_contains($visibleText, 'Default title value'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Label</th><th>Value</th><th>Widgets</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(
        'field object ' . (string) $row['object'] . '; widget objects ' . implode(',', array_map('strval', $row['widget_objects'])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

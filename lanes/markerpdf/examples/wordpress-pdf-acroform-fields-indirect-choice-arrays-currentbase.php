<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect choice array body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 22 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 20 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Ch /T (workflow.indirect_arrays) /Ff 2097152 /V 30 1 R /DV 31 1 R /Opt 32 1 R /I 33 1 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n<< /FT /Ch /T (workflow.stale_arrays) /V 40 1 R /DV 41 1 R /Opt 42 1 R /I 43 1 R /Kids [22 0 R] >>\nendobj\n"
    . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 1 obj\n[(publish) (archive)]\nendobj\n"
    . "31 1 obj\n[(draft)]\nendobj\n"
    . "32 1 obj\n[[(draft) (Draft label)] [(review) (Review label)] [(publish) (Published label)] [(archive) (Archived label)]]\nendobj\n"
    . "33 1 obj\n[2 3]\nendobj\n"
    . "40 0 obj\n[(stale current choice must not surface)]\nendobj\n"
    . "41 0 obj\n[(stale default choice must not surface)]\nendobj\n"
    . "42 0 obj\n[[(stale option export must not surface) (Stale option label must not surface)]]\nendobj\n"
    . "43 0 obj\n[0]\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['workflow.indirect_arrays', 'workflow.stale_arrays'] as $fieldName) {
    if (!isset($fieldsByName[$fieldName])) {
        throw new RuntimeException("Expected AcroForm field {$fieldName} to be present.");
    }
}

$workflow = $fieldsByName['workflow.indirect_arrays'];
$stale = $fieldsByName['workflow.stale_arrays'];
$encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

if (($workflow['value'] ?? null) !== ['publish', 'archive']) {
    throw new RuntimeException('Indirect AcroForm choice current-value array did not resolve.');
}
if (($workflow['default_value'] ?? null) !== ['draft']) {
    throw new RuntimeException('Indirect AcroForm choice default-value array did not resolve.');
}
if (($workflow['options'] ?? null) !== [
    ['export' => 'draft', 'label' => 'Draft label'],
    ['export' => 'review', 'label' => 'Review label'],
    ['export' => 'publish', 'label' => 'Published label'],
    ['export' => 'archive', 'label' => 'Archived label'],
]) {
    throw new RuntimeException('Indirect AcroForm choice option array did not resolve.');
}
if (($workflow['value_state']['selected_indices'] ?? null) !== [2, 3]) {
    throw new RuntimeException('Indirect AcroForm choice selected-index array did not resolve.');
}
if (($stale['value'] ?? null) !== null || ($stale['options'] ?? null) !== []) {
    throw new RuntimeException('Generation-mismatched indirect AcroForm arrays must stay unresolved.');
}

$staleTexts = [
    'stale current choice must not surface',
    'stale default choice must not surface',
    'stale option export must not surface',
    'Stale option label must not surface',
];
foreach ($staleTexts as $staleText) {
    if (is_string($encoded) && str_contains($encoded, $staleText)) {
        throw new RuntimeException("Stale AcroForm indirect array leaked into review metadata: {$staleText}");
    }
    if (str_contains($visibleText, $staleText)) {
        throw new RuntimeException("Stale AcroForm indirect array leaked into visible text: {$staleText}");
    }
}
if (str_contains($visibleText, 'publish') || str_contains($visibleText, 'Draft label')) {
    throw new RuntimeException('AcroForm indirect choice arrays must stay review metadata, not visible WordPress text.');
}

$rows = [];
foreach ($form['fields'] as $field) {
    $state = is_array($field['value_state'] ?? null) ? $field['value_state'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'value' => $state['display_value'] ?? null,
        'default' => is_array($field['default_value'] ?? null) ? implode(', ', $field['default_value']) : ($field['default_value'] ?? null),
        'options' => implode(', ', array_map(
            static fn (array $option): string => $option['export'] . ':' . $option['label'],
            is_array($field['options'] ?? null) ? $field['options'] : []
        )),
        'selected' => implode(',', array_map('strval', $state['selected_indices'] ?? [])),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-indirect-choice-arrays-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-indirect-choice-array-boundary',
    'native_boundary' => 'Generation-exact indirect AcroForm choice arrays resolve for /V, /DV, /Opt, and /I before WordPress form review; stale generation array objects stay unresolved.',
    'field_names' => array_column($rows, 'name'),
    'indirect_current_values_resolved' => ($workflow['value'] ?? null) === ['publish', 'archive'],
    'indirect_default_values_resolved' => ($workflow['default_value'] ?? null) === ['draft'],
    'indirect_options_resolved' => count($workflow['options'] ?? []) === 4,
    'indirect_selected_indices_resolved' => ($workflow['value_state']['selected_indices'] ?? null) === [2, 3],
    'stale_generation_arrays_excluded' => ($stale['value'] ?? null) === null && ($stale['options'] ?? null) === [],
    'form_values_visible_in_text' => str_contains($visibleText, 'publish') || str_contains($visibleText, 'Draft label'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Value</th><th>Default</th><th>Selected</th><th>Options</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['default'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['selected'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['options'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

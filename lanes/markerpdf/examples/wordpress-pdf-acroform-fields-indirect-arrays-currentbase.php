<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect array boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 15 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields 20 0 R /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.indirect) /V (Indirect field array title) /Kids 21 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "12 0 obj\n<< /FT /Tx /T (metadata.hidden) /Ff 4 /V (Metadata-only indirect value) >>\nendobj\n"
    . "15 0 obj\n<< /Subtype /Widget /Parent 42 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n[6 0 R 12 0 R 40 0 R]\nendobj\n"
    . "21 0 obj\n[8 0 R]\nendobj\n"
    . "40 0 obj\n<< /FT /Tx /T (profile) /V (Inherited profile value) /Kids 41 0 R >>\nendobj\n"
    . "41 0 obj\n[42 0 R]\nendobj\n"
    . "42 0 obj\n<< /T (name) /Kids 43 0 R >>\nendobj\n"
    . "43 0 obj\n[15 0 R]\nendobj\n"
    . "99 0 obj\n[101 0 R]\nendobj\n"
    . "101 0 obj\n<< /FT /Tx /T (detached.indirect.decoy) /V (Detached indirect decoy) >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['article.indirect', 'metadata.hidden', 'profile.name'] as $name) {
    if (!isset($fieldsByName[$name])) {
        throw new RuntimeException("Missing indirect-array AcroForm field {$name}.");
    }
}
if (isset($fieldsByName['detached.indirect.decoy'])) {
    throw new RuntimeException('Detached indirect array object must not be promoted into AcroForm review.');
}

$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'source' => $field['value_state']['hierarchy_boundary']['current_value_source'] ?? null,
        'widget_objects' => array_column($widgets, 'object'),
        'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-indirect-arrays-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-indirect-array-boundary',
    'native_boundary' => 'AcroForm /Fields and nested /Kids arrays stored as indirect objects resolve before WordPress field review while detached indirect arrays remain excluded.',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'metadata_only_field_preserved' => isset($fieldsByName['metadata.hidden']) && ($fieldsByName['metadata.hidden']['widgets'] ?? []) === [],
    'nested_indirect_kids_name' => $fieldsByName['profile.name']['name'] ?? null,
    'nested_value_source' => $fieldsByName['profile.name']['value_state']['hierarchy_boundary']['current_value_source'] ?? null,
    'detached_indirect_decoy_excluded' => !isset($fieldsByName['detached.indirect.decoy']),
    'visible_text_contains_form_value' => str_contains($visibleText, 'Indirect field array title')
        || str_contains($visibleText, 'Metadata-only indirect value')
        || str_contains($visibleText, 'Inherited profile value'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Review value</th><th>Widgets</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'] . ' [' . (string) $row['source'] . ']', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(
        'objects ' . implode(',', array_map('strval', $row['widget_objects'])) . '; page annotation indexes ' . implode(',', array_map('strval', $row['page_annotation_indexes'])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

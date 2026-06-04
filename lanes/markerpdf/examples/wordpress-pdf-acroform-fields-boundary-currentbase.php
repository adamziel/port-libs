<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm page widget boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 14 0 R 18 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) /DR << /Font << /Helv 40 0 R >> >> >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (listed.email) /V (listed@example.test) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Ch /T (omitted.category) /V (page) /Opt [(post) (page)] /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /FT /Tx /T (inline.note) /V (inline page widget value) /Rect [72 560 320 584] /P 3 0 R /F 4 /DA (/Helv 8 Tf 0 0 1 rg) >>\nendobj\n"
    . "16 0 obj\n<< /FT /Tx /T (indirect.geometry) /V (indirect geometry value) /Kids [18 0 R] >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [50 0 R 51 0 R 52 0 R 53 0 R] /P 3 0 R /F 54 0 R >>\nendobj\n"
    . "20 0 obj\n<< /FT /Tx /T (detached.secret) /V (detached widget value must not surface) /Kids [22 0 R] >>\nendobj\n"
    . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 520 320 544] /F 4 >>\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "50 0 obj\n360\nendobj\n"
    . "51 0 obj\n544\nendobj\n"
    . "52 0 obj\n72\nendobj\n"
    . "53 0 obj\n520\nendobj\n"
    . "54 0 obj\n4\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['listed.email', 'omitted.category', 'inline.note', 'indirect.geometry'] as $name) {
    if (!isset($fieldsByName[$name])) {
        throw new RuntimeException("Missing expected AcroForm field {$name}.");
    }
}
if (isset($fieldsByName['detached.secret'])) {
    throw new RuntimeException('Detached widget field must not be promoted into the AcroForm review.');
}

$indirectWidget = $fieldsByName['indirect.geometry']['widgets'][0] ?? null;
if (!is_array($indirectWidget)) {
    throw new RuntimeException('Missing expected indirect-operand AcroForm widget.');
}
if (($indirectWidget['rect'] ?? null) !== [72.0, 520.0, 360.0, 544.0]) {
    throw new RuntimeException('Indirect AcroForm widget Rect operands were not resolved.');
}
if (($indirectWidget['annotation_flags'] ?? null) !== 4 || ($indirectWidget['annotation_visibility'] ?? null) !== 'visible') {
    throw new RuntimeException('Indirect AcroForm widget annotation flags were not resolved.');
}

$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'object' => $field['object'] ?? null,
        'widget_objects' => array_column($widgets, 'object'),
        'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
        'page_referenced_widgets' => count(array_filter(
            $widgets,
            static fn (array $widget): bool => ($widget['referenced_from_page_annots'] ?? false) === true
        )),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-page-widget-boundary',
    'native_boundary' => 'Page-owned Widget annotations and their Parent fields are reviewed when malformed AcroForm Fields omits them; detached widgets remain excluded',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'promoted_page_widget_parent_fields' => ['omitted.category'],
    'promoted_standalone_widget_fields' => ['inline.note'],
    'indirect_widget_rect_resolved' => ($indirectWidget['rect'] ?? null) === [72.0, 520.0, 360.0, 544.0],
    'indirect_widget_flags_resolved' => ($indirectWidget['annotation_flags'] ?? null) === 4,
    'indirect_widget_visibility' => $indirectWidget['annotation_visibility'] ?? null,
    'detached_widget_excluded' => !isset($fieldsByName['detached.secret']),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Widgets</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(
        'objects ' . implode(',', array_map('strval', $row['widget_objects'])) . '; page annotation indexes ' . implode(',', array_map('strval', $row['page_annotation_indexes'])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

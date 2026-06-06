<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm stream object boundary body) Tj ET';
$fieldStream = 'BT /F1 12 Tf 72 680 Td (Stream field payload leak) Tj ET';
$widgetStream = 'BT /F1 12 Tf 72 660 Td (Stream widget payload leak) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 14 0 R 16 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 16 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (stream.root.decoy) /V (Stream root value) /Kids [8 0 R] /Length " . strlen($fieldStream) . " >>\nstream\n{$fieldStream}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 /Length " . strlen($widgetStream) . " >>\nstream\n{$widgetStream}\nendstream\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (normal.field) /V (Normal value) /Kids [12 0 R 14 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 /Length " . strlen($widgetStream) . " >>\nstream\n{$widgetStream}\nendstream\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /FT /Tx /T (stream.inline.decoy) /V (Stream inline value) /Rect [72 520 320 544] /P 3 0 R /F 4 /Length " . strlen($widgetStream) . " >>\nstream\n{$widgetStream}\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['normal.field']) {
    throw new RuntimeException('Stream objects must not be promoted as AcroForm field dictionaries.');
}

$normal = $fieldsByName['normal.field'];
if (array_column($normal['widgets'] ?? [], 'object') !== [12]) {
    throw new RuntimeException('Stream objects must not be promoted as AcroForm widget dictionaries.');
}

$encoded = json_encode($form, JSON_UNESCAPED_SLASHES);
if (!is_string($encoded)) {
    throw new RuntimeException('Unable to encode AcroForm review metadata.');
}

$decoyTexts = [
    'stream.root.decoy',
    'stream.inline.decoy',
    'Stream root value',
    'Stream inline value',
    'Stream field payload leak',
    'Stream widget payload leak',
];
foreach ($decoyTexts as $decoyText) {
    if (str_contains($encoded, $decoyText) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException("Stream object decoy leaked into WordPress review: {$decoyText}");
    }
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
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-stream-object-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-stream-object-boundary',
    'native_boundary' => 'AcroForm field and Widget dictionaries are ordinary dictionaries; stream objects referenced from /Fields, /Kids, or page /Annots remain excluded from WordPress form review metadata and visible text',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'stream_root_field_excluded' => !isset($fieldsByName['stream.root.decoy']),
    'stream_inline_widget_excluded' => !isset($fieldsByName['stream.inline.decoy']),
    'stream_child_widget_excluded' => array_column($normal['widgets'] ?? [], 'object') === [12],
    'stream_payload_text_excluded' => !str_contains($visibleText, 'Stream field payload leak')
        && !str_contains($visibleText, 'Stream widget payload leak'),
    'form_values_review_only' => !str_contains($visibleText, 'Normal value'),
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

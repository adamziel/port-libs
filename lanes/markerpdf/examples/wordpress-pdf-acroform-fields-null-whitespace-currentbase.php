<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$nul = "\0";
$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm null whitespace body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5{$nul}0{$nul}R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8{$nul}0{$nul}R 12{$nul}0{$nul}R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6{$nul}0{$nul}R 10{$nul}0{$nul}R (99{$nul}0{$nul}R literal decoy)] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (nullws.email) /TU (Null whitespace email label) /TM (nullws.email.export) /V (nullws@example.test) /DV (draft-nullws@example.test) /MaxLen 64 /Kids [8{$nul}0{$nul}R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6{$nul}0{$nul}R /Rect [320{$nul}664{$nul}72{$nul}640] /P 3{$nul}0{$nul}R /F 34{$nul}0{$nul}R >>\nendobj\n"
    . "10 0 obj\n<< /FT /Ch /T (nullws.status) /V (publish) /Opt [(draft) (publish)] /Kids [12{$nul}0{$nul}R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10{$nul}0{$nul}R /Rect [72{$nul}600{$nul}260{$nul}624] /P 3{$nul}0{$nul}R /F 4 >>\nendobj\n"
    . "34 0 obj\n4\nendobj\n"
    . "99 0 obj\n<< /FT /Tx /T (nullws.literal.decoy) /V (NUL whitespace literal decoy) >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['nullws.email', 'nullws.status'] as $fieldName) {
    if (!isset($fieldsByName[$fieldName])) {
        throw new RuntimeException("Missing NUL-whitespace AcroForm field {$fieldName}.");
    }
}
if (isset($fieldsByName['nullws.literal.decoy'])) {
    throw new RuntimeException('Literal NUL-whitespace field reference decoy must not be promoted.');
}

$email = $fieldsByName['nullws.email'];
$status = $fieldsByName['nullws.status'];
$emailWidget = $email['widgets'][0] ?? null;
$statusWidget = $status['widgets'][0] ?? null;
if (!is_array($emailWidget) || !is_array($statusWidget)) {
    throw new RuntimeException('NUL-whitespace AcroForm widgets were not resolved.');
}
if (($emailWidget['rect'] ?? null) !== [72.0, 640.0, 320.0, 664.0] || ($emailWidget['annotation_flags'] ?? null) !== 4) {
    throw new RuntimeException('NUL-whitespace widget Rect or F operands were not resolved.');
}
if (trim($visibleText) !== 'Visible AcroForm null whitespace body') {
    throw new RuntimeException('AcroForm field values leaked into visible WordPress text.');
}

$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'widgets' => array_column($widgets, 'object'),
        'page_annotation_indexes' => array_column($widgets, 'page_annotation_index'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-null-whitespace-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-null-whitespace-boundary',
    'native_boundary' => 'PDF NUL bytes are treated as whitespace inside AcroForm references and widget numeric arrays before WordPress field review.',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'catalog_acroform_reference_resolved' => count($form['fields']) === 2,
    'fields_array_references_resolved' => isset($fieldsByName['nullws.email']) && isset($fieldsByName['nullws.status']),
    'page_annots_references_resolved' => array_column($email['widgets'] ?? [], 'referenced_from_page_annots') === [true]
        && array_column($status['widgets'] ?? [], 'referenced_from_page_annots') === [true],
    'widget_parent_references_resolved' => array_column($email['widgets'] ?? [], 'object') === [8]
        && array_column($status['widgets'] ?? [], 'object') === [12],
    'widget_page_references_resolved' => array_column($email['widgets'] ?? [], 'page_object') === [3]
        && array_column($status['widgets'] ?? [], 'page_object') === [3],
    'widget_rects_resolved' => ($emailWidget['rect'] ?? null) === [72.0, 640.0, 320.0, 664.0]
        && ($statusWidget['rect'] ?? null) === [72.0, 600.0, 260.0, 624.0],
    'widget_flags_resolved' => ($emailWidget['annotation_flags'] ?? null) === 4,
    'literal_reference_decoy_excluded' => !isset($fieldsByName['nullws.literal.decoy']),
    'form_values_visible_in_text' => str_contains($visibleText, 'nullws@example.test')
        || str_contains($visibleText, 'publish')
        || str_contains($visibleText, 'NUL whitespace literal decoy'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Widget objects</th><th>Page annotations</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $row['widgets'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $row['page_annotation_indexes'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

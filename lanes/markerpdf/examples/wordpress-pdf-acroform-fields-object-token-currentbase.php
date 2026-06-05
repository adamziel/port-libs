<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm object token boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.endobj.title) /TU (Editor label with endobj token) /TM (article.endobj.export) /V (Current value with endobj token) /DV (Default value with endobj token) /MaxLen 64 /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 /MK << /CA (Caption with endobj token) >> >>\nendobj\n"
    . "10 0 obj\n<< /FT /Ch /T (choice.endobj.status) /V (publish) /Opt [(draft endobj option) (publish)] /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "99 0 obj\n<< /FT /Tx /T (decoy.after.object.token) /V (Decoy after object token value) >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['article.endobj.title', 'choice.endobj.status'] as $fieldName) {
    if (!isset($fieldsByName[$fieldName])) {
        throw new RuntimeException("Missing expected AcroForm field {$fieldName}.");
    }
}
if (isset($fieldsByName['decoy.after.object.token'])) {
    throw new RuntimeException('Unlisted object-token decoy field must not enter AcroForm review metadata.');
}

$article = $fieldsByName['article.endobj.title'];
$articleWidget = $article['widgets'][0] ?? null;
if (!is_array($articleWidget)) {
    throw new RuntimeException('Missing expected object-token AcroForm widget.');
}
if (($article['value'] ?? null) !== 'Current value with endobj token') {
    throw new RuntimeException('AcroForm literal endobj current value was not preserved.');
}
if (($article['default_value'] ?? null) !== 'Default value with endobj token') {
    throw new RuntimeException('AcroForm literal endobj default value was not preserved.');
}
if (($articleWidget['appearance_characteristics']['normal_caption'] ?? null) !== 'Caption with endobj token') {
    throw new RuntimeException('AcroForm widget caption with literal endobj token was not preserved as review metadata.');
}
if ($visibleText !== 'Visible AcroForm object token boundary body') {
    throw new RuntimeException('AcroForm object-token review metadata leaked into visible WordPress text.');
}

$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'widgets' => array_column($widgets, 'object'),
    ];
}

$summary = [
    'source' => 'native-pdf-acroform-object-token-boundary',
    'native_boundary' => 'AcroForm direct object collection skips PDF strings, dictionaries, arrays, comments, hex strings, and streams before accepting endobj, so literal field text containing endobj remains review metadata.',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'literal_endobj_field_name_preserved' => isset($fieldsByName['article.endobj.title']),
    'literal_endobj_current_value_preserved' => ($article['value'] ?? null) === 'Current value with endobj token',
    'literal_endobj_default_value_preserved' => ($article['default_value'] ?? null) === 'Default value with endobj token',
    'literal_endobj_widget_caption_reviewed' => ($articleWidget['appearance_characteristics']['normal_caption'] ?? null) === 'Caption with endobj token',
    'decoy_after_object_token_excluded' => !isset($fieldsByName['decoy.after.object.token']),
    'form_values_visible_in_text' => str_contains($visibleText, 'Current value with endobj token')
        || str_contains($visibleText, 'Default value with endobj token')
        || str_contains($visibleText, 'Caption with endobj token')
        || str_contains($visibleText, 'Decoy after object token value'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-acroform-fields-object-token-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Widget objects</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $row['widgets'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

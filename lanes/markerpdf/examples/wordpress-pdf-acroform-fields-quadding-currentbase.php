<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm quadding boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 13 0 R 17 0 R 21 0 R 25 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R 18 0 R 22 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) /Q 1 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (site.title) /V (Site title value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (article) /V (Parent alignment value must stay review only) /Q 2 /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Parent 10 0 R /T (summary) /V (Summary value) /Kids [13 0 R] >>\nendobj\n"
    . "13 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /FT /Tx /T (article.caption) /Q 30 1 R /V (Caption value) /Kids [17 0 R] >>\nendobj\n"
    . "17 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "18 0 obj\n<< /FT /Tx /T (article.invalid) /Q 9 /V (Invalid alignment value) /Kids [21 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 520 320 544] /P 3 0 R /F 4 >>\nendobj\n"
    . "22 0 obj\n<< /FT /Tx /T (article.unresolved) /Q 31 0 R /V (Unresolved alignment value) /Kids [25 0 R] >>\nendobj\n"
    . "25 0 obj\n<< /Subtype /Widget /Parent 22 0 R /Rect [72 480 320 504] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 1 obj\n0\nendobj\n"
    . "30 0 obj\n2\nendobj\n"
    . "31 1 obj\n2\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

$expectedFields = ['site.title', 'article.summary', 'article.caption', 'article.invalid', 'article.unresolved'];
if (array_keys($fieldsByName) !== $expectedFields) {
    throw new RuntimeException('Expected AcroForm quadding field set was not extracted.');
}

$expectedLabels = [
    'site.title' => 'center',
    'article.summary' => 'right',
    'article.caption' => 'left',
    'article.invalid' => 'unknown',
    'article.unresolved' => null,
];
foreach ($expectedLabels as $fieldName => $alignment) {
    if (($fieldsByName[$fieldName]['text_alignment'] ?? null) !== $alignment) {
        throw new RuntimeException("Unexpected text alignment for {$fieldName}.");
    }
}

if (($fieldsByName['article.caption']['quadding_review']['quadding_source_boundary'] ?? null) !== 'field_terminal') {
    throw new RuntimeException('Expected generation-exact terminal /Q value for article.caption.');
}
if (($fieldsByName['article.summary']['quadding_review']['quadding_source_boundary'] ?? null) !== 'field_hierarchy_inherited') {
    throw new RuntimeException('Expected inherited parent /Q value for article.summary.');
}
if (($fieldsByName['article.unresolved']['quadding_review']['quadding_resolved'] ?? null) !== false) {
    throw new RuntimeException('Expected generation-mismatched /Q reference to remain unresolved.');
}

foreach (['Site title value', 'Summary value', 'Caption value', 'Invalid alignment value', 'Unresolved alignment value'] as $formText) {
    if (str_contains($visibleText, $formText)) {
        throw new RuntimeException('AcroForm field values must remain review metadata, not visible WordPress text.');
    }
}

$rows = [];
foreach ($fieldsByName as $field) {
    $review = is_array($field['quadding_review'] ?? null) ? $field['quadding_review'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'alignment' => $field['text_alignment'] ?? null,
        'quadding' => $field['quadding'] ?? null,
        'source' => $review['quadding_source_boundary'] ?? null,
        'valid' => $review['quadding_valid'] ?? null,
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-quadding-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-field-quadding-boundary',
    'native_boundary' => 'AcroForm /Q text quadding resolves through form defaults, parent fields, terminal fields, and exact-generation numeric references as review metadata before WordPress import.',
    'field_names' => array_keys($fieldsByName),
    'alignment_labels' => array_column($rows, 'alignment', 'name'),
    'terminal_generation_quadding_resolved' => ($fieldsByName['article.caption']['quadding'] ?? null) === 0,
    'parent_quadding_inherited' => ($fieldsByName['article.summary']['quadding_review']['quadding_inherited'] ?? null) === true,
    'invalid_quadding_marked_unknown' => ($fieldsByName['article.invalid']['text_alignment'] ?? null) === 'unknown',
    'unresolved_generation_quadding_rejected' => ($fieldsByName['article.unresolved']['quadding_review']['quadding_resolved'] ?? null) === false,
    'form_values_visible_in_text' => str_contains($visibleText, 'Site title value') || str_contains($visibleText, 'Summary value'),
    'quadding_used_for_visible_text' => false,
    'appearance_alignment_used_for_import' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Alignment</th><th>Q</th><th>Source</th><th>Valid</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($row['alignment'] ?? 'unresolved'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($row['quadding'] === null ? 'null' : (string) $row['quadding'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($row['source'] ?? 'none'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(var_export($row['valid'], true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

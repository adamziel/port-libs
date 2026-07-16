<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm choice top index boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R 22 0 R 32 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [12 0 R 20 0 R 30 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "10 0 obj\n<< /FT /Ch /T (workflow) /Ff 2097152 /V [(publish) (archive)] /I [2 3] /Opt [[(draft) (Draft)] [(review) (Review)] [(publish) (Published)] [(archive) (Archived)]] /TI 35 1 R /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Parent 10 0 R /T (status) /Kids [14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n<< /FT /Ch /T (workflow.invalid_top) /V (draft) /Opt [(draft) (review)] /TI 9 /Kids [22 0 R] >>\nendobj\n"
    . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /FT /Ch /T (workflow.unresolved_top) /V (review) /Opt [(draft) (review)] /TI 36 0 R /Kids [32 0 R] >>\nendobj\n"
    . "32 0 obj\n<< /Subtype /Widget /Parent 30 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "35 1 obj\n2\nendobj\n"
    . "35 0 obj\n0\nendobj\n"
    . "36 1 obj\n1\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

$expectedFields = ['workflow.status', 'workflow.invalid_top', 'workflow.unresolved_top'];
if (array_keys($fieldsByName) !== $expectedFields) {
    throw new RuntimeException('Expected AcroForm choice top-index field set was not extracted.');
}

$statusReview = $fieldsByName['workflow.status']['choice_top_index_review'] ?? null;
$invalidReview = $fieldsByName['workflow.invalid_top']['choice_top_index_review'] ?? null;
$unresolvedReview = $fieldsByName['workflow.unresolved_top']['choice_top_index_review'] ?? null;
if (!is_array($statusReview) || !is_array($invalidReview) || !is_array($unresolvedReview)) {
    throw new RuntimeException('Expected choice top-index review metadata for every choice field.');
}
if (($statusReview['top_index'] ?? null) !== 2 || ($statusReview['top_option_label'] ?? null) !== 'Published') {
    throw new RuntimeException('Inherited /TI did not resolve to the expected top visible option.');
}
if (($statusReview['top_index_source_boundary'] ?? null) !== 'field_hierarchy_inherited') {
    throw new RuntimeException('Expected inherited parent /TI source boundary for workflow.status.');
}
if (($invalidReview['top_index_valid'] ?? null) !== false || ($invalidReview['top_index_resolved'] ?? null) !== true) {
    throw new RuntimeException('Out-of-range /TI must be resolved but invalid.');
}
if (($unresolvedReview['top_index_resolved'] ?? null) !== false) {
    throw new RuntimeException('Generation-mismatched /TI reference must remain unresolved.');
}

foreach (['publish', 'archive', 'Draft', 'Review', 'Published', 'Archived'] as $formText) {
    if (str_contains($visibleText, $formText)) {
        throw new RuntimeException('AcroForm choice option payloads must stay review metadata, not visible WordPress text.');
    }
}

$rows = [];
foreach ($fieldsByName as $field) {
    $review = is_array($field['choice_top_index_review'] ?? null) ? $field['choice_top_index_review'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'top_index' => $review['top_index'] ?? null,
        'top_label' => $review['top_option_label'] ?? null,
        'source' => $review['top_index_source_boundary'] ?? null,
        'resolved' => $review['top_index_resolved'] ?? null,
        'valid' => $review['top_index_valid'] ?? null,
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-choice-top-index-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-choice-top-index-boundary',
    'native_boundary' => 'AcroForm choice-field /TI top-index values resolve through field inheritance and exact-generation scalar references as review-only scroll-position metadata before WordPress import.',
    'field_names' => array_keys($fieldsByName),
    'inherited_top_index' => $statusReview['top_index'] ?? null,
    'inherited_top_label' => $statusReview['top_option_label'] ?? null,
    'inherited_source_boundary' => $statusReview['top_index_source_boundary'] ?? null,
    'out_of_range_marked_invalid' => ($invalidReview['top_index_valid'] ?? null) === false,
    'generation_mismatch_marked_unresolved' => ($unresolvedReview['top_index_resolved'] ?? null) === false,
    'form_options_visible_in_text' => false,
    'choice_top_index_used_for_import' => false,
    'appearance_scroll_position_used_for_import' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_appearance_streams' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Top index</th><th>Top label</th><th>Source</th><th>Resolved</th><th>Valid</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($row['top_index'] === null ? 'null' : (string) $row['top_index'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($row['top_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($row['source'] ?? 'none'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(var_export($row['resolved'], true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(var_export($row['valid'], true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

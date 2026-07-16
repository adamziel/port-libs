<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 10 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /NeedAppearances true >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (registration.email) /TU (Editor email) /Ff 2 /V (editor@example.com) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /F 4 >>\nendobj\n"
    . "9 0 obj\n<< /FT /Ch /T (content.type) /V (page) /Opt [(post) (page)] /Kids [10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 220 624] /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);

echo "<!-- markerpdf-acroform-fields-smoke " . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_javascript_or_actions' => false,
    'renders_appearance_streams' => false,
    'native_boundary' => 'AcroForm catalog field-tree extraction for WordPress import review',
    'field_count' => count($form['fields']),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Review</th></tr>\n";
foreach ($form['fields'] as $field) {
    $review = [];
    $flagNames = array_values(array_filter(
        $field['flag_names'] ?? [],
        static fn (mixed $flag): bool => is_string($flag)
    ));
    if (in_array('required', $flagNames, true)) {
        $review[] = 'required';
    }
    if (in_array('no_export', $flagNames, true)) {
        $review[] = 'not exported';
    }
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $firstWidget = $widgets[0] ?? null;
    if (is_array($firstWidget) && isset($firstWidget['page_index'])) {
        $review[] = 'page ' . ((int) $firstWidget['page_index'] + 1);
    }

    echo '<tr><td>' . htmlspecialchars((string) $field['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($field['field_type_label'] ?? $field['field_type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($field['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(', ', $review), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

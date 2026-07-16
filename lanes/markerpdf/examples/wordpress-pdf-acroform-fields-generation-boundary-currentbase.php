<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[(string) ($field['name'] ?? '')] = $field;
    }

    return $indexed;
};

$mismatchPageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm generation mismatch body) Tj ET';
$mismatchPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 1 R 12 1 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($mismatchPageText) . " >>\nstream\n{$mismatchPageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 1 R] /NeedAppearances true >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (stale.generation.listed) /V (stale listed value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (stale.page.widget.parent) /V (stale parent value) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 1 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$exactPageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm generation exact body) Tj ET';
$exactPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 1 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($exactPageText) . " >>\nstream\n{$exactPageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 1 R] /NeedAppearances true >>\nendobj\n"
    . "6 1 obj\n<< /FT /Tx /T (current.generation.email) /V (current-generation@example.test) /Kids [8 1 R] >>\nendobj\n"
    . "8 1 obj\n<< /Subtype /Widget /Parent 6 1 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (stale.generation.email) /V (stale-generation@example.test) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfAcroFormExtractor();
$mismatchForm = $extractor->extractForm($mismatchPdf);
$exactForm = $extractor->extractForm($exactPdf);
$exactFields = $fieldsByName($exactForm['fields']);

if ($mismatchForm['fields'] !== []) {
    throw new RuntimeException('Generation-mismatched AcroForm references must not import stale fields.');
}
if (!isset($exactFields['current.generation.email'])) {
    throw new RuntimeException('Exact generation-one AcroForm field was not imported.');
}
if (isset($exactFields['stale.generation.email'])) {
    throw new RuntimeException('Stale generation-zero AcroForm field must stay excluded.');
}

$mismatchText = (new PdfTextExtractor())->extractPlainText($mismatchPdf);
$exactText = (new PdfTextExtractor())->extractPlainText($exactPdf);
$exactField = $exactFields['current.generation.email'];
$widgets = is_array($exactField['widgets'] ?? null) ? $exactField['widgets'] : [];
$encodedForms = json_encode([$mismatchForm, $exactForm], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf:pdf-acroform-fields-generation-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-generation-boundary',
    'native_boundary' => 'AcroForm field roots, Kids, Widget Parent links, and page Annots are resolved only when object generations match the reference.',
    'generation_mismatch_field_count' => count($mismatchForm['fields']),
    'exact_generation_field_names' => array_keys($exactFields),
    'uses_current_generation_field' => isset($exactFields['current.generation.email']),
    'excludes_stale_generation_fields' => is_string($encodedForms)
        && !str_contains($encodedForms, 'stale.generation.listed')
        && !str_contains($encodedForms, 'stale.page.widget.parent')
        && !str_contains($encodedForms, 'stale.generation.email'),
    'visible_text_contains_form_value' => str_contains($mismatchText . $exactText, 'current-generation@example.test')
        || str_contains($mismatchText . $exactText, 'stale-generation@example.test')
        || str_contains($mismatchText . $exactText, 'stale listed value')
        || str_contains($mismatchText . $exactText, 'stale parent value'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Widgets</th></tr>\n";
echo '<tr><td>' . htmlspecialchars((string) $exactField['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) ($exactField['field_type_label'] ?? $exactField['field_type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) ($exactField['value_state']['display_value'] ?? $exactField['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars(
    'objects ' . implode(',', array_map('strval', array_column($widgets, 'object')))
    . '; page annotation indexes ' . implode(',', array_map('strval', array_column($widgets, 'page_annotation_index'))),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</td></tr>\n";
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

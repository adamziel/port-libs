<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect field type boundary body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R] /NeedAppearances true >>\nendobj\n"
    . "6 0 obj\n<< /FT 30 1 R /T (indirect.type.title) /V (Indirect type title value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT 31 1 R /T (indirect.type.category) /V (page) /Opt [(post) (page)] /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /FT 32 0 R /T (stale.type.review) /V (Stale type remains unknown review value) /Kids [16 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 1 obj\n/T#78\nendobj\n"
    . "30 0 obj\n/Sig\nendobj\n"
    . "31 1 obj\n/C#68\nendobj\n"
    . "31 0 obj\n/Btn\nendobj\n"
    . "32 1 obj\n/Tx\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[(string) $field['name']] = $field;
}

$title = $fields['indirect.type.title'] ?? [];
$choice = $fields['indirect.type.category'] ?? [];
$stale = $fields['stale.type.review'] ?? [];
$encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

if (($title['field_type_label'] ?? null) !== 'text') {
    throw new RuntimeException('Expected generation-exact indirect /FT /Tx to classify a text field.');
}
if (($choice['field_type_label'] ?? null) !== 'choice') {
    throw new RuntimeException('Expected generation-exact indirect /FT /Ch to classify a choice field.');
}
if (($stale['field_type_label'] ?? null) !== 'unknown') {
    throw new RuntimeException('Expected stale-generation indirect /FT to stay unknown.');
}
if (($choice['options'] ?? []) !== [
    ['export' => 'post', 'label' => 'post'],
    ['export' => 'page', 'label' => 'page'],
]) {
    throw new RuntimeException('Expected choice options to resolve only after indirect /FT /Ch is accepted.');
}
if (!str_contains($visibleText, 'Visible AcroForm indirect field type boundary body')) {
    throw new RuntimeException('Expected visible page text to remain available.');
}
foreach (['Indirect type title value', 'Stale type remains unknown review value'] as $hiddenValue) {
    if (str_contains($visibleText, $hiddenValue)) {
        throw new RuntimeException('Expected AcroForm field values to remain out of visible page text.');
    }
}
foreach (['/Sig', '/Btn', '30 0 R', '31 0 R', '32 0 R'] as $staleToken) {
    if (is_string($encoded) && str_contains($encoded, $staleToken)) {
        throw new RuntimeException('Expected stale-generation field-type operands to stay out of the import review.');
    }
}

echo '<!-- markerpdf:pdf-acroform-fields-indirect-type-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-fields-indirect-type-currentbase',
    'native_boundary' => 'AcroForm /FT name operands may be indirect only when object generation matches; stale generation field type names remain unknown review metadata',
    'field_names' => array_keys($fields),
    'indirect_text_type_resolved' => ($title['field_type'] ?? null) === 'Tx',
    'indirect_choice_type_resolved' => ($choice['field_type'] ?? null) === 'Ch',
    'choice_options_resolved' => $choice['options'] ?? [],
    'escaped_name_operands_decoded' => true,
    'stale_generation_field_type_rejected' => array_key_exists('field_type', $stale) && $stale['field_type'] === null,
    'field_values_visible_in_text' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><tbody>\n";
foreach ($fields as $field) {
    echo '<tr><td>' . htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td><td>'
        . htmlspecialchars((string) ($field['field_type_label'] ?? 'unknown'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</td></tr>\n";
}
echo "</tbody></table></figure>\n<!-- /wp:table -->\n";

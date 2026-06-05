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

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible calculation order generation boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 1 R 12 1 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 1 R 10 1 R] /CO [8 0 R (70 0 R stays literal) [71 0 R] << /Nested 72 0 R >> % 73 0 R stays comment\n10 0 R 99 0 R 12 1 R] >>\nendobj\n"
    . "6 1 obj\n<< /FT /Tx /T (current.total) /V (current total value) /Kids [8 1 R] >>\nendobj\n"
    . "8 1 obj\n<< /Subtype /Widget /Parent 6 1 R /Rect [72 640 260 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 1 obj\n<< /FT /Tx /T (current.amount) /V (current amount value) /Kids [12 1 R] >>\nendobj\n"
    . "12 1 obj\n<< /Subtype /Widget /Parent 10 1 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (stale.total) /V (stale total value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 520 260 544] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (stale.amount) /V (stale amount value) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 480 260 504] /P 3 0 R /F 4 >>\nendobj\n"
    . "70 0 obj\n<< /FT /Tx /T (decoy.literal) /V (literal decoy value) >>\nendobj\n"
    . "71 0 obj\n<< /FT /Tx /T (decoy.nested_array) /V (nested array decoy value) >>\nendobj\n"
    . "72 0 obj\n<< /FT /Tx /T (decoy.nested_dictionary) /V (nested dictionary decoy value) >>\nendobj\n"
    . "73 0 obj\n<< /FT /Tx /T (decoy.comment) /V (comment decoy value) >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = $fieldsByName($form['fields']);
$text = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

if (!isset($fields['current.total'], $fields['current.amount'])) {
    throw new RuntimeException('Expected current-generation AcroForm fields were not extracted.');
}
if (($form['calculation_order'][0]['object'] ?? null) !== 99 || ($form['calculation_order'][1]['object'] ?? null) !== 12) {
    throw new RuntimeException('Stale-generation calculation-order references were not filtered.');
}
if (($fields['current.total']['calculation_state']['in_calculation_order'] ?? true) !== false) {
    throw new RuntimeException('Stale widget calculation-order reference attached to the current total field.');
}
if (($fields['current.amount']['calculation_state']['calculation_order_widget_object'] ?? null) !== 12) {
    throw new RuntimeException('Exact-generation widget calculation-order reference was not preserved.');
}
foreach (['stale.total', 'stale.amount', 'decoy.literal', 'decoy.nested_array', 'decoy.nested_dictionary', 'decoy.comment'] as $forbidden) {
    if ((is_string($encoded) && str_contains($encoded, $forbidden)) || str_contains($text, $forbidden)) {
        throw new RuntimeException("Forbidden stale or decoy AcroForm value surfaced: {$forbidden}");
    }
}

echo '<!-- markerpdf:pdf-acroform-calculation-order-generation-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-calculation-order-generation-boundary',
    'native_boundary' => 'AcroForm /CO calculation-order references keep unresolved review targets but reject stale-generation refs before field calculation metadata is attached.',
    'field_names' => array_keys($fields),
    'calculation_order_objects' => array_column($form['calculation_order'], 'object'),
    'calculation_order_fields' => array_column($form['calculation_order'], 'field_name'),
    'calculation_order_target_kinds' => array_column($form['calculation_order_review'], 'target_kind'),
    'stale_generation_refs_excluded' => is_string($encoded)
        && !str_contains($encoded, 'stale.total')
        && !str_contains($encoded, 'stale.amount'),
    'unresolved_review_object_preserved' => ($form['calculation_order'][0]['object'] ?? null) === 99,
    'exact_widget_order_preserved' => ($fields['current.amount']['calculation_state']['calculation_order_widget_object'] ?? null) === 12,
    'literal_nested_comment_decoys_excluded' => is_string($encoded)
        && !str_contains($encoded, 'decoy.literal')
        && !str_contains($encoded, 'decoy.nested_array')
        && !str_contains($encoded, 'decoy.nested_dictionary')
        && !str_contains($encoded, 'decoy.comment'),
    'executes_calculations' => false,
    'executes_javascript' => false,
    'executes_form_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($fields as $field) {
    $state = is_array($field['calculation_state'] ?? null) ? $field['calculation_state'] : [];
    $parts = [
        (string) ($field['field_type_label'] ?? $field['field_type'] ?? 'field'),
        'value ' . (string) ($field['value'] ?? 'review-only'),
    ];
    if (($state['in_calculation_order'] ?? false) === true) {
        $parts[] = 'calculation order object ' . (string) ($state['calculation_order_object'] ?? 'unknown');
    } else {
        $parts[] = 'not in calculation order';
    }

    echo '<li>' . htmlspecialchars((string) $field['name'] . ': ' . implode('; ', $parts), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";

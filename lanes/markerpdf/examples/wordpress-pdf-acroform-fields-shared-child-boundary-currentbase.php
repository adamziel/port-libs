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

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm shared child boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (billing) /TU (Billing group label) /TM (billing.group.map) /V (Billing parent value) /DV (Billing default value) /MaxLen 64 /Kids [12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (shipping) /TU (Shipping group label) /TM (shipping.group.map) /V (Shipping parent value) /DV (Shipping default value) /MaxLen 48 /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /T (email) /TU (Shared child label must not surface) /TM (shared.email.export) /V (shared-child@example.test) /Kids [14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fields = $fieldsByName($form['fields']);

if (array_keys($fields) !== ['billing', 'shipping']) {
    throw new RuntimeException('Ambiguous shared child field must not be imported as a qualified AcroForm terminal.');
}

foreach (['billing.email', 'shipping.email'] as $ambiguousName) {
    if (isset($fields[$ambiguousName])) {
        throw new RuntimeException("Ambiguous AcroForm child {$ambiguousName} leaked into WordPress review metadata.");
    }
}

foreach (['Shared child label must not surface', 'shared.email.export', 'shared-child@example.test'] as $sharedChildPayload) {
    if (str_contains(json_encode($form, JSON_UNESCAPED_SLASHES) ?: '', $sharedChildPayload)) {
        throw new RuntimeException("Ambiguous shared child payload leaked into AcroForm review metadata: {$sharedChildPayload}");
    }
    if (str_contains($visibleText, $sharedChildPayload)) {
        throw new RuntimeException("Ambiguous shared child payload leaked into visible WordPress text: {$sharedChildPayload}");
    }
}

$rows = [];
foreach ($fields as $field) {
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'widgets' => array_column(is_array($field['widgets'] ?? null) ? $field['widgets'] : [], 'object'),
    ];
}

$metadata = [
    'source' => 'native-pdf-acroform-shared-child-boundary',
    'native_boundary' => 'Parentless AcroForm child dictionaries shared by multiple field parents are ambiguous and remain excluded from WordPress field review.',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'ambiguous_child_excluded' => !isset($fields['billing.email']) && !isset($fields['shipping.email']),
    'parent_fields_preserved' => isset($fields['billing']) && isset($fields['shipping']),
    'shared_child_payload_hidden' => !str_contains($visibleText, 'shared-child@example.test'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-acroform-fields-shared-child-boundary-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Review value</th><th>Widgets</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $row['widgets'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";

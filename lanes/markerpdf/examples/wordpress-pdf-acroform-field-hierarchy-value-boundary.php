<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [9 0 R 12 0 R 15 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /T (registration) /TM (Registration packet) /FT /Tx /Ff 1 /V (Inherited contact value) /DV (Draft contact value) /Kids [7 0 R 10 0 R 13 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /T (contact) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /T (email) /Kids [9 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /Subtype /Widget /Parent 8 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /T (title) /V (Child override title) /DV (Child draft title) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "13 0 obj\n<< /T (secret) /Ff 8192 /Kids [15 0 R] >>\nendobj\n"
    . "15 0 obj\n<< /Subtype /Widget /Parent 13 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$fields = (new PdfAcroFormExtractor())->extractFields($pdf);
$fieldsByName = [];
foreach ($fields as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['registration.contact.email', 'registration.title', 'registration.secret'] as $name) {
    if (!isset($fieldsByName[$name])) {
        throw new RuntimeException("Missing expected AcroForm field {$name}.");
    }
    if (!is_array($fieldsByName[$name]['field_hierarchy'] ?? null)) {
        throw new RuntimeException("Missing hierarchy boundary for {$name}.");
    }
}

$email = $fieldsByName['registration.contact.email'];
$title = $fieldsByName['registration.title'];
$secret = $fieldsByName['registration.secret'];

if (($email['value_state']['hierarchy_boundary']['current_value_source'] ?? null) !== 'field_hierarchy_inherited') {
    throw new RuntimeException('Expected email value to be inherited from the parent field dictionary.');
}
if (($title['value_state']['hierarchy_boundary']['current_value_source'] ?? null) !== 'field_terminal_override') {
    throw new RuntimeException('Expected title value to override the parent field dictionary.');
}
if (($secret['value_redacted'] ?? false) !== true || ($secret['value'] ?? null) !== null) {
    throw new RuntimeException('Expected inherited password value to stay redacted.');
}

$rows = [];
foreach ($fieldsByName as $field) {
    $hierarchy = is_array($field['field_hierarchy'] ?? null) ? $field['field_hierarchy'] : [];
    $state = is_array($field['value_state']['hierarchy_boundary'] ?? null) ? $field['value_state']['hierarchy_boundary'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'display' => $field['value_state']['display_value'] ?? null,
        'path_objects' => array_column($hierarchy['path'] ?? [], 'object'),
        'value_source' => $state['current_value_source'] ?? null,
        'inherited_value' => $state['current_value_inherited'] ?? null,
        'overrides_parent' => $state['terminal_overrides_parent_value'] ?? null,
        'redacted' => $field['value_redacted'] ?? false,
    ];
}

echo '<!-- markerpdf:pdf-acroform-field-hierarchy-value-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-field-tree',
    'native_boundary' => 'AcroForm non-terminal field dictionaries can supply inherited /FT, /Ff, /V, and /DV; terminal fields expose hierarchy review metadata before WordPress import rendering',
    'field_count' => count($fields),
    'inherited_value_fields' => array_values(array_filter(array_map(
        static fn (array $row): ?string => ($row['inherited_value'] ?? false) === true ? (string) $row['name'] : null,
        $rows
    ))),
    'terminal_override_fields' => array_values(array_filter(array_map(
        static fn (array $row): ?string => ($row['overrides_parent'] ?? false) === true ? (string) $row['name'] : null,
        $rows
    ))),
    'redacted_fields' => array_values(array_filter(array_map(
        static fn (array $row): ?string => ($row['redacted'] ?? false) === true ? (string) $row['name'] : null,
        $rows
    ))),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($rows as $row) {
    echo '<li>' . htmlspecialchars(
        (string) $row['name'] . ': ' . (string) $row['display'] . ' [' . (string) $row['value_source'] . ']',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";

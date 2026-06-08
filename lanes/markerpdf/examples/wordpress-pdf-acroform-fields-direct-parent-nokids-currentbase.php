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

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct Parent no Kids boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R 20 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [12 0 R 18 0 R] /NeedAppearances true /DA (/Fallback 9 Tf 0 0 0 rg) /DR << /Font << /Fallback 40 0 R /ParentFont 41 0 R >> >> >>\nendobj\n"
    . "12 0 obj\n<< /Parent << /FT /Tx /T (profile) /TU (Direct parent no Kids label) /TM (profile-direct-nokids-map) /Ff 4097 /V (Parent stale current value) /DV (Parent draft value) /MaxLen 80 /Q 2 /DA (/ParentFont 11 Tf 0 0 1 rg) >> /T (email) /TU (Editor email child label) /TM (profile.email.export) /V (editor@example.test) /Kids [14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "18 0 obj\n<< /Parent << /FT /Tx /T (detached.parent.decoy) /TU (Detached parent label must not surface) /TM (detached-parent-map) /DV (Detached parent default must not surface) /MaxLen 5 /Kids [] >> /FT /Tx /T (local.only) /TU (Local child label) /TM (local-only-export) /V (Local child value) /Kids [20 0 R] >>\nendobj\n"
    . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "41 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fields = $fieldsByName($form['fields']);

foreach (['profile.email', 'local.only'] as $expectedField) {
    if (!isset($fields[$expectedField])) {
        throw new RuntimeException("Missing expected AcroForm review field {$expectedField}.");
    }
}

$email = $fields['profile.email'];
$parentObject = $email['field_hierarchy']['ancestor_objects'][0] ?? null;
if (!is_int($parentObject) || $parentObject <= 41) {
    throw new RuntimeException('Direct AcroForm Parent without Kids was not materialized as a synthetic review ancestor.');
}
if (($email['default_value'] ?? null) !== 'Parent draft value' || ($email['max_length'] ?? null) !== 80) {
    throw new RuntimeException('Direct AcroForm Parent without Kids did not provide inherited review attributes.');
}
if (($email['default_appearance']['font_resource'] ?? null) !== 'ParentFont'
    || ($email['default_appearance']['font_resource_object'] ?? null) !== 41
) {
    throw new RuntimeException('Inherited direct-parent default appearance resources were not preserved.');
}
if (($email['value_state']['hierarchy_boundary']['terminal_overrides_parent_value'] ?? null) !== true) {
    throw new RuntimeException('Terminal child value override was not recorded for the direct-parent no-Kids boundary.');
}
if (($fields['local.only']['field_hierarchy']['ancestor_objects'] ?? []) !== []) {
    throw new RuntimeException('Explicit empty Kids direct parent decoy must not become a review ancestor.');
}

foreach ([
    'editor@example.test',
    'Parent stale current value',
    'Parent draft value',
    'Direct parent no Kids label',
    'Detached parent label must not surface',
    'Local child value',
] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("Review-only AcroForm text leaked into visible WordPress text: {$reviewOnlyText}");
    }
}

echo '<!-- markerpdf:pdf-acroform-fields-direct-parent-nokids-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-direct-parent-nokids-boundary',
    'field_names' => array_keys($fields),
    'synthetic_parent_object' => $parentObject,
    'profile_email_inherited_attributes' => $email['field_hierarchy']['inherited_attributes'] ?? [],
    'terminal_overrides_parent_value' => $email['value_state']['hierarchy_boundary']['terminal_overrides_parent_value'] ?? null,
    'local_empty_kids_parent_rejected' => ($fields['local.only']['field_hierarchy']['ancestor_objects'] ?? []) === [],
    'visible_text_excludes_form_values' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . " -->\n";
echo $visibleText . "\n";

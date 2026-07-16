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

$fieldsPageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm malformed indirect Fields object body) Tj ET';
$malformedFieldsPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($fieldsPageText) . " >>\nstream\n{$fieldsPageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields 20 0 R /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (valid.page.repair) /TU (Valid page repair label) /TM (valid-page-repair-export) /V (Valid page repair value) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n<< /NotFields [<< /FT /Tx /T (malformed.root.decoy) /TU (Malformed root label) /TM (malformed-root-export) /V (Malformed root decoy value) /Kids [8 0 R] >>] >>\nendobj\n"
    . "%%EOF";

$kidsPageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm malformed indirect Kids object body) Tj ET';
$malformedKidsPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($kidsPageText) . " >>\nstream\n{$kidsPageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (valid.parent) /TU (Valid parent label) /TM (valid-parent-export) /V (Valid parent value) /DV (Valid parent default) /MaxLen 48 /Kids 20 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n<< /NotKids [<< /T (malformed.child.decoy) /TU (Malformed child label) /TM (malformed-child-export) /V (Malformed child decoy value) /Kids [8 0 R] >>] >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfAcroFormExtractor();
$textExtractor = new PdfTextExtractor();
$fieldsForm = $extractor->extractForm($malformedFieldsPdf);
$kidsForm = $extractor->extractForm($malformedKidsPdf);
$fieldsText = $textExtractor->extractPlainText($malformedFieldsPdf);
$kidsText = $textExtractor->extractPlainText($malformedKidsPdf);
$fields = $fieldsByName($fieldsForm['fields']);
$kids = $fieldsByName($kidsForm['fields']);

if (array_keys($fields) !== ['valid.page.repair']) {
    throw new RuntimeException('Malformed indirect /Fields target must not promote nested dictionary field decoys.');
}
if (array_keys($kids) !== ['valid.parent']) {
    throw new RuntimeException('Malformed indirect /Kids target must not replace the terminal parent field with nested dictionary children.');
}
if (isset($fields['malformed.root.decoy']) || isset($kids['valid.parent.malformed.child.decoy'])) {
    throw new RuntimeException('Malformed indirect array-object decoys leaked into AcroForm review metadata.');
}

$encoded = json_encode([$fieldsForm, $kidsForm], JSON_UNESCAPED_SLASHES);
if (!is_string($encoded)) {
    throw new RuntimeException('Unable to encode AcroForm review output.');
}
foreach ([
    'malformed.root.decoy',
    'Malformed root decoy value',
    'malformed.child.decoy',
    'Malformed child decoy value',
] as $decoy) {
    if (str_contains($encoded, $decoy) || str_contains($fieldsText, $decoy) || str_contains($kidsText, $decoy)) {
        throw new RuntimeException("Malformed AcroForm indirect array object decoy leaked: {$decoy}");
    }
}
foreach ([
    'Valid page repair value',
    'Valid page repair label',
    'Valid parent value',
    'Valid parent default',
    'Valid parent label',
] as $reviewOnly) {
    if (str_contains($fieldsText, $reviewOnly) || str_contains($kidsText, $reviewOnly)) {
        throw new RuntimeException("AcroForm review-only field data leaked into visible text: {$reviewOnly}");
    }
}

$fieldsWidget = $fields['valid.page.repair']['widgets'][0] ?? [];
$kidsWidget = $kids['valid.parent']['widgets'][0] ?? [];
$summary = [
    'source' => 'native-pdf-acroform-indirect-array-object-boundary',
    'native_boundary' => 'Indirect AcroForm /Fields and /Kids operands are materialized only when the referenced target is an array object; dictionaries that merely contain arrays are ignored before WordPress field review.',
    'fields_target_field_names' => array_keys($fields),
    'kids_target_field_names' => array_keys($kids),
    'malformed_fields_object_decoy_excluded' => !isset($fields['malformed.root.decoy']) && !str_contains($encoded, 'malformed.root.decoy'),
    'malformed_kids_object_decoy_excluded' => !isset($kids['valid.parent.malformed.child.decoy']) && !str_contains($encoded, 'malformed.child.decoy'),
    'valid_page_widget_repair_preserved' => ($fields['valid.page.repair']['object'] ?? null) === 10
        && array_column($fields['valid.page.repair']['widgets'] ?? [], 'object') === [12],
    'terminal_parent_preserved_after_malformed_kids' => ($kids['valid.parent']['object'] ?? null) === 6
        && ($kids['valid.parent']['field_hierarchy']['ancestor_objects'] ?? []) === [],
    'fields_widget_page_annotation_index' => $fieldsWidget['page_annotation_index'] ?? null,
    'kids_widget_page_annotation_index' => $kidsWidget['page_annotation_index'] ?? null,
    'visible_text' => [$fieldsText, $kidsText],
    'visible_text_excludes_form_values' => !str_contains($fieldsText . "\n" . $kidsText, 'Valid page repair value')
        && !str_contains($fieldsText . "\n" . $kidsText, 'Valid parent value')
        && !str_contains($fieldsText . "\n" . $kidsText, 'Malformed'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
];

echo '<!-- markerpdf:pdf-acroform-fields-indirect-array-object-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

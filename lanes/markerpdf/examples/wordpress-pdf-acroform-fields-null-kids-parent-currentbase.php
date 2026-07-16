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

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm null Kids parent WordPress body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R 24 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (nullkids.email) /TU (Null Kids email label) /TM (nullkids.email.export) /V (nullkids@example.test) /DV (draft-nullkids@example.test) /MaxLen 64 /Kids null >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [320 664 72 640] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (emptykids.decoy) /TU (Explicit empty Kids decoy label) /V (empty Kids decoy value) /Kids [] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /FT /Tx /T (mismatchkids.decoy) /TU (Mismatched Kids decoy label) /V (mismatched Kids decoy value) /Kids [18 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 520 320 544] /F 4 >>\nendobj\n"
    . "20 0 obj\n<< /FT /Tx /T (malformedkids.decoy) /TU (Malformed Kids decoy label) /V (malformed Kids decoy value) /Kids 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /NotAnArray true >>\nendobj\n"
    . "24 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 520 320 544] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fields = $fieldsByName($form['fields']);
$encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

if (!isset($fields['nullkids.email'])) {
    throw new RuntimeException('Missing AcroForm parent field whose /Kids value is PDF null.');
}

foreach (['emptykids.decoy', 'mismatchkids.decoy', 'malformedkids.decoy', 'empty Kids decoy value', 'mismatched Kids decoy value', 'malformed Kids decoy value'] as $decoyText) {
    if ((is_string($encoded) && str_contains($encoded, $decoyText)) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException("Malformed AcroForm Kids boundary leaked decoy review text: {$decoyText}");
    }
}

echo '<!-- markerpdf:pdf-acroform-fields-null-kids-parent-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-null-kids-parent-currentbase',
    'native_boundary' => 'page Widget /Parent repair treats /Kids null as absent while explicit empty, mismatched, or malformed non-array Kids values still block inferred ownership',
    'field_count' => count($form['fields']),
    'null_kids_parent_selected' => isset($fields['nullkids.email']),
    'field_value_selected' => ($fields['nullkids.email']['value'] ?? null) === 'nullkids@example.test',
    'widget_promoted_from_page_annots' => ($fields['nullkids.email']['widgets'][0]['referenced_from_page_annots'] ?? null) === true,
    'widget_rect_normalized' => ($fields['nullkids.email']['widgets'][0]['rect'] ?? null) === [72.0, 640.0, 320.0, 664.0],
    'explicit_empty_kids_excluded' => is_string($encoded) && !str_contains($encoded, 'emptykids.decoy'),
    'mismatched_kids_excluded' => is_string($encoded) && !str_contains($encoded, 'mismatchkids.decoy'),
    'malformed_non_array_kids_excluded' => is_string($encoded) && !str_contains($encoded, 'malformedkids.decoy'),
    'visible_page_text_selected' => $visibleText === 'Visible AcroForm null Kids parent WordPress body',
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

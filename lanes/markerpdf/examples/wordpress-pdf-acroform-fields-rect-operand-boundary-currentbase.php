<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm widget rect operand boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R 20 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R 18 0 R] /NeedAppearances true >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (valid.indirect.rect) /V (Valid indirect rect value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [30 0 R 31 0 R 32 0 R 33 0 R] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (malformed.extra.rect) /V (Malformed extra rect value) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624 999] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /FT /Tx /T (malformed.trailing.rect) /V (Malformed trailing rect value) /Kids [16 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] 99 0 R /P 3 0 R /F 4 >>\nendobj\n"
    . "18 0 obj\n<< /FT /Tx /T (malformed.indirect.rect) /V (Malformed indirect rect value) /Kids [20 0 R] >>\nendobj\n"
    . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect 40 0 R /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n320\nendobj\n"
    . "31 0 obj\n664\nendobj\n"
    . "32 0 obj\n72\nendobj\n"
    . "33 0 obj\n640\nendobj\n"
    . "40 0 obj\n[72 520 320 544 123]\nendobj\n"
    . "99 0 obj\n<< /Subtype /Widget /FT /Tx /T (stale.trailing.rect.decoy) /V (Stale trailing rect decoy value) /Rect [72 500 320 524] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[(string) ($field['name'] ?? '')] = $field;
}

$required = ['valid.indirect.rect', 'malformed.extra.rect', 'malformed.trailing.rect', 'malformed.indirect.rect'];
foreach ($required as $name) {
    if (!isset($fields[$name])) {
        throw new RuntimeException("Missing AcroForm field {$name}.");
    }
}
if (($fields['valid.indirect.rect']['widgets'][0]['rect'] ?? null) !== [72.0, 640.0, 320.0, 664.0]) {
    throw new RuntimeException('Valid indirect AcroForm widget Rect operands were not preserved.');
}
foreach (['malformed.extra.rect', 'malformed.trailing.rect', 'malformed.indirect.rect'] as $name) {
    if (!array_key_exists('rect', $fields[$name]['widgets'][0] ?? []) || $fields[$name]['widgets'][0]['rect'] !== null) {
        throw new RuntimeException("Malformed AcroForm widget Rect for {$name} was not rejected.");
    }
}
$encoded = json_encode($form, JSON_UNESCAPED_SLASHES);
if (!is_string($encoded) || str_contains($encoded, 'stale.trailing.rect.decoy')) {
    throw new RuntimeException('Trailing Rect operand object must not be promoted as an AcroForm field.');
}
foreach (['Valid indirect rect value', 'Malformed extra rect value', 'Malformed trailing rect value', 'Malformed indirect rect value', 'Stale trailing rect decoy value'] as $reviewText) {
    if (str_contains($visibleText, $reviewText)) {
        throw new RuntimeException('AcroForm field review text must stay out of visible WordPress paragraphs.');
    }
}

echo 'fields=' . implode(',', array_keys($fields)) . PHP_EOL;
echo 'valid_indirect_rect_preserved=' . (($fields['valid.indirect.rect']['widgets'][0]['rect'] ?? null) === [72.0, 640.0, 320.0, 664.0] ? 'true' : 'false') . PHP_EOL;
echo 'extra_rect_operand_rejected=' . (array_key_exists('rect', $fields['malformed.extra.rect']['widgets'][0] ?? []) && $fields['malformed.extra.rect']['widgets'][0]['rect'] === null ? 'true' : 'false') . PHP_EOL;
echo 'trailing_rect_operand_rejected=' . (array_key_exists('rect', $fields['malformed.trailing.rect']['widgets'][0] ?? []) && $fields['malformed.trailing.rect']['widgets'][0]['rect'] === null ? 'true' : 'false') . PHP_EOL;
echo 'indirect_extra_rect_operand_rejected=' . (array_key_exists('rect', $fields['malformed.indirect.rect']['widgets'][0] ?? []) && $fields['malformed.indirect.rect']['widgets'][0]['rect'] === null ? 'true' : 'false') . PHP_EOL;
echo 'stale_trailing_rect_decoy_excluded=' . (!str_contains($encoded, 'stale.trailing.rect.decoy') ? 'true' : 'false') . PHP_EOL;
echo 'form_values_visible_in_text=' . (str_contains($visibleText, 'Valid indirect rect value') ? 'true' : 'false') . PHP_EOL;
echo 'executes_python_or_models=false' . PHP_EOL;
echo 'executes_external_pdf_tools=false' . PHP_EOL;
echo 'executes_pdf_actions=false' . PHP_EOL;

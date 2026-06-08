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

$previousPageText = 'BT /F1 12 Tf 72 720 Td (Previous xref-stream AcroForm smoke page) Tj ET';
$currentPageText = 'BT /F1 12 Tf 72 720 Td (Current xref-stream AcroForm smoke page) Tj ET';
$decoyPageText = 'BT /F1 12 Tf 72 720 Td (Decoy xref-stream AcroForm smoke page) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset,
    $generation,
    $state
);
$xrefStreamRow = static fn (int $type, int $offset, int $generation): string => chr($type)
    . pack('N', $offset)
    . chr($generation);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>');
$addObject(4, 0, "<< /Length " . strlen($previousPageText) . " >>\nstream\n{$previousPageText}\nendstream");
$addObject(5, 0, '<< /Fields [6 0 R] /NeedAppearances false >>');
$addObject(6, 0, '<< /FT /Tx /T (previous.smoke.email) /V (previous-smoke@example.test) /Kids [8 0 R] >>');
$addObject(8, 0, '<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n0 9\n" . $xrefTableRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 8; $objectNumber++) {
    $offsetKey = $objectNumber . ':0';
    $pdf .= isset($offsets[$offsetKey])
        ? $xrefTableRow($offsets[$offsetKey])
        : $xrefTableRow(0, 0, 'f');
}
$pdf .= "trailer\n<< /Size 9 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /AcroForm 5 1 R >>');
$addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
$addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Contents 4 1 R /Annots [8 1 R] >>');
$addObject(4, 1, "<< /Length " . strlen($currentPageText) . " >>\nstream\n{$currentPageText}\nendstream");
$addObject(5, 1, '<< /Fields [6 1 R] /NeedAppearances true >>');
$addObject(6, 1, '<< /FT /Tx /T (current.smoke.email) /TU (Current smoke email label) /V (current-smoke@example.test) /Kids [8 1 R] >>');
$addObject(8, 1, '<< /Subtype /Widget /Parent 6 1 R /Rect [72 640 300 664] /P 3 1 R /F 4 >>');

$addObject(1, 2, '<< /Type /Catalog /Pages 2 2 R /AcroForm 5 2 R >>');
$addObject(2, 2, '<< /Type /Pages /Kids [3 2 R] /Count 1 >>');
$addObject(3, 2, '<< /Type /Page /Parent 2 2 R /Contents 4 2 R /Annots [8 2 R] >>');
$addObject(4, 2, "<< /Length " . strlen($decoyPageText) . " >>\nstream\n{$decoyPageText}\nendstream");
$addObject(5, 2, '<< /Fields [6 2 R] /NeedAppearances false >>');
$addObject(6, 2, '<< /FT /Tx /T (decoy.smoke.email) /V (decoy-smoke@example.test) /Kids [8 2 R] >>');
$addObject(8, 2, '<< /Subtype /Widget /Parent 6 2 R /Rect [72 640 300 664] /P 3 2 R /F 4 >>');

$rows = '';
foreach ([1, 2, 3, 4, 5, 6, 8] as $objectNumber) {
    $rows .= $xrefStreamRow(1, $offsets[$objectNumber . ':1'], 1);
}
$compressedRows = gzcompress($rows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress AcroForm xref-stream smoke rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 1 R /Prev ' . $previousXrefOffset
    . ' /Index [1 6 8 1] /W [1 4 1] /Filter /FlateDecode /Length '
    . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fields = $fieldsByName($form['fields']);
$encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

if (!isset($fields['current.smoke.email'])) {
    throw new RuntimeException('Missing current AcroForm field selected through xref-stream /Prev.');
}

foreach (['previous.smoke.email', 'previous-smoke@example.test', 'decoy.smoke.email', 'decoy-smoke@example.test'] as $staleText) {
    if ((is_string($encoded) && str_contains($encoded, $staleText)) || str_contains($visibleText, $staleText)) {
        throw new RuntimeException("Stale AcroForm review text leaked into WordPress import: {$staleText}");
    }
}

if ($visibleText !== 'Current xref-stream AcroForm smoke page') {
    throw new RuntimeException('Visible WordPress text did not follow the xref-stream current page.');
}

echo '<!-- markerpdf:pdf-xref-prev-chain-acroform-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-xref-prev-chain-acroform-currentbase',
    'native_boundary' => 'PDF xref-stream /Prev chain selects current AcroForm review objects before previous rows or higher-generation direct decoys',
    'field_count' => count($form['fields']),
    'current_field_selected' => isset($fields['current.smoke.email']),
    'current_value_selected' => ($fields['current.smoke.email']['value'] ?? null) === 'current-smoke@example.test',
    'need_appearances_selected' => $form['need_appearances'] === true,
    'current_page_text_selected' => $visibleText === 'Current xref-stream AcroForm smoke page',
    'stale_form_review_excluded' => is_string($encoded)
        && !str_contains($encoded, 'previous.smoke.email')
        && !str_contains($encoded, 'decoy.smoke.email'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

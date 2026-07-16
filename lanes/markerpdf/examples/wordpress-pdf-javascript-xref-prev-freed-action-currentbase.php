<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfJavaScriptActionInspector;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Current xref-free JavaScript guard text) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset,
    $generation,
    $state
);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /OpenAction 8 0 R /Names << /JavaScript 6 0 R >> >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(6, '<< /Names [(freed-prev-script) 8 0 R] >>');
$addObject(8, "<< /S /JavaScript /JS (stalePrevOpenAction\\(\\)) >>");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 9\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow($offsets[2])
    . $xrefRow($offsets[3])
    . $xrefRow($offsets[4])
    . $xrefRow($offsets[5])
    . $xrefRow($offsets[6])
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets[8])
    . "trailer\n<< /Size 9 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "8 1\n"
    . $xrefRow(0, 1, 'f')
    . "trailer\n<< /Size 9 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$review = (new PdfJavaScriptActionInspector())->reviewDocumentActions($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'scenario' => 'wordpress-pdf-javascript-xref-prev-freed-action-currentbase',
    'native_boundary' => 'current xref Prev-chain free rows suppress inherited JavaScript action review objects',
    'free_row_detected' => isset($freeObjects[8]),
    'visible_text_preserved' => $plainText === 'Current xref-free JavaScript guard text',
    'freed_javascript_suppressed' => $review['has_javascript'] === false
        && $review['action_count'] === 0
        && !str_contains($encodedReview, 'stalePrevOpenAction'),
    'executes_javascript' => $review['executes_javascript'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$summary['free_row_detected']
    || !$summary['visible_text_preserved']
    || !$summary['freed_javascript_suppressed']
    || $summary['executes_javascript'] !== false
) {
    throw new RuntimeException('Expected xref-free JavaScript action object to stay out of WordPress safety review.');
}

echo '<!-- markerpdf-javascript-xref-prev-freed-action-currentbase-smoke ' . htmlspecialchars(json_encode(
    $summary,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

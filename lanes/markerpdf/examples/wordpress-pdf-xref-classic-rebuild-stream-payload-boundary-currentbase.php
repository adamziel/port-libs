<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale stream-owned xref page) Tj T* (Stream payload xref leak) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current stream-owned xref page) Tj T* (Stream payload xref ignored) Tj ET';
$stalePayload = '<wp-export><post id="stale-stream-owned-xref"/></wp-export>';
$currentPayload = '<wp-export><post id="current-stream-owned-xref"/></wp-export>';
$currentChecksum = strtoupper(hash('md5', $currentPayload));

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber] = $offset;
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset,
    $generation,
    $state
);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Annots [7 0 R] /Contents 5 0 R >>');
$addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(7, '<< /Type /Annot /Subtype /Link /Rect [72 700 250 718] /Contents (Stale stream-owned xref annotation) /A << /S /URI /URI (https://stale.example.com/stream-owned-xref) >> >>');
$addObject(8, '<< /Names [(stale-stream-owned-xref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (stale-stream-owned-xref.xml) /Desc (Stale stream-owned xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 11\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow($offsets[2])
    . $xrefRow($offsets[3])
    . $xrefRow($offsets[4])
    . $xrefRow($offsets[5])
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets[7])
    . $xrefRow($offsets[8])
    . $xrefRow($offsets[9])
    . $xrefRow($offsets[10])
    . "trailer\n<< /Size 80 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Annots [7 0 R] /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(28, '<< /Names [(current-stream-owned-xref.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (current-stream-owned-xref.xml) /Desc (Current stream-owned xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "7 1\n"
    . $xrefRow(0, 1, 'f')
    . "20 12\n"
    . $xrefRow($offsets[20])
    . $xrefRow($offsets[21])
    . $xrefRow($offsets[22])
    . $xrefRow($offsets[23])
    . $xrefRow($offsets[24])
    . $xrefRow(0, 65535, 'f')
    . $xrefRow(0, 65535, 'f')
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[28])
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[30])
    . $xrefRow($offsets[31])
    . "trailer\n<< /Size 80 /Root 20 0 R /Prev {$previousXrefOffset} >>\n";

$streamOwnerOffset = strlen($pdf);
$fakePayload = "stream preface endobj\n"
    . "xref\n"
    . "0 11\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow($offsets[2])
    . $xrefRow($offsets[3])
    . $xrefRow($offsets[4])
    . $xrefRow($offsets[5])
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets[7])
    . $xrefRow($offsets[8])
    . $xrefRow($offsets[9])
    . $xrefRow($offsets[10])
    . "trailer\n<< /Size 80 /Root 1 0 R >>\n";
$pdf .= "60 0 obj\n"
    . "<< /Length 8 >>\n"
    . "stream\n"
    . $fakePayload
    . "endstream\n"
    . "endobj\n"
    . "startxref\n999999\n%%EOF";

$textExtractor = new PdfTextExtractor();
$plainText = $textExtractor->extractPlainText($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$pageLinks = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
$reviewJson = json_encode([$attachmentSummary, $embeddedFiles, $freeObjects, $pageLinks], JSON_UNESCAPED_SLASHES) ?: '';

$smoke = [
    'scenario' => 'wordpress-pdf-xref-classic-rebuild-stream-payload-boundary-currentbase',
    'native_boundary' => 'classic xref rebuild ignores xref-looking bytes embedded inside a still-open stream payload',
    'previous_xref_offset' => $previousXrefOffset,
    'current_xref_offset' => $currentXrefOffset,
    'stream_owner_offset' => $streamOwnerOffset,
    'current_text_selected' => str_contains($plainText, 'Current stream-owned xref page'),
    'current_attachment_selected' => ($attachmentSummary['filenames'][0] ?? null) === 'current-stream-owned-xref.xml',
    'embedded_file_payload_current' => ($embeddedFiles[0]['content'] ?? null) === $currentPayload,
    'freed_annotation_suppressed' => ($freeObjects[7] ?? null) === true && $pageLinks === [],
    'stream_owned_fake_xref_excluded' => !str_contains($reviewJson, 'stale-stream-owned-xref'),
    'stale_link_excluded' => !str_contains($reviewJson, 'stale.example.com/stream-owned-xref'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'current_text_selected',
    'current_attachment_selected',
    'embedded_file_payload_current',
    'freed_annotation_suppressed',
    'stream_owned_fake_xref_excluded',
    'stale_link_excluded',
] as $required) {
    if (($smoke[$required] ?? false) !== true) {
        throw new RuntimeException('Classic xref stream-payload boundary smoke failed: ' . $required);
    }
}

echo '<!-- markerpdf-xref-classic-rebuild-stream-payload-boundary-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES
) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_filter(array_map('trim', explode("\n", $plainText))) as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-03T09:30:09Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Prev chain metadata page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Prev chain metadata page) Tj T* (Incremental update current base) Tj ET';
$staleXmp = gzcompress($xmpPacket('Stale Prev Chain XMP Title', 'Stale previous xref metadata must not win'));
$currentXmp = gzcompress($xmpPacket('Current Prev Chain XMP Title', 'Current incremental xref metadata selected'));
if (!is_string($staleXmp) || !is_string($currentXmp)) {
    throw new RuntimeException('Unable to compress xref Prev chain metadata smoke fixture streams.');
}

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(6, 0, '<< /Title (Stale Prev Chain Info Title) /Author (Stale Prev Author) /Producer (Stale Prev Producer) >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 8\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($offsets['1:0'])
    . $xrefTableRow($offsets['2:0'])
    . $xrefTableRow($offsets['3:0'])
    . $xrefTableRow($offsets['4:0'])
    . $xrefTableRow($offsets['5:0'])
    . $xrefTableRow($offsets['6:0'])
    . $xrefTableRow($offsets['7:0'])
    . "trailer\n<< /Size 8 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /Lang (en-US) /Metadata 7 1 R >>');
$addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
$addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 1 R >>');
$addObject(4, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 1, '<< /Title (Current Prev Chain Info Title) /Author (Current Prev Author) /Producer (Current Prev Producer) >>');
$addObject(7, 1, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");

$currentRows = ''
    . $xrefStreamRow(1, 0, 1)
    . $xrefStreamRow(1, 0, 1)
    . $xrefStreamRow(1, 0, 1)
    . $xrefStreamRow(1, 0, 1)
    . $xrefStreamRow(1, $offsets['5:0'], 0)
    . $xrefStreamRow(1, 0, 1)
    . $xrefStreamRow(1, 0, 1);
$compressedCurrentRows = gzcompress($currentRows);
if (!is_string($compressedCurrentRows)) {
    throw new RuntimeException('Unable to compress current xref-stream Prev chain smoke fixture.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 1 R /Info 6 1 R /Prev ' . $previousXrefOffset . ' /Index [1 7] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
    . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$mismatchContent = 'BT /F1 12 Tf 72 720 Td (Current generation mismatch page) Tj T* (Metadata generation boundary) Tj ET';
$mismatchXmp = gzcompress($xmpPacket('Wrong Generation XMP Title', 'This generation-one XMP stream is not referenced by the catalog'));
if (!is_string($mismatchXmp)) {
    throw new RuntimeException('Unable to compress xref Prev chain generation-mismatch smoke fixture stream.');
}

$mismatchPdf = "%PDF-1.7\n";
$mismatchOffsets = [];
$addMismatchObject = static function (int $objectNumber, int $generation, string $body) use (&$mismatchPdf, &$mismatchOffsets): int {
    $offset = strlen($mismatchPdf);
    $mismatchOffsets[$objectNumber . ':' . $generation] = $offset;
    $mismatchPdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$addMismatchObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 7 0 R /Lang (de-DE) >>');
$addMismatchObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addMismatchObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addMismatchObject(4, 0, '<< /Length 0 >>');
$addMismatchObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addMismatchObject(6, 0, '<< /Title (Stale Generation Info Title) /Author (Stale Generation Author) >>');
$addMismatchObject(7, 0, '<< /Type /Metadata /Subtype /XML /Length 0 >>');

$mismatchPreviousXrefOffset = strlen($mismatchPdf);
$mismatchPdf .= "xref\n"
    . "0 8\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($mismatchOffsets['1:0'])
    . $xrefTableRow($mismatchOffsets['2:0'])
    . $xrefTableRow($mismatchOffsets['3:0'])
    . $xrefTableRow($mismatchOffsets['4:0'])
    . $xrefTableRow($mismatchOffsets['5:0'])
    . $xrefTableRow($mismatchOffsets['6:0'])
    . $xrefTableRow($mismatchOffsets['7:0'])
    . "trailer\n<< /Size 8 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$mismatchPreviousXrefOffset}\n%%EOF\n";

$addMismatchObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /Metadata 7 0 R /Lang (en-US) >>');
$addMismatchObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
$addMismatchObject(3, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 1 R >>');
$addMismatchObject(4, 1, "<< /Length " . strlen($mismatchContent) . " >>\nstream\n{$mismatchContent}\nendstream");
$addMismatchObject(6, 1, '<< /Title (Current Generation Info Title) /Author (Current Generation Author) /Producer (Current Generation Producer) >>');
$addMismatchObject(7, 1, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($mismatchXmp) . " >>\nstream\n{$mismatchXmp}\nendstream");

$mismatchRows = ''
    . $xrefStreamRow(1, $mismatchOffsets['1:1'], 1)
    . $xrefStreamRow(1, $mismatchOffsets['2:1'], 1)
    . $xrefStreamRow(1, $mismatchOffsets['3:1'], 1)
    . $xrefStreamRow(1, $mismatchOffsets['4:1'], 1)
    . $xrefStreamRow(1, $mismatchOffsets['5:0'], 0)
    . $xrefStreamRow(1, $mismatchOffsets['6:1'], 1)
    . $xrefStreamRow(1, $mismatchOffsets['7:1'], 1);
$compressedMismatchRows = gzcompress($mismatchRows);
if (!is_string($compressedMismatchRows)) {
    throw new RuntimeException('Unable to compress current generation-mismatch xref-stream smoke fixture.');
}

$mismatchCurrentXrefOffset = strlen($mismatchPdf);
$mismatchPdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 1 R /Info 6 1 R /Prev ' . $mismatchPreviousXrefOffset . ' /Index [1 7] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedMismatchRows) . " >>\n"
    . "stream\n{$compressedMismatchRows}\nendstream\nendobj\n"
    . "startxref\n{$mismatchCurrentXrefOffset}\n%%EOF";

$stalePayload = '<wp-export><post id="stale-prev-attachment"/></wp-export>';
$currentPayload = '<wp-export><post id="current-prev-attachment"/></wp-export>';
$attachmentPdf = "%PDF-1.7\n";
$attachmentOffsets = [];
$addAttachmentObject = static function (int $objectNumber, int $generation, string $body) use (&$attachmentPdf, &$attachmentOffsets): int {
    $offset = strlen($attachmentPdf);
    $attachmentOffsets[$objectNumber . ':' . $generation] = $offset;
    $attachmentPdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$addAttachmentObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>');
$addAttachmentObject(2, 0, '<< /Type /Pages /Kids [] /Count 0 >>');
$addAttachmentObject(6, 0, '<< /Names [(stale-source.xml) 10 0 R] >>');
$addAttachmentObject(10, 0, '<< /Type /Filespec /F (stale-source.xml) /Desc (Stale Prev chain attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addAttachmentObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$attachmentPreviousXrefOffset = strlen($attachmentPdf);
$attachmentPdf .= "xref\n"
    . "0 12\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($attachmentOffsets['1:0'])
    . $xrefTableRow($attachmentOffsets['2:0'])
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($attachmentOffsets['6:0'])
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($attachmentOffsets['10:0'])
    . $xrefTableRow($attachmentOffsets['11:0'])
    . "trailer\n<< /Size 12 /Root 1 0 R >>\n"
    . "startxref\n{$attachmentPreviousXrefOffset}\n%%EOF\n";

$addAttachmentObject(1, 1, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 1 R >> >>');
$addAttachmentObject(6, 1, '<< /Names [(current-source.xml) 10 1 R] >>');
$addAttachmentObject(10, 1, '<< /Type /Filespec /F (current-source.xml) /Desc (Current Prev chain attachment) /AFRelationship /Source /EF << /F 11 1 R >> >>');
$addAttachmentObject(11, 1, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$attachmentRows = ''
    . $xrefStreamRow(1, 0, 1)
    . $xrefStreamRow(1, 0, 1)
    . $xrefStreamRow(1, 0, 1)
    . $xrefStreamRow(1, 0, 1);
$compressedAttachmentRows = gzcompress($attachmentRows);
if (!is_string($compressedAttachmentRows)) {
    throw new RuntimeException('Unable to compress xref Prev chain embedded-file smoke fixture.');
}

$attachmentCurrentXrefOffset = strlen($attachmentPdf);
$attachmentPdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 1 R /Prev ' . $attachmentPreviousXrefOffset . ' /Index [1 1 6 1 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedAttachmentRows) . " >>\n"
    . "stream\n{$compressedAttachmentRows}\nendstream\nendobj\n"
    . "startxref\n{$attachmentCurrentXrefOffset}\n%%EOF";

$malformedIndexStaleText = 'BT /F1 12 Tf 72 720 Td (Stale malformed index smoke page) Tj ET';
$malformedIndexCurrentText = 'BT /F1 12 Tf 72 720 Td (Current malformed index smoke page) Tj ET';
$malformedIndexStalePayload = '<wp-export><post id="stale-malformed-index-smoke"/></wp-export>';
$malformedIndexCurrentPayload = '<wp-export><post id="current-malformed-index-smoke"/></wp-export>';
$malformedIndexPdf = "%PDF-1.7\n";
$addMalformedIndexObject = static function (int $objectNumber, int $generation, string $body) use (&$malformedIndexPdf): int {
    $offset = strlen($malformedIndexPdf);
    $malformedIndexPdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$malformedIndexStaleCatalogOffset = $addMalformedIndexObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Names << /EmbeddedFiles 8 0 R >> >>');
$malformedIndexStalePagesOffset = $addMalformedIndexObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$malformedIndexStalePageOffset = $addMalformedIndexObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$malformedIndexStaleContentOffset = $addMalformedIndexObject(4, 0, "<< /Length " . strlen($malformedIndexStaleText) . " >>\nstream\n{$malformedIndexStaleText}\nendstream");
$malformedIndexFontOffset = $addMalformedIndexObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$malformedIndexStaleInfoOffset = $addMalformedIndexObject(6, 0, '<< /Title (Stale Malformed Index Smoke Title) /Author (Stale Malformed Smoke Author) >>');
$malformedIndexStaleNameTreeOffset = $addMalformedIndexObject(8, 0, '<< /Names [(stale-malformed-index-smoke.xml) 10 0 R] >>');
$malformedIndexStaleFileSpecOffset = $addMalformedIndexObject(10, 0, '<< /Type /Filespec /F (stale-malformed-index-smoke.xml) /Desc (Stale malformed index smoke attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$malformedIndexStaleEmbeddedOffset = $addMalformedIndexObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($malformedIndexStalePayload) . " >>\nstream\n{$malformedIndexStalePayload}\nendstream");

$malformedIndexPreviousXrefOffset = strlen($malformedIndexPdf);
$malformedIndexPdf .= "xref\n"
    . "0 12\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($malformedIndexStaleCatalogOffset)
    . $xrefTableRow($malformedIndexStalePagesOffset)
    . $xrefTableRow($malformedIndexStalePageOffset)
    . $xrefTableRow($malformedIndexStaleContentOffset)
    . $xrefTableRow($malformedIndexFontOffset)
    . $xrefTableRow($malformedIndexStaleInfoOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($malformedIndexStaleNameTreeOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($malformedIndexStaleFileSpecOffset)
    . $xrefTableRow($malformedIndexStaleEmbeddedOffset)
    . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$malformedIndexPreviousXrefOffset}\n%%EOF\n";

$malformedIndexCurrentCatalogOffset = $addMalformedIndexObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Names << /EmbeddedFiles 8 0 R >> >>');
$malformedIndexCurrentPagesOffset = $addMalformedIndexObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$malformedIndexCurrentPageOffset = $addMalformedIndexObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$malformedIndexCurrentContentOffset = $addMalformedIndexObject(4, 0, "<< /Length " . strlen($malformedIndexCurrentText) . " >>\nstream\n{$malformedIndexCurrentText}\nendstream");
$malformedIndexCurrentInfoOffset = $addMalformedIndexObject(6, 0, '<< /Title (Current Malformed Index Smoke Title) /Author (Current Malformed Smoke Author) /Producer (Current Malformed Smoke Producer) >>');
$malformedIndexCurrentNameTreeOffset = $addMalformedIndexObject(8, 0, '<< /Names [(current-malformed-index-smoke.xml) 10 0 R] >>');
$malformedIndexCurrentFileSpecOffset = $addMalformedIndexObject(10, 0, '<< /Type /Filespec /F (current-malformed-index-smoke.xml) /Desc (Current malformed index smoke attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$malformedIndexCurrentEmbeddedOffset = $addMalformedIndexObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($malformedIndexCurrentPayload) . " >>\nstream\n{$malformedIndexCurrentPayload}\nendstream");

$malformedIndexRows = ''
    . $xrefStreamRow(1, $malformedIndexCurrentCatalogOffset, 0)
    . $xrefStreamRow(1, $malformedIndexCurrentPagesOffset, 0)
    . $xrefStreamRow(1, $malformedIndexCurrentPageOffset, 0)
    . $xrefStreamRow(1, $malformedIndexCurrentContentOffset, 0)
    . $xrefStreamRow(1, $malformedIndexFontOffset, 0)
    . $xrefStreamRow(1, $malformedIndexCurrentInfoOffset, 0)
    . $xrefStreamRow(1, $malformedIndexCurrentNameTreeOffset, 0)
    . $xrefStreamRow(1, $malformedIndexCurrentFileSpecOffset, 0)
    . $xrefStreamRow(1, $malformedIndexCurrentEmbeddedOffset, 0);
$compressedMalformedIndexRows = gzcompress($malformedIndexRows);
if (!is_string($compressedMalformedIndexRows)) {
    throw new RuntimeException('Unable to compress malformed-index current xref-stream smoke fixture.');
}

$malformedIndexCurrentXrefOffset = strlen($malformedIndexPdf);
$malformedIndexPdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 40 /Root 1 0 R /Info 6 0 R /Prev ' . $malformedIndexPreviousXrefOffset . ' /Index [30 9] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedMalformedIndexRows) . " >>\n"
    . "stream\n{$compressedMalformedIndexRows}\nendstream\nendobj\n"
    . "startxref\n{$malformedIndexCurrentXrefOffset}\n%%EOF";

$staleOffsetStaleText = 'BT /F1 12 Tf 72 720 Td (Stale valid-offset smoke page) Tj ET';
$staleOffsetCurrentText = 'BT /F1 12 Tf 72 720 Td (Current valid-offset smoke page) Tj ET';
$staleOffsetStalePayload = '<wp-export><post id="stale-valid-offset-smoke"/></wp-export>';
$staleOffsetCurrentPayload = '<wp-export><post id="current-valid-offset-smoke"/></wp-export>';
$staleOffsetPdf = "%PDF-1.7\n";
$addStaleOffsetObject = static function (int $objectNumber, int $generation, string $body) use (&$staleOffsetPdf): int {
    $offset = strlen($staleOffsetPdf);
    $staleOffsetPdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$staleOffsetStaleCatalogOffset = $addStaleOffsetObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Names << /EmbeddedFiles 8 0 R >> >>');
$staleOffsetStalePagesOffset = $addStaleOffsetObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$staleOffsetStalePageOffset = $addStaleOffsetObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$staleOffsetStaleContentOffset = $addStaleOffsetObject(4, 0, "<< /Length " . strlen($staleOffsetStaleText) . " >>\nstream\n{$staleOffsetStaleText}\nendstream");
$staleOffsetFontOffset = $addStaleOffsetObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleOffsetStaleInfoOffset = $addStaleOffsetObject(6, 0, '<< /Title (Stale Valid Offset Smoke Info) /Author (Stale Valid Offset Smoke Author) >>');
$staleOffsetStaleNameTreeOffset = $addStaleOffsetObject(8, 0, '<< /Names [(stale-valid-offset-smoke.xml) 10 0 R] >>');
$staleOffsetStaleFileSpecOffset = $addStaleOffsetObject(10, 0, '<< /Type /Filespec /F (stale-valid-offset-smoke.xml) /Desc (Stale valid-offset smoke attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$staleOffsetStaleEmbeddedOffset = $addStaleOffsetObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($staleOffsetStalePayload) . " >>\nstream\n{$staleOffsetStalePayload}\nendstream");

$staleOffsetPreviousXrefOffset = strlen($staleOffsetPdf);
$staleOffsetPdf .= "xref\n"
    . "0 12\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($staleOffsetStaleCatalogOffset)
    . $xrefTableRow($staleOffsetStalePagesOffset)
    . $xrefTableRow($staleOffsetStalePageOffset)
    . $xrefTableRow($staleOffsetStaleContentOffset)
    . $xrefTableRow($staleOffsetFontOffset)
    . $xrefTableRow($staleOffsetStaleInfoOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($staleOffsetStaleNameTreeOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($staleOffsetStaleFileSpecOffset)
    . $xrefTableRow($staleOffsetStaleEmbeddedOffset)
    . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$staleOffsetPreviousXrefOffset}\n%%EOF\n";

$addStaleOffsetObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Names << /EmbeddedFiles 8 0 R >> >>');
$addStaleOffsetObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addStaleOffsetObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addStaleOffsetObject(4, 0, "<< /Length " . strlen($staleOffsetCurrentText) . " >>\nstream\n{$staleOffsetCurrentText}\nendstream");
$addStaleOffsetObject(6, 0, '<< /Title (Current Valid Offset Smoke Info) /Author (Current Valid Offset Smoke Author) /Producer (Current Valid Offset Smoke Producer) >>');
$addStaleOffsetObject(8, 0, '<< /Names [(current-valid-offset-smoke.xml) 10 0 R] >>');
$addStaleOffsetObject(10, 0, '<< /Type /Filespec /F (current-valid-offset-smoke.xml) /Desc (Current valid-offset smoke attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addStaleOffsetObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($staleOffsetCurrentPayload) . " >>\nstream\n{$staleOffsetCurrentPayload}\nendstream");

$staleOffsetRows = ''
    . $xrefStreamRow(1, $staleOffsetStaleCatalogOffset, 0)
    . $xrefStreamRow(1, $staleOffsetStalePagesOffset, 0)
    . $xrefStreamRow(1, $staleOffsetStalePageOffset, 0)
    . $xrefStreamRow(1, $staleOffsetStaleContentOffset, 0)
    . $xrefStreamRow(1, $staleOffsetFontOffset, 0)
    . $xrefStreamRow(1, $staleOffsetStaleInfoOffset, 0)
    . $xrefStreamRow(1, $staleOffsetStaleNameTreeOffset, 0)
    . $xrefStreamRow(1, $staleOffsetStaleFileSpecOffset, 0)
    . $xrefStreamRow(1, $staleOffsetStaleEmbeddedOffset, 0);
$compressedStaleOffsetRows = gzcompress($staleOffsetRows);
if (!is_string($compressedStaleOffsetRows)) {
    throw new RuntimeException('Unable to compress stale-offset current xref-stream smoke fixture.');
}

$staleOffsetCurrentXrefOffset = strlen($staleOffsetPdf);
$staleOffsetPdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 40 /Root 1 0 R /Info 6 0 R /Prev ' . $staleOffsetPreviousXrefOffset . ' /Index [1 6 8 1 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedStaleOffsetRows) . " >>\n"
    . "stream\n{$compressedStaleOffsetRows}\nendstream\nendobj\n"
    . "startxref\n{$staleOffsetCurrentXrefOffset}\n%%EOF";

$wrongCurrentOffsetStaleText = 'BT /F1 12 Tf 72 720 Td (Stale wrong-current-offset smoke page) Tj ET';
$wrongCurrentOffsetCurrentText = 'BT /F1 12 Tf 72 720 Td (Current wrong-current-offset smoke page) Tj ET';
$wrongCurrentOffsetStalePayload = '<wp-export><post id="stale-wrong-current-offset-smoke"/></wp-export>';
$wrongCurrentOffsetCurrentPayload = '<wp-export><post id="current-wrong-current-offset-smoke"/></wp-export>';
$wrongCurrentOffsetPdf = "%PDF-1.7\n";
$addWrongCurrentOffsetObject = static function (int $objectNumber, int $generation, string $body) use (&$wrongCurrentOffsetPdf): int {
    $offset = strlen($wrongCurrentOffsetPdf);
    $wrongCurrentOffsetPdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$wrongCurrentOffsetStaleCatalogOffset = $addWrongCurrentOffsetObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Names << /EmbeddedFiles 8 0 R >> >>');
$wrongCurrentOffsetStalePagesOffset = $addWrongCurrentOffsetObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$wrongCurrentOffsetStalePageOffset = $addWrongCurrentOffsetObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$wrongCurrentOffsetStaleContentOffset = $addWrongCurrentOffsetObject(4, 0, "<< /Length " . strlen($wrongCurrentOffsetStaleText) . " >>\nstream\n{$wrongCurrentOffsetStaleText}\nendstream");
$wrongCurrentOffsetFontOffset = $addWrongCurrentOffsetObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$wrongCurrentOffsetStaleInfoOffset = $addWrongCurrentOffsetObject(6, 0, '<< /Title (Stale Wrong Current Offset Smoke Info) /Author (Stale Wrong Current Smoke Author) >>');
$wrongCurrentOffsetStaleNameTreeOffset = $addWrongCurrentOffsetObject(8, 0, '<< /Names [(stale-wrong-current-offset-smoke.xml) 10 0 R] >>');
$wrongCurrentOffsetStaleFileSpecOffset = $addWrongCurrentOffsetObject(10, 0, '<< /Type /Filespec /F (stale-wrong-current-offset-smoke.xml) /Desc (Stale wrong-current-offset smoke attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$wrongCurrentOffsetStaleEmbeddedOffset = $addWrongCurrentOffsetObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($wrongCurrentOffsetStalePayload) . " >>\nstream\n{$wrongCurrentOffsetStalePayload}\nendstream");

$wrongCurrentOffsetPreviousXrefOffset = strlen($wrongCurrentOffsetPdf);
$wrongCurrentOffsetPdf .= "xref\n"
    . "0 12\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($wrongCurrentOffsetStaleCatalogOffset)
    . $xrefTableRow($wrongCurrentOffsetStalePagesOffset)
    . $xrefTableRow($wrongCurrentOffsetStalePageOffset)
    . $xrefTableRow($wrongCurrentOffsetStaleContentOffset)
    . $xrefTableRow($wrongCurrentOffsetFontOffset)
    . $xrefTableRow($wrongCurrentOffsetStaleInfoOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($wrongCurrentOffsetStaleNameTreeOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($wrongCurrentOffsetStaleFileSpecOffset)
    . $xrefTableRow($wrongCurrentOffsetStaleEmbeddedOffset)
    . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$wrongCurrentOffsetPreviousXrefOffset}\n%%EOF\n";

$addWrongCurrentOffsetObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Names << /EmbeddedFiles 8 0 R >> >>');
$wrongCurrentOffsetCurrentPagesOffset = $addWrongCurrentOffsetObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$wrongCurrentOffsetCurrentPageOffset = $addWrongCurrentOffsetObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$wrongCurrentOffsetCurrentContentOffset = $addWrongCurrentOffsetObject(4, 0, "<< /Length " . strlen($wrongCurrentOffsetCurrentText) . " >>\nstream\n{$wrongCurrentOffsetCurrentText}\nendstream");
$wrongCurrentOffsetCurrentInfoOffset = $addWrongCurrentOffsetObject(6, 0, '<< /Title (Current Wrong Current Offset Smoke Info) /Author (Current Wrong Current Smoke Author) /Producer (Current Wrong Current Smoke Producer) >>');
$wrongCurrentOffsetCurrentNameTreeOffset = $addWrongCurrentOffsetObject(8, 0, '<< /Names [(current-wrong-current-offset-smoke.xml) 10 0 R] >>');
$wrongCurrentOffsetCurrentFileSpecOffset = $addWrongCurrentOffsetObject(10, 0, '<< /Type /Filespec /F (current-wrong-current-offset-smoke.xml) /Desc (Current wrong-current-offset smoke attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$wrongCurrentOffsetCurrentEmbeddedOffset = $addWrongCurrentOffsetObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($wrongCurrentOffsetCurrentPayload) . " >>\nstream\n{$wrongCurrentOffsetCurrentPayload}\nendstream");

$wrongCurrentOffsetRows = ''
    . $xrefStreamRow(1, $wrongCurrentOffsetCurrentPagesOffset, 0)
    . $xrefStreamRow(1, $wrongCurrentOffsetCurrentPagesOffset, 0)
    . $xrefStreamRow(1, $wrongCurrentOffsetCurrentPageOffset, 0)
    . $xrefStreamRow(1, $wrongCurrentOffsetCurrentContentOffset, 0)
    . $xrefStreamRow(1, $wrongCurrentOffsetFontOffset, 0)
    . $xrefStreamRow(1, $wrongCurrentOffsetCurrentInfoOffset, 0)
    . $xrefStreamRow(1, $wrongCurrentOffsetCurrentNameTreeOffset, 0)
    . $xrefStreamRow(1, $wrongCurrentOffsetCurrentFileSpecOffset, 0)
    . $xrefStreamRow(1, $wrongCurrentOffsetCurrentEmbeddedOffset, 0);
$compressedWrongCurrentOffsetRows = gzcompress($wrongCurrentOffsetRows);
if (!is_string($compressedWrongCurrentOffsetRows)) {
    throw new RuntimeException('Unable to compress wrong-current-offset smoke xref stream.');
}

$wrongCurrentOffsetCurrentXrefOffset = strlen($wrongCurrentOffsetPdf);
$wrongCurrentOffsetPdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 40 /Root 1 0 R /Info 6 0 R /Prev ' . $wrongCurrentOffsetPreviousXrefOffset . ' /Index [1 6 8 1 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedWrongCurrentOffsetRows) . " >>\n"
    . "stream\n{$compressedWrongCurrentOffsetRows}\nendstream\nendobj\n"
    . "startxref\n{$wrongCurrentOffsetCurrentXrefOffset}\n%%EOF";

$classicTableStaleText = 'BT /F1 12 Tf 72 720 Td (Stale classic Prev table smoke page) Tj ET';
$classicTableCurrentText = 'BT /F1 12 Tf 72 720 Td (Current classic Prev table smoke page) Tj ET';
$classicTableStalePayload = '<wp-export><post id="stale-classic-prev-smoke"/></wp-export>';
$classicTableCurrentPayload = '<wp-export><post id="current-classic-prev-smoke"/></wp-export>';
$classicTableStaleXmp = gzcompress($xmpPacket('Stale Classic Table Smoke Title', 'Stale classic table smoke metadata'));
$classicTableCurrentXmp = gzcompress($xmpPacket('Current Classic Table Smoke Title', 'Current classic table smoke metadata'));
if (!is_string($classicTableStaleXmp) || !is_string($classicTableCurrentXmp)) {
    throw new RuntimeException('Unable to compress classic-table xref Prev chain smoke fixture streams.');
}

$classicTablePdf = "%PDF-1.7\n";
$addClassicTableObject = static function (int $objectNumber, int $generation, string $body) use (&$classicTablePdf): int {
    $offset = strlen($classicTablePdf);
    $classicTablePdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$classicTableStaleCatalogOffset = $addClassicTableObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$classicTableStalePagesOffset = $addClassicTableObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$classicTableStalePageOffset = $addClassicTableObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$classicTableStaleContentOffset = $addClassicTableObject(4, 0, "<< /Length " . strlen($classicTableStaleText) . " >>\nstream\n{$classicTableStaleText}\nendstream");
$classicTableFontOffset = $addClassicTableObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$classicTableStaleInfoOffset = $addClassicTableObject(6, 0, '<< /Title (Stale Classic Table Smoke Info) /Author (Stale Classic Smoke Author) >>');
$classicTableStaleMetadataOffset = $addClassicTableObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($classicTableStaleXmp) . " >>\nstream\n{$classicTableStaleXmp}\nendstream");
$classicTableStaleNameTreeOffset = $addClassicTableObject(8, 0, '<< /Names [(stale-classic-prev-smoke.xml) 10 0 R] >>');
$classicTableStaleFileSpecOffset = $addClassicTableObject(10, 0, '<< /Type /Filespec /F (stale-classic-prev-smoke.xml) /Desc (Stale classic table smoke attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$classicTableStaleEmbeddedOffset = $addClassicTableObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($classicTableStalePayload) . " >>\nstream\n{$classicTableStalePayload}\nendstream");

$classicTablePreviousXrefOffset = strlen($classicTablePdf);
$classicTablePdf .= "xref\n"
    . "0 12\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($classicTableStaleCatalogOffset)
    . $xrefTableRow($classicTableStalePagesOffset)
    . $xrefTableRow($classicTableStalePageOffset)
    . $xrefTableRow($classicTableStaleContentOffset)
    . $xrefTableRow($classicTableFontOffset)
    . $xrefTableRow($classicTableStaleInfoOffset)
    . $xrefTableRow($classicTableStaleMetadataOffset)
    . $xrefTableRow($classicTableStaleNameTreeOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($classicTableStaleFileSpecOffset)
    . $xrefTableRow($classicTableStaleEmbeddedOffset)
    . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$classicTablePreviousXrefOffset}\n%%EOF\n";

$addClassicTableObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addClassicTableObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addClassicTableObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addClassicTableObject(4, 0, "<< /Length " . strlen($classicTableCurrentText) . " >>\nstream\n{$classicTableCurrentText}\nendstream");
$addClassicTableObject(6, 0, '<< /Title (Current Classic Table Smoke Info) /Author (Current Classic Smoke Author) /Producer (Current Classic Smoke Producer) >>');
$addClassicTableObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($classicTableCurrentXmp) . " >>\nstream\n{$classicTableCurrentXmp}\nendstream");
$addClassicTableObject(8, 0, '<< /Names [(current-classic-prev-smoke.xml) 10 0 R] >>');
$addClassicTableObject(10, 0, '<< /Type /Filespec /F (current-classic-prev-smoke.xml) /Desc (Current classic table smoke attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addClassicTableObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($classicTableCurrentPayload) . " >>\nstream\n{$classicTableCurrentPayload}\nendstream");

$classicTableCurrentXrefOffset = strlen($classicTablePdf);
$classicTablePdf .= "xref\n"
    . "1 4\n"
    . $xrefTableRow(0)
    . $xrefTableRow(0)
    . $xrefTableRow(0)
    . $xrefTableRow(0)
    . "6 3\n"
    . $xrefTableRow(0)
    . $xrefTableRow(0)
    . $xrefTableRow(0)
    . "10 2\n"
    . $xrefTableRow(0)
    . $xrefTableRow(0)
    . "trailer\n<< /Size 21 /Root 1 0 R /Info 6 0 R /Prev {$classicTablePreviousXrefOffset} >>\n"
    . "startxref\n{$classicTableCurrentXrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$lines = $extractor->extractTextLines($pdf);
$mismatchMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($mismatchPdf);
$mismatchEncodedMetadata = json_encode($mismatchMetadata, JSON_UNESCAPED_SLASHES);
$mismatchPlainText = $extractor->extractPlainText($mismatchPdf);
$attachmentFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($attachmentPdf);
$attachmentEncoded = json_encode($attachmentFiles, JSON_UNESCAPED_SLASHES);
$malformedIndexMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($malformedIndexPdf);
$malformedIndexPlainText = $extractor->extractPlainText($malformedIndexPdf);
$malformedIndexFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($malformedIndexPdf);
$malformedIndexEncoded = json_encode([
    'metadata' => $malformedIndexMetadata,
    'text' => $malformedIndexPlainText,
    'files' => $malformedIndexFiles,
], JSON_UNESCAPED_SLASHES);
$staleOffsetMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($staleOffsetPdf);
$staleOffsetPlainText = $extractor->extractPlainText($staleOffsetPdf);
$staleOffsetFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($staleOffsetPdf);
$staleOffsetEncoded = json_encode([
    'metadata' => $staleOffsetMetadata,
    'text' => $staleOffsetPlainText,
    'files' => $staleOffsetFiles,
], JSON_UNESCAPED_SLASHES);
$wrongCurrentOffsetMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($wrongCurrentOffsetPdf);
$wrongCurrentOffsetPlainText = $extractor->extractPlainText($wrongCurrentOffsetPdf);
$wrongCurrentOffsetFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($wrongCurrentOffsetPdf);
$wrongCurrentOffsetEncoded = json_encode([
    'metadata' => $wrongCurrentOffsetMetadata,
    'text' => $wrongCurrentOffsetPlainText,
    'files' => $wrongCurrentOffsetFiles,
], JSON_UNESCAPED_SLASHES);
$classicTableMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($classicTablePdf);
$classicTablePlainText = $extractor->extractPlainText($classicTablePdf);
$classicTableFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($classicTablePdf);
$classicTableEncoded = json_encode([
    'metadata' => $classicTableMetadata,
    'text' => $classicTablePlainText,
    'files' => $classicTableFiles,
], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-xref-prev-chain-incremental-update-smoke ' . htmlspecialchars(json_encode([
    'native_boundary' => 'PDF xref /Prev chain current trailer generations repair damaged current in-use offsets before metadata import',
    'current_xmp_title_selected' => ($metadata['title'] ?? null) === 'Current Prev Chain XMP Title',
    'current_info_title_selected' => ($metadata['info']['Title'] ?? null) === 'Current Prev Chain Info Title',
    'current_catalog_language_selected' => ($metadata['language'] ?? null) === 'en-US',
    'current_page_text_selected' => str_contains($plainText, 'Current Prev chain metadata page'),
    'stale_prev_metadata_excluded' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Prev Chain'),
    'stale_prev_text_excluded' => !str_contains($plainText, 'Stale Prev chain metadata page'),
    'generation_mismatch_xmp_excluded' => is_string($mismatchEncodedMetadata)
        && !str_contains($mismatchEncodedMetadata, 'Wrong Generation XMP Title'),
    'generation_mismatch_info_fallback_selected' => ($mismatchMetadata['title'] ?? null) === 'Current Generation Info Title',
    'generation_mismatch_text_selected' => str_contains($mismatchPlainText, 'Metadata generation boundary'),
    'embedded_file_current_attachment_selected' => ($attachmentFiles[0]['filename'] ?? null) === 'current-source.xml',
    'embedded_file_current_payload_selected' => ($attachmentFiles[0]['content'] ?? null) === $currentPayload,
    'embedded_file_stale_prev_attachment_excluded' => is_string($attachmentEncoded)
        && !str_contains($attachmentEncoded, 'stale-prev-attachment')
        && !str_contains($attachmentEncoded, 'stale-source.xml'),
    'malformed_index_same_generation_current_info_selected' => ($malformedIndexMetadata['title'] ?? null) === 'Current Malformed Index Smoke Title',
    'malformed_index_same_generation_current_language_selected' => ($malformedIndexMetadata['language'] ?? null) === 'en-US',
    'malformed_index_same_generation_current_text_selected' => str_contains($malformedIndexPlainText, 'Current malformed index smoke page'),
    'malformed_index_same_generation_current_attachment_selected' => ($malformedIndexFiles[0]['filename'] ?? null) === 'current-malformed-index-smoke.xml',
    'malformed_index_same_generation_stale_prev_excluded' => is_string($malformedIndexEncoded)
        && !str_contains($malformedIndexEncoded, 'Stale Malformed')
        && !str_contains($malformedIndexEncoded, 'stale-malformed-index-smoke'),
    'stale_explicit_offset_current_info_selected' => ($staleOffsetMetadata['title'] ?? null) === 'Current Valid Offset Smoke Info',
    'stale_explicit_offset_current_language_selected' => ($staleOffsetMetadata['language'] ?? null) === 'en-US',
    'stale_explicit_offset_current_text_selected' => str_contains($staleOffsetPlainText, 'Current valid-offset smoke page'),
    'stale_explicit_offset_current_attachment_selected' => ($staleOffsetFiles[0]['filename'] ?? null) === 'current-valid-offset-smoke.xml',
    'stale_explicit_offset_previous_storage_excluded' => is_string($staleOffsetEncoded)
        && !str_contains($staleOffsetEncoded, 'Stale Valid Offset')
        && !str_contains($staleOffsetEncoded, 'stale-valid-offset-smoke'),
    'wrong_current_offset_row_object_current_info_selected' => ($wrongCurrentOffsetMetadata['title'] ?? null) === 'Current Wrong Current Offset Smoke Info',
    'wrong_current_offset_row_object_current_language_selected' => ($wrongCurrentOffsetMetadata['language'] ?? null) === 'en-US',
    'wrong_current_offset_row_object_current_text_selected' => str_contains($wrongCurrentOffsetPlainText, 'Current wrong-current-offset smoke page'),
    'wrong_current_offset_row_object_current_attachment_selected' => ($wrongCurrentOffsetFiles[0]['filename'] ?? null) === 'current-wrong-current-offset-smoke.xml',
    'wrong_current_offset_stale_prev_excluded' => is_string($wrongCurrentOffsetEncoded)
        && !str_contains($wrongCurrentOffsetEncoded, 'Stale Wrong Current Offset')
        && !str_contains($wrongCurrentOffsetEncoded, 'stale-wrong-current-offset-smoke'),
    'classic_table_same_generation_current_xmp_selected' => ($classicTableMetadata['title'] ?? null) === 'Current Classic Table Smoke Title',
    'classic_table_same_generation_current_info_selected' => ($classicTableMetadata['info']['Title'] ?? null) === 'Current Classic Table Smoke Info',
    'classic_table_same_generation_current_text_selected' => str_contains($classicTablePlainText, 'Current classic Prev table smoke page'),
    'classic_table_same_generation_current_attachment_selected' => ($classicTableFiles[0]['filename'] ?? null) === 'current-classic-prev-smoke.xml',
    'classic_table_same_generation_stale_prev_excluded' => is_string($classicTableEncoded)
        && !str_contains($classicTableEncoded, 'Stale Classic')
        && !str_contains($classicTableEncoded, 'stale-classic-prev-smoke'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo '<h2>' . htmlspecialchars((string) ($metadata['title'] ?? 'PDF metadata review'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
echo "<!-- /wp:heading -->\n\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

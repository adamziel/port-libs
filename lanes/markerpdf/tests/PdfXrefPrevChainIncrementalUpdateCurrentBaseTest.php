<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainIncrementalUpdateCurrentBaseXmp = static function (string $title, string $description): string {
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

$xrefPrevChainIncrementalUpdateCurrentBasePdf = static function () use ($xrefPrevChainIncrementalUpdateCurrentBaseXmp): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Prev chain metadata page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current Prev chain metadata page) Tj T* (Incremental update current base) Tj ET';
    $staleXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Stale Prev Chain XMP Title',
        'Stale previous xref metadata must not win'
    ));
    $currentXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Current Prev Chain XMP Title',
        'Current incremental xref metadata selected'
    ));
    if (!is_string($staleXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress xref Prev chain metadata fixture streams.');
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
        throw new RuntimeException('Unable to compress current xref-stream Prev chain fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 1 R /Info 6 1 R /Prev ' . $previousXrefOffset . ' /Index [1 7] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
        . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefPrevChainGenerationMismatchMetadataPdf = static function () use ($xrefPrevChainIncrementalUpdateCurrentBaseXmp): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current generation mismatch page) Tj T* (Metadata generation boundary) Tj ET';
    $currentXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Wrong Generation XMP Title',
        'This generation-one XMP stream is not referenced by the catalog'
    ));
    if (!is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress xref Prev chain generation-mismatch XMP fixture stream.');
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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 7 0 R /Lang (de-DE) >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, '<< /Length 0 >>');
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, 0, '<< /Title (Stale Generation Info Title) /Author (Stale Generation Author) >>');
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Length 0 >>');

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

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /Metadata 7 0 R /Lang (en-US) >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
    $addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 1 R >>');
    $addObject(4, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 1, '<< /Title (Current Generation Info Title) /Author (Current Generation Author) /Producer (Current Generation Producer) >>');
    $addObject(7, 1, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, $offsets['1:1'], 1)
        . $xrefStreamRow(1, $offsets['2:1'], 1)
        . $xrefStreamRow(1, $offsets['3:1'], 1)
        . $xrefStreamRow(1, $offsets['4:1'], 1)
        . $xrefStreamRow(1, $offsets['5:0'], 0)
        . $xrefStreamRow(1, $offsets['6:1'], 1)
        . $xrefStreamRow(1, $offsets['7:1'], 1);
    $compressedCurrentRows = gzcompress($currentRows);
    if (!is_string($compressedCurrentRows)) {
        throw new RuntimeException('Unable to compress current generation-mismatch xref-stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 1 R /Info 6 1 R /Prev ' . $previousXrefOffset . ' /Index [1 7] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
        . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefPrevChainEmbeddedFilesCurrentBasePdf = static function (): string {
    $stalePayload = '<wp-export><post id="stale-prev-attachment"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-prev-attachment"/></wp-export>';

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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [] /Count 0 >>');
    $addObject(6, 0, '<< /Names [(stale-source.xml) 10 0 R] >>');
    $addObject(10, 0, '<< /Type /Filespec /F (stale-source.xml) /Desc (Stale Prev chain attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($offsets['6:0'])
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($offsets['10:0'])
        . $xrefTableRow($offsets['11:0'])
        . "trailer\n<< /Size 12 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 1 R >> >>');
    $addObject(6, 1, '<< /Names [(current-source.xml) 10 1 R] >>');
    $addObject(10, 1, '<< /Type /Filespec /F (current-source.xml) /Desc (Current Prev chain attachment) /AFRelationship /Source /EF << /F 11 1 R >> >>');
    $addObject(11, 1, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, 0, 1)
        . $xrefStreamRow(1, 0, 1)
        . $xrefStreamRow(1, 0, 1)
        . $xrefStreamRow(1, 0, 1);
    $compressedCurrentRows = gzcompress($currentRows);
    if (!is_string($compressedCurrentRows)) {
        throw new RuntimeException('Unable to compress xref Prev chain embedded-file fixture stream.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 1 6 1 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
        . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefPrevChainMalformedIndexSameGenerationPdf = static function () use ($xrefPrevChainIncrementalUpdateCurrentBaseXmp): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale malformed index page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current malformed index page) Tj T* (Same generation offset owner) Tj ET';
    $stalePayload = '<wp-export><post id="stale-malformed-index"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-malformed-index"/></wp-export>';
    $staleXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Stale Malformed Index XMP Title',
        'Stale same-generation metadata must not win'
    ));
    $currentXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Current Malformed Index XMP Title',
        'Current same-generation offset owner selected'
    ));
    if (!is_string($staleXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress malformed-index xref Prev chain fixture streams.');
    }

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Malformed Index Info Title) /Author (Stale Malformed Author) /Producer (Stale Malformed Producer) >>');
    $staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-malformed-index.xml) 10 0 R] >>');
    $staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-malformed-index.xml) /Desc (Stale malformed index attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $staleEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($staleCatalogOffset)
        . $xrefTableRow($stalePagesOffset)
        . $xrefTableRow($stalePageOffset)
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow($fontOffset)
        . $xrefTableRow($staleInfoOffset)
        . $xrefTableRow($staleMetadataOffset)
        . $xrefTableRow($staleNameTreeOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($staleFileSpecOffset)
        . $xrefTableRow($staleEmbeddedFileOffset)
        . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $currentInfoOffset = $addObject(6, 0, '<< /Title (Current Malformed Index Info Title) /Author (Current Malformed Author) /Producer (Current Malformed Producer) >>');
    $currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $currentNameTreeOffset = $addObject(8, 0, '<< /Names [(current-malformed-index.xml) 10 0 R] >>');
    $currentFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (current-malformed-index.xml) /Desc (Current malformed index attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $currentEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, $currentCatalogOffset, 0)
        . $xrefStreamRow(1, $currentPagesOffset, 0)
        . $xrefStreamRow(1, $currentPageOffset, 0)
        . $xrefStreamRow(1, $currentContentOffset, 0)
        . $xrefStreamRow(1, $fontOffset, 0)
        . $xrefStreamRow(1, $currentInfoOffset, 0)
        . $xrefStreamRow(1, $currentMetadataOffset, 0)
        . $xrefStreamRow(1, $currentNameTreeOffset, 0)
        . $xrefStreamRow(1, $currentFileSpecOffset, 0)
        . $xrefStreamRow(1, $currentEmbeddedFileOffset, 0);
    $compressedCurrentRows = gzcompress($currentRows);
    if (!is_string($compressedCurrentRows)) {
        throw new RuntimeException('Unable to compress malformed-index current xref-stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 40 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [30 10] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
        . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefPrevChainSameGenerationDamagedOffsetPdf = static function () use ($xrefPrevChainIncrementalUpdateCurrentBaseXmp): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale same generation Prev page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current same generation Prev page) Tj T* (Damaged offset repaired) Tj ET';
    $stalePayload = '<wp-export><post id="stale-same-generation"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-same-generation"/></wp-export>';
    $staleXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Stale Same Generation XMP Title',
        'Stale same-generation metadata must not win'
    ));
    $currentXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Current Same Generation XMP Title',
        'Current same-generation damaged offsets repaired'
    ));
    if (!is_string($staleXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress same-generation xref Prev chain fixture streams.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Same Generation Info Title) /Author (Stale Same Generation Author) /Producer (Stale Same Generation Producer) >>');
    $staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-same-generation.xml) 10 0 R] >>');
    $staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-same-generation.xml) /Desc (Stale same-generation attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $staleEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($staleCatalogOffset)
        . $xrefTableRow($stalePagesOffset)
        . $xrefTableRow($stalePageOffset)
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow($fontOffset)
        . $xrefTableRow($staleInfoOffset)
        . $xrefTableRow($staleMetadataOffset)
        . $xrefTableRow($staleNameTreeOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($staleFileSpecOffset)
        . $xrefTableRow($staleEmbeddedFileOffset)
        . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Title (Current Same Generation Info Title) /Author (Current Same Generation Author) /Producer (Current Same Generation Producer) >>');
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(8, 0, '<< /Names [(current-same-generation.xml) 10 0 R] >>');
    $addObject(10, 0, '<< /Type /Filespec /F (current-same-generation.xml) /Desc (Current same-generation attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, $fontOffset, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0);
    $compressedCurrentRows = gzcompress($currentRows);
    if (!is_string($compressedCurrentRows)) {
        throw new RuntimeException('Unable to compress same-generation current xref-stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [1 8 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
        . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefPrevChainSameGenerationStaleOffsetPdf = static function () use ($xrefPrevChainIncrementalUpdateCurrentBaseXmp): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale valid-offset Prev page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current valid-offset Prev page) Tj T* (Stale offset repaired) Tj ET';
    $stalePayload = '<wp-export><post id="stale-valid-offset"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-valid-offset"/></wp-export>';
    $staleXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Stale Valid Offset XMP Title',
        'Stale valid previous offset must not win'
    ));
    $currentXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Current Valid Offset XMP Title',
        'Current update object repaired from stale explicit offsets'
    ));
    if (!is_string($staleXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress stale-offset xref Prev chain fixture streams.');
    }

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Valid Offset Info Title) /Author (Stale Valid Offset Author) /Producer (Stale Valid Offset Producer) >>');
    $staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-valid-offset.xml) 10 0 R] >>');
    $staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-valid-offset.xml) /Desc (Stale valid-offset attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $staleEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($staleCatalogOffset)
        . $xrefTableRow($stalePagesOffset)
        . $xrefTableRow($stalePageOffset)
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow($fontOffset)
        . $xrefTableRow($staleInfoOffset)
        . $xrefTableRow($staleMetadataOffset)
        . $xrefTableRow($staleNameTreeOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($staleFileSpecOffset)
        . $xrefTableRow($staleEmbeddedFileOffset)
        . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Title (Current Valid Offset Info Title) /Author (Current Valid Offset Author) /Producer (Current Valid Offset Producer) >>');
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(8, 0, '<< /Names [(current-valid-offset.xml) 10 0 R] >>');
    $addObject(10, 0, '<< /Type /Filespec /F (current-valid-offset.xml) /Desc (Current valid-offset attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, $staleCatalogOffset, 0)
        . $xrefStreamRow(1, $stalePagesOffset, 0)
        . $xrefStreamRow(1, $stalePageOffset, 0)
        . $xrefStreamRow(1, $staleContentOffset, 0)
        . $xrefStreamRow(1, $fontOffset, 0)
        . $xrefStreamRow(1, $staleInfoOffset, 0)
        . $xrefStreamRow(1, $staleMetadataOffset, 0)
        . $xrefStreamRow(1, $staleNameTreeOffset, 0)
        . $xrefStreamRow(1, $staleFileSpecOffset, 0)
        . $xrefStreamRow(1, $staleEmbeddedFileOffset, 0);
    $compressedCurrentRows = gzcompress($currentRows);
    if (!is_string($compressedCurrentRows)) {
        throw new RuntimeException('Unable to compress valid-offset current xref-stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [1 8 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
        . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefPrevChainSameGenerationWrongCurrentOffsetPdf = static function () use ($xrefPrevChainIncrementalUpdateCurrentBaseXmp): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale wrong-current-offset Prev page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current wrong-current-offset Prev page) Tj T* (Row object repaired before offset owner) Tj ET';
    $stalePayload = '<wp-export><post id="stale-wrong-current-offset"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-wrong-current-offset"/></wp-export>';
    $staleXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Stale Wrong Current Offset XMP Title',
        'Stale wrong-current-offset metadata must not win'
    ));
    $currentXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Current Wrong Current Offset XMP Title',
        'Current row object wins before wrong offset owner'
    ));
    if (!is_string($staleXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress wrong-current-offset xref Prev chain fixture streams.');
    }

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Wrong Current Offset Info Title) /Author (Stale Wrong Current Author) /Producer (Stale Wrong Current Producer) >>');
    $staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-wrong-current-offset.xml) 10 0 R] >>');
    $staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-wrong-current-offset.xml) /Desc (Stale wrong-current-offset attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $staleEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($staleCatalogOffset)
        . $xrefTableRow($stalePagesOffset)
        . $xrefTableRow($stalePageOffset)
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow($fontOffset)
        . $xrefTableRow($staleInfoOffset)
        . $xrefTableRow($staleMetadataOffset)
        . $xrefTableRow($staleNameTreeOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($staleFileSpecOffset)
        . $xrefTableRow($staleEmbeddedFileOffset)
        . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $currentInfoOffset = $addObject(6, 0, '<< /Title (Current Wrong Current Offset Info Title) /Author (Current Wrong Current Author) /Producer (Current Wrong Current Producer) >>');
    $currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $currentNameTreeOffset = $addObject(8, 0, '<< /Names [(current-wrong-current-offset.xml) 10 0 R] >>');
    $currentFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (current-wrong-current-offset.xml) /Desc (Current wrong-current-offset attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $currentEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, $currentPagesOffset, 0)
        . $xrefStreamRow(1, $currentPagesOffset, 0)
        . $xrefStreamRow(1, $currentPageOffset, 0)
        . $xrefStreamRow(1, $currentContentOffset, 0)
        . $xrefStreamRow(1, $fontOffset, 0)
        . $xrefStreamRow(1, $currentInfoOffset, 0)
        . $xrefStreamRow(1, $currentMetadataOffset, 0)
        . $xrefStreamRow(1, $currentNameTreeOffset, 0)
        . $xrefStreamRow(1, $currentFileSpecOffset, 0)
        . $xrefStreamRow(1, $currentEmbeddedFileOffset, 0);
    $compressedCurrentRows = gzcompress($currentRows);
    if (!is_string($compressedCurrentRows)) {
        throw new RuntimeException('Unable to compress wrong-current-offset xref-stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [1 8 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
        . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefPrevChainClassicTableDamagedOffsetPdf = static function () use ($xrefPrevChainIncrementalUpdateCurrentBaseXmp): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale classic Prev table page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current classic Prev table page) Tj T* (Classic table offset repaired) Tj ET';
    $stalePayload = '<wp-export><post id="stale-classic-prev"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-classic-prev"/></wp-export>';
    $staleXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Stale Classic Table XMP Title',
        'Stale classic table metadata must not win'
    ));
    $currentXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Current Classic Table XMP Title',
        'Current classic table damaged offsets repaired'
    ));
    if (!is_string($staleXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress classic-table xref Prev chain fixture streams.');
    }

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Classic Table Info Title) /Author (Stale Classic Author) /Producer (Stale Classic Producer) >>');
    $staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-classic-prev.xml) 10 0 R] >>');
    $staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-classic-prev.xml) /Desc (Stale classic Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $staleEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($staleCatalogOffset)
        . $xrefTableRow($stalePagesOffset)
        . $xrefTableRow($stalePageOffset)
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow($fontOffset)
        . $xrefTableRow($staleInfoOffset)
        . $xrefTableRow($staleMetadataOffset)
        . $xrefTableRow($staleNameTreeOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($staleFileSpecOffset)
        . $xrefTableRow($staleEmbeddedFileOffset)
        . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Title (Current Classic Table Info Title) /Author (Current Classic Author) /Producer (Current Classic Producer) >>');
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(8, 0, '<< /Names [(current-classic-prev.xml) 10 0 R] >>');
    $addObject(10, 0, '<< /Type /Filespec /F (current-classic-prev.xml) /Desc (Current classic Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
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
        . "trailer\n<< /Size 21 /Root 1 0 R /Info 6 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefPrevChainClassicTableIndirectPrevOffsetPdf = static function () use ($xrefPrevChainIncrementalUpdateCurrentBaseXmp): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale indirect Prev helper page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current indirect Prev helper page) Tj T* (Indirect Prev offset repaired) Tj ET';
    $stalePayload = '<wp-export><post id="stale-indirect-prev"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-indirect-prev"/></wp-export>';
    $staleXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Stale Indirect Prev XMP Title',
        'Stale indirect Prev metadata must not win'
    ));
    $currentXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Current Indirect Prev XMP Title',
        'Current classic table indirect Prev offsets repaired'
    ));
    if (!is_string($staleXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress indirect-Prev xref Prev chain fixture streams.');
    }

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Indirect Prev Info Title) /Author (Stale Indirect Author) /Producer (Stale Indirect Producer) >>');
    $staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-indirect-prev.xml) 10 0 R] >>');
    $staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-indirect-prev.xml) /Desc (Stale indirect Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $staleEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($staleCatalogOffset)
        . $xrefTableRow($stalePagesOffset)
        . $xrefTableRow($stalePageOffset)
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow($fontOffset)
        . $xrefTableRow($staleInfoOffset)
        . $xrefTableRow($staleMetadataOffset)
        . $xrefTableRow($staleNameTreeOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($staleFileSpecOffset)
        . $xrefTableRow($staleEmbeddedFileOffset)
        . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Title (Current Indirect Prev Info Title) /Author (Current Indirect Author) /Producer (Current Indirect Producer) >>');
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(8, 0, '<< /Names [(current-indirect-prev.xml) 10 0 R] >>');
    $addObject(10, 0, '<< /Type /Filespec /F (current-indirect-prev.xml) /Desc (Current indirect Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
    $prevHelperOffset = $addObject(30, 0, (string) $previousXrefOffset);

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
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
        . "30 1\n"
        . $xrefTableRow($prevHelperOffset)
        . "trailer\n<< /Size 31 /Root 1 0 R /Info 6 0 R /Prev 30 0 R >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefPrevChainStreamIndirectOperandsPdf = static function () use ($xrefPrevChainIncrementalUpdateCurrentBaseXmp): string {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous indirect xref operand page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current indirect xref operand page) Tj T* (Indirect W Index operands selected) Tj ET';
    $staleDecoyContent = 'BT /F1 12 Tf 72 720 Td (Stale post xref decoy page) Tj ET';
    $previousPayload = '<wp-export><post id="previous-indirect-operands"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-indirect-operands"/></wp-export>';
    $staleDecoyPayload = '<wp-export><post id="stale-post-xref-decoy"/></wp-export>';
    $previousXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Previous Indirect Operand XMP Title',
        'Previous xref section metadata must not win'
    ));
    $currentXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Current Indirect Operand XMP Title',
        'Current xref-stream indirect operands selected'
    ));
    $staleDecoyXmp = gzcompress($xrefPrevChainIncrementalUpdateCurrentBaseXmp(
        'Stale Post Xref Decoy XMP Title',
        'Unreferenced post-xref direct objects must not win'
    ));
    if (!is_string($previousXmp) || !is_string($currentXmp) || !is_string($staleDecoyXmp)) {
        throw new RuntimeException('Unable to compress xref-stream indirect operand fixture streams.');
    }

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $previousCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (es-ES) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $previousPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $previousPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $previousContentOffset = $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $previousInfoOffset = $addObject(6, 0, '<< /Title (Previous Indirect Operand Info Title) /Author (Previous Indirect Author) /Producer (Previous Indirect Producer) >>');
    $previousMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($previousXmp) . " >>\nstream\n{$previousXmp}\nendstream");
    $previousNameTreeOffset = $addObject(8, 0, '<< /Names [(previous-indirect-operands.xml) 10 0 R] >>');
    $previousFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (previous-indirect-operands.xml) /Desc (Previous indirect operand attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $previousEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($previousPayload) . " >>\nstream\n{$previousPayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($previousCatalogOffset)
        . $xrefTableRow($previousPagesOffset)
        . $xrefTableRow($previousPageOffset)
        . $xrefTableRow($previousContentOffset)
        . $xrefTableRow($fontOffset)
        . $xrefTableRow($previousInfoOffset)
        . $xrefTableRow($previousMetadataOffset)
        . $xrefTableRow($previousNameTreeOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($previousFileSpecOffset)
        . $xrefTableRow($previousEmbeddedFileOffset)
        . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $currentInfoOffset = $addObject(6, 0, '<< /Title (Current Indirect Operand Info Title) /Author (Current Indirect Author) /Producer (Current Indirect Producer) >>');
    $currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $currentNameTreeOffset = $addObject(8, 0, '<< /Names [(current-indirect-operands.xml) 10 0 R] >>');
    $currentFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (current-indirect-operands.xml) /Desc (Current indirect operand attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $currentEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
    $addObject(30, 0, '[1 4 1]');
    $addObject(31, 0, '[1 8 10 2]');

    $currentRows = ''
        . $xrefStreamRow(1, $currentCatalogOffset, 0)
        . $xrefStreamRow(1, $currentPagesOffset, 0)
        . $xrefStreamRow(1, $currentPageOffset, 0)
        . $xrefStreamRow(1, $currentContentOffset, 0)
        . $xrefStreamRow(1, $fontOffset, 0)
        . $xrefStreamRow(1, $currentInfoOffset, 0)
        . $xrefStreamRow(1, $currentMetadataOffset, 0)
        . $xrefStreamRow(1, $currentNameTreeOffset, 0)
        . $xrefStreamRow(1, $currentFileSpecOffset, 0)
        . $xrefStreamRow(1, $currentEmbeddedFileOffset, 0);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress xref-stream indirect operand fixture rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 32 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /W 30 0 R /Index 31 0 R /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (it-IT) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($staleDecoyContent) . " >>\nstream\n{$staleDecoyContent}\nendstream");
    $addObject(6, 0, '<< /Title (Stale Post Xref Decoy Info Title) /Author (Stale Decoy Author) /Producer (Stale Decoy Producer) >>');
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleDecoyXmp) . " >>\nstream\n{$staleDecoyXmp}\nendstream");
    $addObject(8, 0, '<< /Names [(stale-post-xref-decoy.xml) 10 0 R] >>');
    $addObject(10, 0, '<< /Type /Filespec /F (stale-post-xref-decoy.xml) /Desc (Stale post-xref decoy attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($staleDecoyPayload) . " >>\nstream\n{$staleDecoyPayload}\nendstream");

    $pdf .= "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'repairs current metadata generation objects through damaged xref Prev chain offsets' => static function (
        TestRunner $t
    ) use ($xrefPrevChainIncrementalUpdateCurrentBasePdf): void {
        $pdf = $xrefPrevChainIncrementalUpdateCurrentBasePdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['Current Prev chain metadata page', 'Incremental update current base'], $extractor->extractTextLines($pdf));
        $t->same("Current Prev chain metadata page\nIncremental update current base", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Prev Chain XMP Title', $metadata['title']);
        $t->same('Current incremental xref metadata selected', $metadata['description']);
        $t->same('Current Prev Chain Info Title', $metadata['info']['Title']);
        $t->same(['Current Prev Author'], $metadata['authors']);
        $t->same('Current Prev Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same('2026-06-03T09:30:09Z', $metadata['created_at_utc']);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Prev Chain'));
        $t->true(!str_contains($text, 'Stale Prev chain metadata page'));
        $t->true(!str_contains($text, "\0"));
    },
    'does not resolve generation-zero catalog Metadata to a generation-one current xref object' => static function (
        TestRunner $t
    ) use ($xrefPrevChainGenerationMismatchMetadataPdf): void {
        $pdf = $xrefPrevChainGenerationMismatchMetadataPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['Current generation mismatch page', 'Metadata generation boundary'], $extractor->extractTextLines($pdf));
        $t->same("Current generation mismatch page\nMetadata generation boundary", $text);
        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same('Current Generation Info Title', $metadata['title']);
        $t->same(['Current Generation Author'], $metadata['authors']);
        $t->same('Current Generation Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Wrong Generation XMP Title'));
        $t->true(!str_contains($text, 'Wrong Generation XMP Title'));
        $t->true(!str_contains($text, "\0"));
    },
    'repairs trailer Root generation before embedded-file name-tree attachment import' => static function (
        TestRunner $t
    ) use ($xrefPrevChainEmbeddedFilesCurrentBasePdf): void {
        $pdf = $xrefPrevChainEmbeddedFilesCurrentBasePdf();
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $encoded = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, count($files));
        $t->same('catalog_names_embedded_files', $files[0]['source']);
        $t->same('current-source.xml', $files[0]['name']);
        $t->same('current-source.xml', $files[0]['filename']);
        $t->same('Current Prev chain attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same('text/xml', $files[0]['mime_type']);
        $t->same(10, $files[0]['file_spec_object']);
        $t->same(11, $files[0]['embedded_file_object']);
        $t->same('<wp-export><post id="current-prev-attachment"/></wp-export>', $files[0]['content']);
        $t->same(strlen('<wp-export><post id="current-prev-attachment"/></wp-export>'), $files[0]['size']);
        $t->same(hash('sha256', '<wp-export><post id="current-prev-attachment"/></wp-export>'), $files[0]['content_sha256']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-source.xml'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-prev-attachment'));
    },
    'repairs malformed current xref-stream Index rows by direct offsets before same-generation metadata and attachments' => static function (
        TestRunner $t
    ) use ($xrefPrevChainMalformedIndexSameGenerationPdf): void {
        $pdf = $xrefPrevChainMalformedIndexSameGenerationPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $extractor = new PdfTextExtractor();
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(['Current malformed index page', 'Same generation offset owner'], $extractor->extractTextLines($pdf));
        $t->same("Current malformed index page\nSame generation offset owner", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Malformed Index XMP Title', $metadata['title']);
        $t->same('Current same-generation offset owner selected', $metadata['description']);
        $t->same('Current Malformed Index Info Title', $metadata['info']['Title']);
        $t->same(['Current Malformed Author'], $metadata['authors']);
        $t->same('Current Malformed Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same(1, count($files));
        $t->same('current-malformed-index.xml', $files[0]['filename']);
        $t->same('Current malformed index attachment', $files[0]['description']);
        $t->same('<wp-export><post id="current-malformed-index"/></wp-export>', $files[0]['content']);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Malformed'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-malformed-index'));
        $t->true(!str_contains($text, 'Stale malformed index page'));
        $t->true(!str_contains($text, "\0"));
    },
    'repairs same-generation current update objects when xref-stream Prev rows have damaged explicit offsets' => static function (
        TestRunner $t
    ) use ($xrefPrevChainSameGenerationDamagedOffsetPdf): void {
        $pdf = $xrefPrevChainSameGenerationDamagedOffsetPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $extractor = new PdfTextExtractor();
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(['Current same generation Prev page', 'Damaged offset repaired'], $extractor->extractTextLines($pdf));
        $t->same("Current same generation Prev page\nDamaged offset repaired", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Same Generation XMP Title', $metadata['title']);
        $t->same('Current same-generation damaged offsets repaired', $metadata['description']);
        $t->same('Current Same Generation Info Title', $metadata['info']['Title']);
        $t->same(['Current Same Generation Author'], $metadata['authors']);
        $t->same('Current Same Generation Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same(1, count($files));
        $t->same('current-same-generation.xml', $files[0]['filename']);
        $t->same('Current same-generation attachment', $files[0]['description']);
        $t->same('<wp-export><post id="current-same-generation"/></wp-export>', $files[0]['content']);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Same Generation'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-same-generation'));
        $t->true(!str_contains($text, 'Stale same generation Prev page'));
        $t->true(!str_contains($text, "\0"));
    },
    'repairs same-generation current update objects when xref-stream Prev rows point at stale explicit offsets' => static function (
        TestRunner $t
    ) use ($xrefPrevChainSameGenerationStaleOffsetPdf): void {
        $pdf = $xrefPrevChainSameGenerationStaleOffsetPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $extractor = new PdfTextExtractor();
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(['Current valid-offset Prev page', 'Stale offset repaired'], $extractor->extractTextLines($pdf));
        $t->same("Current valid-offset Prev page\nStale offset repaired", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Valid Offset XMP Title', $metadata['title']);
        $t->same('Current update object repaired from stale explicit offsets', $metadata['description']);
        $t->same('Current Valid Offset Info Title', $metadata['info']['Title']);
        $t->same(['Current Valid Offset Author'], $metadata['authors']);
        $t->same('Current Valid Offset Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same(1, count($files));
        $t->same('current-valid-offset.xml', $files[0]['filename']);
        $t->same('Current valid-offset attachment', $files[0]['description']);
        $t->same('<wp-export><post id="current-valid-offset"/></wp-export>', $files[0]['content']);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Valid Offset'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-valid-offset'));
        $t->true(!str_contains($text, 'Stale valid-offset Prev page'));
        $t->true(!str_contains($text, "\0"));
    },
    'repairs same-generation xref-stream rows whose damaged offsets point at a different current object' => static function (
        TestRunner $t
    ) use ($xrefPrevChainSameGenerationWrongCurrentOffsetPdf): void {
        $pdf = $xrefPrevChainSameGenerationWrongCurrentOffsetPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $extractor = new PdfTextExtractor();
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(['Current wrong-current-offset Prev page', 'Row object repaired before offset owner'], $extractor->extractTextLines($pdf));
        $t->same("Current wrong-current-offset Prev page\nRow object repaired before offset owner", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Wrong Current Offset XMP Title', $metadata['title']);
        $t->same('Current row object wins before wrong offset owner', $metadata['description']);
        $t->same('Current Wrong Current Offset Info Title', $metadata['info']['Title']);
        $t->same(['Current Wrong Current Author'], $metadata['authors']);
        $t->same('Current Wrong Current Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same(1, count($files));
        $t->same('current-wrong-current-offset.xml', $files[0]['filename']);
        $t->same('Current wrong-current-offset attachment', $files[0]['description']);
        $t->same('<wp-export><post id="current-wrong-current-offset"/></wp-export>', $files[0]['content']);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Wrong Current Offset'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-wrong-current-offset'));
        $t->true(!str_contains($text, 'Stale wrong-current-offset Prev page'));
        $t->true(!str_contains($text, "\0"));
    },
    'repairs same-generation current update objects when classic xref Prev rows have damaged explicit offsets' => static function (
        TestRunner $t
    ) use ($xrefPrevChainClassicTableDamagedOffsetPdf): void {
        $pdf = $xrefPrevChainClassicTableDamagedOffsetPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $extractor = new PdfTextExtractor();
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(['Current classic Prev table page', 'Classic table offset repaired'], $extractor->extractTextLines($pdf));
        $t->same("Current classic Prev table page\nClassic table offset repaired", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Classic Table XMP Title', $metadata['title']);
        $t->same('Current classic table damaged offsets repaired', $metadata['description']);
        $t->same('Current Classic Table Info Title', $metadata['info']['Title']);
        $t->same(['Current Classic Author'], $metadata['authors']);
        $t->same('Current Classic Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same(1, count($files));
        $t->same('current-classic-prev.xml', $files[0]['filename']);
        $t->same('Current classic Prev attachment', $files[0]['description']);
        $t->same('<wp-export><post id="current-classic-prev"/></wp-export>', $files[0]['content']);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Classic'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-classic-prev'));
        $t->true(!str_contains($text, 'Stale classic Prev table page'));
        $t->true(!str_contains($text, "\0"));
    },
    'repairs classic xref-table current update rows when Prev is an indirect numeric helper' => static function (
        TestRunner $t
    ) use ($xrefPrevChainClassicTableIndirectPrevOffsetPdf): void {
        $pdf = $xrefPrevChainClassicTableIndirectPrevOffsetPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $extractor = new PdfTextExtractor();
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(['Current indirect Prev helper page', 'Indirect Prev offset repaired'], $extractor->extractTextLines($pdf));
        $t->same("Current indirect Prev helper page\nIndirect Prev offset repaired", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Indirect Prev XMP Title', $metadata['title']);
        $t->same('Current classic table indirect Prev offsets repaired', $metadata['description']);
        $t->same('Current Indirect Prev Info Title', $metadata['info']['Title']);
        $t->same(['Current Indirect Author'], $metadata['authors']);
        $t->same('Current Indirect Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same(1, count($files));
        $t->same('current-indirect-prev.xml', $files[0]['filename']);
        $t->same('Current indirect Prev attachment', $files[0]['description']);
        $t->same('<wp-export><post id="current-indirect-prev"/></wp-export>', $files[0]['content']);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Indirect Prev'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-indirect-prev'));
        $t->true(!str_contains($text, 'Stale indirect Prev helper page'));
        $t->true(!str_contains($text, "\0"));
    },
    'resolves xref-stream W and Index helpers before stale post-xref direct objects' => static function (
        TestRunner $t
    ) use ($xrefPrevChainStreamIndirectOperandsPdf): void {
        $pdf = $xrefPrevChainStreamIndirectOperandsPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $extractor = new PdfTextExtractor();
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(['Current indirect xref operand page', 'Indirect W Index operands selected'], $extractor->extractTextLines($pdf));
        $t->same("Current indirect xref operand page\nIndirect W Index operands selected", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Indirect Operand XMP Title', $metadata['title']);
        $t->same('Current xref-stream indirect operands selected', $metadata['description']);
        $t->same('Current Indirect Operand Info Title', $metadata['info']['Title']);
        $t->same(['Current Indirect Author'], $metadata['authors']);
        $t->same('Current Indirect Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same(1, count($files));
        $t->same('current-indirect-operands.xml', $files[0]['filename']);
        $t->same('Current indirect operand attachment', $files[0]['description']);
        $t->same('<wp-export><post id="current-indirect-operands"/></wp-export>', $files[0]['content']);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Post Xref Decoy'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-post-xref-decoy'));
        $t->true(!str_contains($text, 'Stale post xref decoy page'));
        $t->true(!str_contains($text, 'Previous indirect xref operand page'));
        $t->true(!str_contains($text, "\0"));
    },
];

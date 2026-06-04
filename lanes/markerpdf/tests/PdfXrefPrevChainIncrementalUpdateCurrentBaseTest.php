<?php

declare(strict_types=1);

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
];

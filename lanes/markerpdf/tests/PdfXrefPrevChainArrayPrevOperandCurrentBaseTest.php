<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainArrayPrevXmp = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-08T18:04:20Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xrefPrevChainArrayPrevPdf = static function () use ($xrefPrevChainArrayPrevXmp): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale array Prev page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current array Prev page) Tj T* (Array Prev helper repaired) Tj ET';
    $stalePayload = '<wp-export><post id="stale-array-prev"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-array-prev"/></wp-export>';
    $staleXmp = gzcompress($xrefPrevChainArrayPrevXmp(
        'Stale Array Prev XMP Title',
        'Stale array Prev metadata must not win'
    ));
    $currentXmp = gzcompress($xrefPrevChainArrayPrevXmp(
        'Current Array Prev XMP Title',
        'Current array-wrapped Prev helper selected'
    ));
    if (!is_string($staleXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress xref array Prev fixture streams.');
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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, 0, '<< /Title (Stale Array Prev Info Title) /Author (Stale Array Prev Author) /Producer (Stale Array Prev Producer) >>');
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $addObject(8, 0, '<< /Names [(stale-array-prev.xml) 10 0 R] >>');
    $addObject(10, 0, '<< /Type /Filespec /F (stale-array-prev.xml) /Desc (Stale array Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow($offsets['3:0'])
        . $xrefTableRow($offsets['4:0'])
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow($offsets['6:0'])
        . $xrefTableRow($offsets['7:0'])
        . $xrefTableRow($offsets['8:0'])
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($offsets['10:0'])
        . $xrefTableRow($offsets['11:0'])
        . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Title (Current Array Prev Info Title) /Author (Current Array Prev Author) /Producer (Current Array Prev Producer) >>');
    $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(8, 0, '<< /Names [(current-array-prev.xml) 10 0 R] >>');
    $addObject(10, 0, '<< /Type /Filespec /F (current-array-prev.xml) /Desc (Current array Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
    $prevHelperOffset = $addObject(30, 0, (string) $previousXrefOffset);

    $currentRows = $xrefStreamRow(1, $prevHelperOffset, 0);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress array Prev current xref-stream rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Info 6 0 R /Prev [30 0 R] /Index [30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefPrevChainArrayPrevActionPdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale array Prev action link) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current array Prev action docs) Tj ET';

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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 274 718] /F 4 /Contents (Array Prev action review link) /A 8 0 R /AA << /E 9 0 R >> >>');
    $addObject(8, 0, '<< /S /URI /URI (https://example.com/stale-array-prev-action) >>');
    $addObject(9, 0, '<< /S /JavaScript /JS (staleArrayPrevHover()) >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 10\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow($offsets['3:0'])
        . $xrefTableRow($offsets['4:0'])
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($offsets['7:0'])
        . $xrefTableRow($offsets['8:0'])
        . $xrefTableRow($offsets['9:0'])
        . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";
    $previousOffsets = $offsets;

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 274 718] /F 4 /Contents (Array Prev action review link) /A 8 0 R /AA << /E 9 0 R >> >>');
    $addObject(8, 0, '<< /S /URI /URI (https://example.com/current-array-prev-action) >>');
    $addObject(9, 0, '<< /S /URI /URI (mailto:current-array-prev-action@example.test) >>');
    $prevHelperOffset = $addObject(30, 0, (string) $previousXrefOffset);

    $currentRows = ''
        . $xrefStreamRow(1, $offsets['1:0'], 0)
        . $xrefStreamRow(1, $offsets['2:0'], 0)
        . $xrefStreamRow(1, $offsets['3:0'], 0)
        . $xrefStreamRow(1, $offsets['4:0'], 0)
        . $xrefStreamRow(1, $previousOffsets['5:0'], 0)
        . $xrefStreamRow(1, $offsets['7:0'], 0)
        . $xrefStreamRow(1, $previousOffsets['8:0'], 0)
        . $xrefStreamRow(1, $previousOffsets['9:0'], 0)
        . $xrefStreamRow(1, $prevHelperOffset, 0);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress array Prev action xref rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Prev [30 0 R] /Index [1 5 7 3 30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefPrevChainArrayPrevActionPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 274.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 274.0, 718.0],
                'spans' => [[
                    'text' => 'Current array Prev action docs',
                    'bbox' => [72.0, 700.0, 274.0, 718.0],
                    'font' => 'Helvetica',
                ]],
            ]],
        ]],
    ]];
};

return [
    'repairs array-wrapped xref Prev helper before current metadata and attachments' => static function (
        TestRunner $t
    ) use ($xrefPrevChainArrayPrevPdf): void {
        $pdf = $xrefPrevChainArrayPrevPdf();
        $extractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $currentPayload = '<wp-export><post id="current-array-prev"/></wp-export>';

        $t->true(str_contains($pdf, '/Prev [30 0 R]'), 'fixture stores xref-stream /Prev as a single-item helper array');
        $t->true(str_contains($pdf, 'Stale array Prev page'), 'fixture contains stale previous-section content');
        $t->true(str_contains($pdf, 'Current array Prev page'), 'fixture contains current update content');
        $t->same(['Current array Prev page', 'Array Prev helper repaired'], $extractor->extractTextLines($pdf));
        $t->same("Current array Prev page\nArray Prev helper repaired", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Array Prev XMP Title', $metadata['title']);
        $t->same('Current array-wrapped Prev helper selected', $metadata['description']);
        $t->same('Current Array Prev Info Title', $metadata['info']['Title']);
        $t->same(['Current Array Prev Author'], $metadata['authors']);
        $t->same('Current Array Prev Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same('2026-06-08T18:04:20Z', $metadata['created_at_utc']);
        $t->same(1, count($files));
        $t->same('current-array-prev.xml', $files[0]['filename']);
        $t->same('Current array Prev attachment', $files[0]['description']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same(strlen($currentPayload), $files[0]['size']);
        $t->same(hash('sha256', $currentPayload), $files[0]['content_sha256']);
        $t->same(1, $summary['attachment_count']);
        $t->same(['current-array-prev.xml'], $summary['filenames']);
        $t->same('current-array-prev.xml', $summary['attachments'][0]['filename'] ?? null);
        $t->same('Current array Prev attachment', $summary['attachments'][0]['description'] ?? null);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Array Prev'), 'stale metadata is excluded');
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-array-prev'), 'stale embedded file is excluded');
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'stale-array-prev'), 'stale attachment summary is excluded');
        $t->true(!str_contains($text, 'Stale array Prev page'), 'stale text is excluded');
        $t->true(!str_contains($text, "\0"), 'text extraction stays free of NUL bytes');
    },
    'repairs array-wrapped xref Prev helper before action review current rows' => static function (
        TestRunner $t
    ) use ($xrefPrevChainArrayPrevActionPdf, $xrefPrevChainArrayPrevActionPages): void {
        $pdf = $xrefPrevChainArrayPrevActionPdf();

        $t->true(str_contains($pdf, '/Prev [30 0 R]'), 'fixture stores action xref /Prev as a single-item helper array');
        $t->true(str_contains($pdf, 'https://example.com/stale-array-prev-action'), 'fixture contains stale action bytes');
        $t->true(str_contains($pdf, 'https://example.com/current-array-prev-action'), 'fixture contains current action bytes');

        $annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotationPages), 'one annotation page is extracted');
        $annotation = $annotationPages[0]['annotations'][0];
        $t->same(7, $annotation['annotation_object'], 'current annotation object is selected');
        $t->same('https://example.com/current-array-prev-action', $annotation['actions'][0]['uri'], 'current primary action wins through array Prev repair');
        $t->same('mailto:current-array-prev-action@example.test', $annotation['additional_actions'][0]['uri'], 'current additional action wins through array Prev repair');

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links), 'one link page is extracted');
        $t->same(1, count($links[0]['links']), 'one link is promoted');
        $t->same('https://example.com/current-array-prev-action', $links[0]['links'][0]['uri'], 'promoted link uses the current URI');
        $t->same('mailto:current-array-prev-action@example.test', $links[0]['links'][0]['additional_actions'][0]['uri'], 'promoted link carries current additional action review');

        $linkedPages = $linkExtractor->applyLinksToPages($xrefPrevChainArrayPrevActionPages(), $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $t->same('https://example.com/current-array-prev-action', $span['link_uri'], 'WordPress span promotion uses current URI');
        $t->same('mailto:current-array-prev-action@example.test', $span['link_additional_actions_review'][0]['uri'], 'WordPress span review uses current additional action');

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('[Current array Prev action docs](https://example.com/current-array-prev-action)', $blocks[0]['text'], 'Markdown block promotes the current URI');

        $encoded = json_encode([$annotationPages, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'stale-array-prev-action'), 'stale primary action is excluded from review output');
        $t->true(!str_contains($encoded, 'staleArrayPrevHover'), 'stale JavaScript additional action is excluded from review output');
    },
];

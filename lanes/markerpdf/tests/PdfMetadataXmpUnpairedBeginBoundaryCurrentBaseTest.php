<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpUnpairedBeginRoot = static function (string $title, string $description, string $date): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Unpaired Begin Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-unpaired-begin</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Unpaired Begin Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Unpaired Begin Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T07:33:17Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$xmpUnpairedBeginBytes = static function (
    string $title,
    string $description,
    string $date
) use ($xmpUnpairedBeginRoot): string {
    $staleUnclosed = '<?xpacket begin="" id="stale-unpaired"?>'
        . $xmpUnpairedBeginRoot(
            'Stale Unpaired Begin XMP Title',
            'A stale unclosed packet must not define WordPress metadata',
            '2026-06-05T07:59:59Z'
        );
    $current = '<?xpacket begin="" id="current-complete"?>'
        . $xmpUnpairedBeginRoot($title, $description, $date)
        . '<?xpacket end="w"?>';
    $trailing = $xmpUnpairedBeginRoot(
        'Trailing Unpaired Begin Decoy Title',
        'Trailing packet bytes stay outside the current packet boundary',
        '2026-06-05T08:59:59Z'
    );

    return $staleUnclosed . "\n" . $current . "\0\0 " . $trailing;
};

$xmpUnpairedBeginPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP unpaired-begin boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Unpaired Begin Info Title) /Author (Info Fallback Author) /Producer (Info Fallback Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'skips stale unpaired xpacket begin before the current complete packet' => static function (
        TestRunner $t
    ) use ($xmpUnpairedBeginBytes, $xmpUnpairedBeginPdf): void {
        $metadataBytes = $xmpUnpairedBeginBytes(
            'Current Unpaired Begin XMP Title',
            'Current complete packet wins after a stale unpaired begin',
            '2026-06-05T03:32:17-04:00'
        );
        $pdf = $xmpUnpairedBeginPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Unpaired Begin Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Unpaired Begin XMP Title', $metadata['title']);
        $t->same('Current complete packet wins after a stale unpaired begin', $metadata['description']);
        $t->same(['Unpaired Begin Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-unpaired-begin'], $metadata['keywords']);
        $t->same('Unpaired Begin Tool', $metadata['creator_tool']);
        $t->same('Unpaired Begin Producer', $metadata['producer']);
        $t->same('2026-06-05T03:32:17-04:00', $metadata['created_at']);
        $t->same('2026-06-05T07:32:17Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T07:33:17Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Unpaired Begin Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Unpaired Begin Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Unpaired Begin XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Unpaired Begin Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Unpaired Begin XMP Title'));
        $t->true(!str_contains($plainText, 'Stale Unpaired Begin XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Unpaired Begin Decoy Title'));
    },
    'summarizes rejected XML streams from the current complete packet after stale begins' => static function (
        TestRunner $t
    ) use ($xmpUnpairedBeginBytes, $xmpUnpairedBeginPdf): void {
        $metadataBytes = $xmpUnpairedBeginBytes(
            'Rejected Current Unpaired Begin XMP Title',
            'Rejected current complete packet is summarized only',
            '2026-06-05T07:34:17Z'
        );
        $pdf = $xmpUnpairedBeginPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Unpaired Begin Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Unpaired Begin Info Title', $metadata['title']);
        $t->same('Rejected XMP Unpaired Begin Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-05T07:34:17Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T07:33:17Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Current Unpaired Begin XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Unpaired Begin XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Unpaired Begin Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Current Unpaired Begin XMP Title'));
        $t->true(!str_contains($plainText, 'Stale Unpaired Begin XMP Title'));
    },
];

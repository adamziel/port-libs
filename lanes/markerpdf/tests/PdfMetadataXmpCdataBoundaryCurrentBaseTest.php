<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpCdataBoundaryPacket = static function (
    string $title,
    string $description,
    string $date,
    bool $wrapInXmpMeta = true
): string {
    $rdf = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>CDATA Boundary Editor</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default"><![CDATA[' . $description . ']]></rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>cdata-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>CDATA Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>CDATA Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T00:42:15Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '<!-- literal </rdf:RDF> and </x:xmpmeta> markers inside comments are metadata text boundaries -->'
        . '</rdf:RDF>';

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . ($wrapInXmpMeta ? '<x:xmpmeta xmlns:x="adobe:ns:meta/">' . $rdf . '</x:xmpmeta>' : $rdf)
        . '<?xpacket end="w"?>';
};

$xmpCdataBoundaryPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP CDATA boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (CDATA Boundary Info Title) /Author (Info Fallback Author) /Producer (Info Fallback Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'bounds accepted XMP root past CDATA and comment closing-looking markers' => static function (
        TestRunner $t
    ) use ($xmpCdataBoundaryPacket, $xmpCdataBoundaryPdf): void {
        $description = 'Current CDATA keeps literal </x:xmpmeta> and </rdf:RDF> text without ending the packet root.';
        $currentXmp = $xmpCdataBoundaryPacket(
            'Current CDATA XMP Title',
            $description,
            '2026-06-04T20:42:15-04:00',
            true
        );
        $decoyXmp = $xmpCdataBoundaryPacket(
            'Trailing CDATA Decoy Title',
            'Trailing decoy packet must not replace current CDATA root.',
            '2026-06-05T00:59:59Z',
            true
        );
        $pdf = $xmpCdataBoundaryPdf(
            $currentXmp . "\0\0 \n" . $decoyXmp,
            '/Type /Metadata /Subtype /XML',
            'CDATA XMP Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current CDATA XMP Title', $metadata['title']);
        $t->same($description, $metadata['description']);
        $t->same(['CDATA Boundary Editor'], $metadata['authors']);
        $t->same(['wordpress', 'cdata-boundary'], $metadata['keywords']);
        $t->same('CDATA Boundary Tool', $metadata['creator_tool']);
        $t->same('CDATA Boundary Producer', $metadata['producer']);
        $t->same('2026-06-04T20:42:15-04:00', $metadata['created_at']);
        $t->same('2026-06-05T00:42:15Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T00:42:15Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('CDATA Boundary Info Title', $metadata['info']['Title'] ?? null);
        $t->same('CDATA XMP Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing CDATA Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing decoy packet must not replace'));
        $t->true(!str_contains($plainText, 'Current CDATA XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing CDATA Decoy Title'));
        $t->true(!str_contains($plainText, '</x:xmpmeta>'));
    },
    'summarizes rejected RDF-only XMP stream past CDATA false closing markers' => static function (
        TestRunner $t
    ) use ($xmpCdataBoundaryPacket, $xmpCdataBoundaryPdf): void {
        $currentXmp = $xmpCdataBoundaryPacket(
            'Rejected RDF CDATA XMP Title',
            'Rejected CDATA mentions </rdf:RDF> without ending the real RDF root.',
            '2026-06-05T00:43:15Z',
            false
        );
        $decoyXmp = $xmpCdataBoundaryPacket(
            'Rejected RDF Decoy Title',
            'Rejected decoy packet stays outside review summary.',
            '2026-06-05T00:59:59Z',
            false
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpCdataBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected RDF CDATA Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('CDATA Boundary Info Title', $metadata['title']);
        $t->same('Rejected RDF CDATA Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-05T00:43:15Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected RDF CDATA XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected RDF Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected RDF CDATA XMP Title'));
        $t->true(!str_contains($plainText, '</rdf:RDF>'));
    },
];

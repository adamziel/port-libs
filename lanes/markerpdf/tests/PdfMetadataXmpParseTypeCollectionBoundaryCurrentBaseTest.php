<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpParseTypeCollectionPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator rdf:parseType="Collection">'
        . '<rdf:Description><rdf:value>ParseType Collection Author One</rdf:value><xmp:role>internal author qualifier one</xmp:role></rdf:Description>'
        . '<rdf:Description><rdf:value>ParseType Collection Author Two</rdf:value><xmp:role>internal author qualifier two</xmp:role></rdf:Description>'
        . '</dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject rdf:parseType="Collection">'
        . '<rdf:Description><rdf:value>wordpress</rdf:value><xmp:tag>internal keyword qualifier one</xmp:tag></rdf:Description>'
        . '<rdf:Description><rdf:value>xmp-parse-type-collection</rdf:value><xmp:tag>internal keyword qualifier two</xmp:tag></rdf:Description>'
        . '</dc:subject>'
        . '<pdf:Producer>ParseType Collection Producer</pdf:Producer>'
        . '<xmp:CreatorTool>ParseType Collection Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T18:30:31Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about="#parseTypeCollectionDecoy">'
        . '<dc:creator rdf:parseType="Collection"><rdf:Description><rdf:value>External Collection Decoy</rdf:value></rdf:Description></dc:creator>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpParseTypeCollectionPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP parseType Collection fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (ParseType Collection Info Title) /Author (Info ParseType Author) /Keywords (info, fallback) /Producer (Info ParseType Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'promotes RDF parseType Collection XMP list nodes as ordered metadata values' => static function (
        TestRunner $t
    ) use ($xmpParseTypeCollectionPacket, $xmpParseTypeCollectionPdf): void {
        $currentXmp = $xmpParseTypeCollectionPacket(
            'Current ParseType Collection XMP Title',
            'RDF parseType Collection nodes remain ordered WordPress metadata values.',
            '2026-06-06T14:30:31-04:00'
        );
        $decoyXmp = $xmpParseTypeCollectionPacket(
            'Trailing ParseType Collection Decoy Title',
            'Trailing parseType Collection packet stays outside the current boundary.',
            '2026-06-06T18:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpParseTypeCollectionPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP ParseType Collection Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current ParseType Collection XMP Title', $metadata['title']);
        $t->same('RDF parseType Collection nodes remain ordered WordPress metadata values.', $metadata['description']);
        $t->same(['ParseType Collection Author One', 'ParseType Collection Author Two'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-parse-type-collection'], $metadata['keywords']);
        $t->same('ParseType Collection Tool', $metadata['creator_tool']);
        $t->same('ParseType Collection Producer', $metadata['producer']);
        $t->same('2026-06-06T14:30:31-04:00', $metadata['created_at']);
        $t->same('2026-06-06T18:30:31Z', $metadata['created_at_utc']);
        $t->same('2026-06-06T18:30:31Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('ParseType Collection Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP ParseType Collection Boundary Body', $plainText);
        $t->true(!in_array('Info ParseType Author', $metadata['authors'], true));
        $t->true(is_string($encoded) && !str_contains($encoded, 'internal author qualifier'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'internal keyword qualifier'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'External Collection Decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing ParseType Collection Decoy Title'));
        $t->true(!str_contains($plainText, 'Current ParseType Collection XMP Title'));
        $t->true(!str_contains($plainText, 'ParseType Collection Author One'));
        $t->true(!str_contains($plainText, 'Trailing ParseType Collection Decoy Title'));
    },
    'summarizes rejected XMP streams with parseType Collection counts only' => static function (
        TestRunner $t
    ) use ($xmpParseTypeCollectionPacket, $xmpParseTypeCollectionPdf): void {
        $currentXmp = $xmpParseTypeCollectionPacket(
            'Rejected ParseType Collection XMP Title',
            'Rejected parseType Collection XMP streams are summarized only.',
            '2026-06-06T18:31:31Z'
        );
        $decoyXmp = $xmpParseTypeCollectionPacket(
            'Rejected ParseType Collection Decoy Title',
            'Rejected trailing parseType Collection packet stays hidden.',
            '2026-06-06T18:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpParseTypeCollectionPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP ParseType Collection Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('ParseType Collection Info Title', $metadata['title']);
        $t->same('Rejected XMP ParseType Collection Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(2, $summary['author_count'] ?? null);
        $t->same(2, $summary['keyword_count'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-06T18:31:31Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-06T18:30:31Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected ParseType Collection XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'ParseType Collection Author One'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'internal keyword qualifier'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected ParseType Collection Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected ParseType Collection XMP Title'));
        $t->true(!str_contains($plainText, 'ParseType Collection Author One'));
        $t->true(!str_contains($plainText, 'Rejected ParseType Collection Decoy Title'));
    },
];

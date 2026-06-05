<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpTypedNodePacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<xmp:Document rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
        . ' xmp:CreatorTool="Typed Node Tool">'
        . '<xmp:PrivateReview><rdf:RDF><rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Nested RDF Decoy XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '<pdf:Producer>Nested RDF Decoy Producer</pdf:Producer>'
        . '</rdf:Description></rdf:RDF></xmp:PrivateReview>'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Typed Node Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-typed-node</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Typed Node Producer</pdf:Producer>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T04:32:33Z</xmp:MetadataDate>'
        . '</xmp:Document>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpTypedNodePdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP typed-node boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Typed Node Info Fallback Title) /Author (Typed Node Info Author) /Producer (Typed Node Info Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'extracts XMP metadata from top-level RDF typed nodes without nested RDF decoys' => static function (
        TestRunner $t
    ) use ($xmpTypedNodePacket, $xmpTypedNodePdf): void {
        $currentXmp = $xmpTypedNodePacket(
            'Current Typed Node XMP Title',
            'Typed RDF node values are document metadata',
            '2026-06-05T00:32:33-04:00'
        );
        $trailingDecoy = $xmpTypedNodePacket(
            'Trailing Typed Node Decoy Title',
            'Trailing typed-node packet must stay hidden',
            '2026-06-05T04:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $trailingDecoy;
        $pdf = $xmpTypedNodePdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Typed Node Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Typed Node XMP Title', $metadata['title']);
        $t->same('Typed RDF node values are document metadata', $metadata['description']);
        $t->same(['Typed Node Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-typed-node'], $metadata['keywords']);
        $t->same('Typed Node Tool', $metadata['creator_tool']);
        $t->same('Typed Node Producer', $metadata['producer']);
        $t->same('2026-06-05T00:32:33-04:00', $metadata['created_at']);
        $t->same('2026-06-05T04:32:33Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T04:32:33Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Typed Node Info Fallback Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Typed Node Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Nested RDF Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Nested RDF Decoy Producer'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Typed Node Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Typed Node XMP Title'));
        $t->true(!str_contains($plainText, 'Nested RDF Decoy XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Typed Node Decoy Title'));
    },
    'summarizes rejected typed-node XMP streams without promoting nested RDF text' => static function (
        TestRunner $t
    ) use ($xmpTypedNodePacket, $xmpTypedNodePdf): void {
        $currentXmp = $xmpTypedNodePacket(
            'Rejected Typed Node XMP Title',
            'Rejected typed-node XMP is summarized only',
            '2026-06-05T04:33:33Z'
        );
        $trailingDecoy = $xmpTypedNodePacket(
            'Rejected Trailing Typed Node Decoy Title',
            'Rejected trailing typed-node packet stays hidden',
            '2026-06-05T04:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $trailingDecoy;
        $pdf = $xmpTypedNodePdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Typed Node Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Typed Node Info Fallback Title', $metadata['title']);
        $t->same('Rejected XMP Typed Node Boundary Body', $plainText);
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
        $t->same('2026-06-05T04:33:33Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T04:32:33Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Typed Node XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Nested RDF Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Trailing Typed Node Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Typed Node XMP Title'));
        $t->true(!str_contains($plainText, 'Nested RDF Decoy XMP Title'));
    },
];

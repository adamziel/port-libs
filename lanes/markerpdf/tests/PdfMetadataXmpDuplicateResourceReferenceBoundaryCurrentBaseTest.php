<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpDuplicateResourceReferencePacket = static function (string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title rdf:resource="#duplicateTitle"/>'
        . '<dc:creator rdf:nodeID="duplicateCreator"/>'
        . '<dc:description rdf:resource="#duplicateDescription"/>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-duplicate-resource-reference</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Duplicate Resource Reference Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Duplicate Resource Reference Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T14:08:11Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about="#duplicateTitle"><rdf:Alt><rdf:li xml:lang="x-default">Stale Duplicate Target XMP Title</rdf:li></rdf:Alt></rdf:Description>'
        . '<rdf:Description rdf:ID="duplicateTitle"><rdf:Alt><rdf:li xml:lang="x-default">Current Duplicate Target XMP Title</rdf:li></rdf:Alt></rdf:Description>'
        . '<rdf:Description rdf:nodeID="duplicateCreator"><rdf:Seq><rdf:li>Stale Duplicate Target Author</rdf:li></rdf:Seq></rdf:Description>'
        . '<rdf:Description rdf:nodeID="duplicateCreator"><rdf:Seq><rdf:li>Current Duplicate Target Author</rdf:li></rdf:Seq></rdf:Description>'
        . '<rdf:Description rdf:about="#duplicateDescription"><rdf:Alt><rdf:li xml:lang="x-default">Stale duplicate target description</rdf:li></rdf:Alt></rdf:Description>'
        . '<rdf:Description xml:id="duplicateDescription"><rdf:Alt><rdf:li xml:lang="x-default">Current duplicate target description</rdf:li></rdf:Alt></rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpDuplicateResourceReferencePdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP duplicate resource-reference fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Duplicate Reference Info Title) /Subject (Duplicate Reference Info Subject) /Author (Duplicate Reference Info Author) /Producer (Duplicate Reference Info Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'keeps duplicate XMP rdf resource-reference targets ambiguous before WordPress metadata import' => static function (
        TestRunner $t
    ) use ($xmpDuplicateResourceReferencePacket, $xmpDuplicateResourceReferencePdf): void {
        $currentXmp = $xmpDuplicateResourceReferencePacket('2026-06-08T10:08:11-04:00');
        $decoyXmp = str_replace(
            ['Duplicate Resource Reference Producer', 'wordpress'],
            ['Trailing Duplicate Resource Producer Decoy', 'trailing-decoy'],
            $xmpDuplicateResourceReferencePacket('2026-06-08T15:15:15Z')
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpDuplicateResourceReferencePdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Duplicate Resource Reference Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $boundary = $metadata['xmp_resource_reference_boundary'] ?? [];

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Duplicate Reference Info Title', $metadata['title']);
        $t->same('Duplicate Reference Info Subject', $metadata['description']);
        $t->same(['Duplicate Reference Info Author'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-duplicate-resource-reference'], $metadata['keywords']);
        $t->same('Duplicate Resource Reference Tool', $metadata['creator_tool']);
        $t->same('Duplicate Resource Reference Producer', $metadata['producer']);
        $t->same('2026-06-08T10:08:11-04:00', $metadata['created_at']);
        $t->same('2026-06-08T14:08:11Z', $metadata['created_at_utc']);
        $t->same('2026-06-08T14:08:11Z', $metadata['metadata_date_utc']);
        $t->same('xmp_resource_reference_boundary', $boundary['source'] ?? null);
        $t->same(3, $boundary['ambiguous_reference_count'] ?? null);
        $t->same(['duplicateTitle', 'duplicateDescription'], $boundary['ambiguous_resource_ids'] ?? null);
        $t->same(['duplicateCreator'], $boundary['ambiguous_node_ids'] ?? null);
        $t->same(false, $boundary['payload_included'] ?? null);
        $t->same('XMP Duplicate Resource Reference Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Duplicate Target XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Current Duplicate Target XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Duplicate Target Author'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Current Duplicate Target Author'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Duplicate Resource Producer Decoy'));
        $t->true(!str_contains($plainText, 'Stale Duplicate Target XMP Title'));
        $t->true(!str_contains($plainText, 'Current Duplicate Target Author'));
        $t->true(!str_contains($plainText, 'Trailing Duplicate Resource Producer Decoy'));
    },
    'summarizes rejected XMP streams with duplicate resource references without target text' => static function (
        TestRunner $t
    ) use ($xmpDuplicateResourceReferencePacket, $xmpDuplicateResourceReferencePdf): void {
        $metadataBytes = $xmpDuplicateResourceReferencePacket('2026-06-08T14:09:11Z');
        $pdf = $xmpDuplicateResourceReferencePdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Duplicate Resource Reference Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];
        $boundary = $summary['resource_reference_boundary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Duplicate Reference Info Title', $metadata['title']);
        $t->same('Rejected XMP Duplicate Resource Reference Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['creator_tool', 'producer', 'created_at', 'metadata_date', 'keywords', 'resource_reference_boundary'], $summary['field_names'] ?? null);
        $t->same(0, $summary['author_count'] ?? null);
        $t->same(2, $summary['keyword_count'] ?? null);
        $t->same(3, $boundary['ambiguous_reference_count'] ?? null);
        $t->same(['duplicateTitle', 'duplicateDescription'], $boundary['ambiguous_resource_ids'] ?? null);
        $t->same(['duplicateCreator'], $boundary['ambiguous_node_ids'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-08T14:09:11Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-08T14:08:11Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Duplicate Target XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Current Duplicate Target XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Current Duplicate Target Author'));
        $t->true(!str_contains($plainText, 'Stale Duplicate Target XMP Title'));
        $t->true(!str_contains($plainText, 'Current Duplicate Target Author'));
    },
];

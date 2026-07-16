<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpNestedMediaManagementPacket = static function (
    string $title,
    string $description,
    string $date,
    array $ids = []
): string {
    $documentId = $ids['document_id'] ?? 'xmp.did:NESTED-CURRENT-DOCUMENT';
    $instanceId = $ids['instance_id'] ?? 'xmp.iid:NESTED-CURRENT-INSTANCE';
    $originalDocumentId = $ids['original_document_id'] ?? 'xmp.did:NESTED-ORIGINAL-DOCUMENT';
    $sourceDocumentId = $ids['source_document_id'] ?? 'xmp.did:NESTED-SOURCE-DOCUMENT';
    $sourceInstanceId = $ids['source_instance_id'] ?? 'xmp.iid:NESTED-SOURCE-INSTANCE';
    $sourceOriginalDocumentId = $ids['source_original_document_id'] ?? 'xmp.did:NESTED-SOURCE-ORIGINAL';

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="#nested-private-decoy"'
        . ' xmlns:xmpMM="http://ns.adobe.com/xap/1.0/mm/"'
        . ' xmpMM:DocumentID="xmp.did:NESTED-PRIVATE-DECOY"/>'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
        . ' xmlns:xmpMM="http://ns.adobe.com/xap/1.0/mm/"'
        . ' xmlns:stRef="http://ns.adobe.com/xap/1.0/sType/ResourceRef#"'
        . ' xmpMM:DocumentID="' . htmlspecialchars($documentId, ENT_XML1) . '"'
        . ' xmpMM:InstanceID="' . htmlspecialchars($instanceId, ENT_XML1) . '"'
        . ' xmpMM:OriginalDocumentID="' . htmlspecialchars($originalDocumentId, ENT_XML1) . '">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Nested Media Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-nested-media-management</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Nested Media Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Nested Media Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T18:03:27Z</xmp:MetadataDate>'
        . '<xmpMM:DerivedFrom>'
        . '<rdf:Description'
        . ' stRef:documentID="' . htmlspecialchars($sourceDocumentId, ENT_XML1) . '"'
        . ' stRef:instanceID="' . htmlspecialchars($sourceInstanceId, ENT_XML1) . '"'
        . ' stRef:originalDocumentID="' . htmlspecialchars($sourceOriginalDocumentId, ENT_XML1) . '">'
        . '<xmp:PrivateLabel>nested derived-from qualifier noise</xmp:PrivateLabel>'
        . '</rdf:Description>'
        . '</xmpMM:DerivedFrom>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpNestedMediaManagementPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP nested media-management boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Nested Media Info Title) /Author (Info Nested Media Author) /Producer (Info Nested Media Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'extracts nested RDF Description XMP media-management source identifiers without qualifier leakage' => static function (
        TestRunner $t
    ) use ($xmpNestedMediaManagementPacket, $xmpNestedMediaManagementPdf): void {
        $currentPacket = $xmpNestedMediaManagementPacket(
            'Current Nested XMP Media Management Title',
            'Nested RDF Description media-management provenance stays review-only',
            '2026-06-08T14:03:27-04:00'
        );
        $trailingPacket = $xmpNestedMediaManagementPacket(
            'Trailing Nested XMP Media Management Decoy Title',
            'Trailing nested identity packet must not replace current provenance',
            '2026-06-08T18:44:00Z',
            [
                'document_id' => 'xmp.did:NESTED-TRAILING-DOCUMENT',
                'instance_id' => 'xmp.iid:NESTED-TRAILING-INSTANCE',
                'original_document_id' => 'xmp.did:NESTED-TRAILING-ORIGINAL',
                'source_document_id' => 'xmp.did:NESTED-TRAILING-SOURCE-DOCUMENT',
                'source_instance_id' => 'xmp.iid:NESTED-TRAILING-SOURCE-INSTANCE',
                'source_original_document_id' => 'xmp.did:NESTED-TRAILING-SOURCE-ORIGINAL',
            ]
        );
        $metadataBytes = $currentPacket . "\0\0\n" . $trailingPacket;
        $pdf = $xmpNestedMediaManagementPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Nested Media Management Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $media = $metadata['xmp']['media_management'] ?? [];
        $derived = $media['derived_from'] ?? [];

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Nested XMP Media Management Title', $metadata['title']);
        $t->same('Nested RDF Description media-management provenance stays review-only', $metadata['description']);
        $t->same('XMP Nested Media Management Boundary Body', $plainText);
        $t->same('xmp_media_management', $media['source'] ?? null);
        $t->same(true, $media['review_only'] ?? null);
        $t->same(false, $media['payload_included'] ?? null);
        $t->same('xmp.did:NESTED-CURRENT-DOCUMENT', $media['document_id'] ?? null);
        $t->same('xmp.iid:NESTED-CURRENT-INSTANCE', $media['instance_id'] ?? null);
        $t->same('xmp.did:NESTED-ORIGINAL-DOCUMENT', $media['original_document_id'] ?? null);
        $t->same('xmpmm_derived_from', $derived['source'] ?? null);
        $t->same(true, $derived['review_only'] ?? null);
        $t->same(false, $derived['payload_included'] ?? null);
        $t->same('xmp.did:NESTED-SOURCE-DOCUMENT', $derived['document_id'] ?? null);
        $t->same('xmp.iid:NESTED-SOURCE-INSTANCE', $derived['instance_id'] ?? null);
        $t->same('xmp.did:NESTED-SOURCE-ORIGINAL', $derived['original_document_id'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same($media, $metadata['xmp_media_management'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'NESTED-PRIVATE-DECOY'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'NESTED-TRAILING-DOCUMENT'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'NESTED-TRAILING-SOURCE-DOCUMENT'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'nested derived-from qualifier noise'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Nested XMP Media Management Decoy Title'));
        $t->true(!str_contains($plainText, 'NESTED-CURRENT-DOCUMENT'));
        $t->true(!str_contains($plainText, 'NESTED-SOURCE-DOCUMENT'));
        $t->true(!str_contains($plainText, 'nested derived-from qualifier noise'));
    },
    'summarizes rejected nested XML media-management source references without exposing identifiers' => static function (
        TestRunner $t
    ) use ($xmpNestedMediaManagementPacket, $xmpNestedMediaManagementPdf): void {
        $currentPacket = $xmpNestedMediaManagementPacket(
            'Rejected Nested XMP Media Management Title',
            'Rejected nested media-management provenance remains redacted',
            '2026-06-08T18:04:27Z'
        );
        $trailingPacket = $xmpNestedMediaManagementPacket(
            'Rejected Trailing Nested XMP Media Management Decoy Title',
            'Rejected trailing nested identity packet stays hidden',
            '2026-06-08T18:44:00Z',
            [
                'document_id' => 'xmp.did:NESTED-REJECTED-TRAILING-DOCUMENT',
                'instance_id' => 'xmp.iid:NESTED-REJECTED-TRAILING-INSTANCE',
                'original_document_id' => 'xmp.did:NESTED-REJECTED-TRAILING-ORIGINAL',
                'source_document_id' => 'xmp.did:NESTED-REJECTED-TRAILING-SOURCE-DOCUMENT',
                'source_instance_id' => 'xmp.iid:NESTED-REJECTED-TRAILING-SOURCE-INSTANCE',
                'source_original_document_id' => 'xmp.did:NESTED-REJECTED-TRAILING-SOURCE-ORIGINAL',
            ]
        );
        $metadataBytes = $currentPacket . "\0\0\n" . $trailingPacket;
        $pdf = $xmpNestedMediaManagementPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Nested Media Management Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Nested Media Info Title', $metadata['title']);
        $t->same('Rejected XMP Nested Media Management Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords', 'media_management'], $summary['field_names'] ?? null);
        $t->same(['document_id', 'instance_id', 'original_document_id', 'derived_from'], $summary['media_management_field_names'] ?? null);
        $t->same(true, $summary['has_media_management_derived_from'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'NESTED-CURRENT-DOCUMENT'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'NESTED-SOURCE-DOCUMENT'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'NESTED-PRIVATE-DECOY'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'NESTED-REJECTED-TRAILING-DOCUMENT'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'nested derived-from qualifier noise'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Trailing Nested XMP Media Management Decoy Title'));
        $t->true(!str_contains($plainText, 'NESTED-CURRENT-DOCUMENT'));
        $t->true(!str_contains($plainText, 'NESTED-SOURCE-DOCUMENT'));
    },
];

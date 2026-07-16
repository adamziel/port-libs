<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpMediaManagementPacket = static function (
    string $title,
    string $description,
    string $date,
    array $ids = []
): string {
    $documentId = $ids['document_id'] ?? 'xmp.did:CURRENT-DOCUMENT-ID';
    $instanceId = $ids['instance_id'] ?? 'xmp.iid:CURRENT-INSTANCE-ID';
    $originalDocumentId = $ids['original_document_id'] ?? 'xmp.did:ORIGINAL-DOCUMENT-ID';
    $sourceDocumentId = $ids['source_document_id'] ?? 'xmp.did:SOURCE-DOCUMENT-ID';
    $sourceInstanceId = $ids['source_instance_id'] ?? 'xmp.iid:SOURCE-INSTANCE-ID';
    $sourceOriginalDocumentId = $ids['source_original_document_id'] ?? 'xmp.did:SOURCE-ORIGINAL-ID';

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="#private-decoy"'
        . ' xmlns:xmpMM="http://ns.adobe.com/xap/1.0/mm/"'
        . ' xmpMM:DocumentID="xmp.did:PRIVATE-DECOY-DOCUMENT"'
        . ' xmpMM:InstanceID="xmp.iid:PRIVATE-DECOY-INSTANCE"/>'
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
        . '<dc:creator><rdf:Seq><rdf:li>Media Management Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-media-management</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Media Management Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Media Management Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T05:58:43Z</xmp:MetadataDate>'
        . '<xmpMM:DerivedFrom rdf:resource="#source-doc"/>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:ID="source-doc"'
        . ' xmlns:stRef="http://ns.adobe.com/xap/1.0/sType/ResourceRef#"'
        . ' stRef:documentID="' . htmlspecialchars($sourceDocumentId, ENT_XML1) . '"'
        . ' stRef:instanceID="' . htmlspecialchars($sourceInstanceId, ENT_XML1) . '"'
        . ' stRef:originalDocumentID="' . htmlspecialchars($sourceOriginalDocumentId, ENT_XML1) . '"/>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpMediaManagementPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP media-management boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Media Management Info Title) /Author (Info Media Author) /Producer (Info Media Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'extracts document-level XMP media-management identifiers without private resource leakage' => static function (
        TestRunner $t
    ) use ($xmpMediaManagementPacket, $xmpMediaManagementPdf): void {
        $currentPacket = $xmpMediaManagementPacket(
            'Current XMP Media Management Title',
            'Document identity metadata is preserved for WordPress review',
            '2026-06-06T01:58:43-04:00'
        );
        $trailingPacket = $xmpMediaManagementPacket(
            'Trailing XMP Media Management Decoy Title',
            'A trailing identity packet must not replace the current document identifiers',
            '2026-06-06T06:28:43Z',
            [
                'document_id' => 'xmp.did:TRAILING-DOCUMENT-ID',
                'instance_id' => 'xmp.iid:TRAILING-INSTANCE-ID',
                'original_document_id' => 'xmp.did:TRAILING-ORIGINAL-ID',
                'source_document_id' => 'xmp.did:TRAILING-SOURCE-DOCUMENT-ID',
                'source_instance_id' => 'xmp.iid:TRAILING-SOURCE-INSTANCE-ID',
                'source_original_document_id' => 'xmp.did:TRAILING-SOURCE-ORIGINAL-ID',
            ]
        );
        $metadataBytes = $currentPacket . "\0\0\n" . $trailingPacket;
        $pdf = $xmpMediaManagementPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Media Management Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $media = $metadata['xmp']['media_management'] ?? [];
        $derived = $media['derived_from'] ?? [];

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current XMP Media Management Title', $metadata['title']);
        $t->same('Document identity metadata is preserved for WordPress review', $metadata['description']);
        $t->same('XMP Media Management Boundary Body', $plainText);
        $t->same('xmp_media_management', $media['source'] ?? null);
        $t->same(true, $media['review_only'] ?? null);
        $t->same(false, $media['payload_included'] ?? null);
        $t->same('xmp.did:CURRENT-DOCUMENT-ID', $media['document_id'] ?? null);
        $t->same('xmp.iid:CURRENT-INSTANCE-ID', $media['instance_id'] ?? null);
        $t->same('xmp.did:ORIGINAL-DOCUMENT-ID', $media['original_document_id'] ?? null);
        $t->same('xmpmm_derived_from', $derived['source'] ?? null);
        $t->same(true, $derived['review_only'] ?? null);
        $t->same(false, $derived['payload_included'] ?? null);
        $t->same('xmp.did:SOURCE-DOCUMENT-ID', $derived['document_id'] ?? null);
        $t->same('xmp.iid:SOURCE-INSTANCE-ID', $derived['instance_id'] ?? null);
        $t->same('xmp.did:SOURCE-ORIGINAL-ID', $derived['original_document_id'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same($media, $metadata['xmp_media_management'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'PRIVATE-DECOY-DOCUMENT'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'PRIVATE-DECOY-INSTANCE'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'TRAILING-DOCUMENT-ID'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing XMP Media Management Decoy Title'));
        $t->true(!str_contains($plainText, 'CURRENT-DOCUMENT-ID'));
        $t->true(!str_contains($plainText, 'SOURCE-DOCUMENT-ID'));
    },
    'summarizes rejected XML media-management streams without exposing document identifiers' => static function (
        TestRunner $t
    ) use ($xmpMediaManagementPacket, $xmpMediaManagementPdf): void {
        $currentPacket = $xmpMediaManagementPacket(
            'Rejected XMP Media Management Title',
            'Rejected document identity metadata remains review-only',
            '2026-06-06T05:59:43Z'
        );
        $trailingPacket = $xmpMediaManagementPacket(
            'Rejected Trailing XMP Media Management Decoy Title',
            'Rejected trailing identity packet stays hidden',
            '2026-06-06T06:29:43Z',
            [
                'document_id' => 'xmp.did:REJECTED-TRAILING-DOCUMENT-ID',
                'instance_id' => 'xmp.iid:REJECTED-TRAILING-INSTANCE-ID',
                'original_document_id' => 'xmp.did:REJECTED-TRAILING-ORIGINAL-ID',
                'source_document_id' => 'xmp.did:REJECTED-TRAILING-SOURCE-DOCUMENT-ID',
                'source_instance_id' => 'xmp.iid:REJECTED-TRAILING-SOURCE-INSTANCE-ID',
                'source_original_document_id' => 'xmp.did:REJECTED-TRAILING-SOURCE-ORIGINAL-ID',
            ]
        );
        $metadataBytes = $currentPacket . "\0\0\n" . $trailingPacket;
        $pdf = $xmpMediaManagementPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Media Management Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Media Management Info Title', $metadata['title']);
        $t->same('Rejected XMP Media Management Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords', 'media_management'], $summary['field_names'] ?? null);
        $t->same(['document_id', 'instance_id', 'original_document_id', 'derived_from'], $summary['media_management_field_names'] ?? null);
        $t->same(true, $summary['has_media_management_derived_from'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'CURRENT-DOCUMENT-ID'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'SOURCE-DOCUMENT-ID'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'PRIVATE-DECOY-DOCUMENT'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'REJECTED-TRAILING-DOCUMENT-ID'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Trailing XMP Media Management Decoy Title'));
        $t->true(!str_contains($plainText, 'CURRENT-DOCUMENT-ID'));
        $t->true(!str_contains($plainText, 'SOURCE-DOCUMENT-ID'));
    },
];

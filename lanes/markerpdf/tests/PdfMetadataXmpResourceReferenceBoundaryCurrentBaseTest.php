<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpResourceReferencePacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title rdf:resource="#titleAlt"/>'
        . '<dc:creator rdf:resource="#creatorSeq"/>'
        . '<dc:description rdf:resource="#descriptionAlt"/>'
        . '<dc:subject rdf:resource="#subjectBag"/>'
        . '<pdf:Producer rdf:resource="#producerValue"/>'
        . '<xmp:CreatorTool rdf:resource="#toolValue"/>'
        . '<xmp:CreateDate rdf:resource="#createDateValue"/>'
        . '<xmp:ModifyDate rdf:resource="#missingModifyDate"/>'
        . '<xmp:MetadataDate rdf:resource="#metadataDateValue"/>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about="#titleAlt"><rdf:Alt>'
        . '<rdf:li xml:lang="fr-FR">Titre reference ignore</rdf:li>'
        . '<rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li>'
        . '</rdf:Alt><xmp:PrivateLabel>title reference qualifier noise</xmp:PrivateLabel></rdf:Description>'
        . '<rdf:Description rdf:about="#creatorSeq"><rdf:Seq>'
        . '<rdf:li>Resource Ref Author One</rdf:li><rdf:li>Resource Ref Author Two</rdf:li>'
        . '</rdf:Seq><xmp:PrivateRole>author reference qualifier noise</xmp:PrivateRole></rdf:Description>'
        . '<rdf:Description rdf:about="#descriptionAlt"><rdf:Alt><rdf:li xml:lang="x-default">'
        . htmlspecialchars($description, ENT_XML1)
        . '</rdf:li></rdf:Alt><pdf:Producer>description reference qualifier noise</pdf:Producer></rdf:Description>'
        . '<rdf:Description rdf:about="#subjectBag"><rdf:Bag>'
        . '<rdf:li>wordpress</rdf:li><rdf:li>xmp-resource-reference</rdf:li>'
        . '</rdf:Bag><xmp:PrivateTag>keyword reference qualifier noise</xmp:PrivateTag></rdf:Description>'
        . '<rdf:Description rdf:about="#producerValue"><rdf:value>Resource Reference Producer</rdf:value><xmp:Private>producer reference qualifier noise</xmp:Private></rdf:Description>'
        . '<rdf:Description rdf:about="#toolValue" rdf:value="Resource Reference Tool"><xmp:Private>tool reference qualifier noise</xmp:Private></rdf:Description>'
        . '<rdf:Description rdf:about="#createDateValue"><rdf:value>' . htmlspecialchars($date, ENT_XML1) . '</rdf:value></rdf:Description>'
        . '<rdf:Description rdf:about="#metadataDateValue" rdf:value="2026-06-05T18:30:45Z"/>'
        . '<rdf:Description rdf:about="#cycleA" rdf:resource="#cycleB"/>'
        . '<rdf:Description rdf:about="#cycleB" rdf:resource="#cycleA"/>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpResourceReferencePdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP resource-reference boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Resource Reference Info Title) /Author (Info Resource Reference Author) /Producer (Info Resource Reference Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'resolves same-packet XMP rdf resource references for document metadata only' => static function (
        TestRunner $t
    ) use ($xmpResourceReferencePacket, $xmpResourceReferencePdf): void {
        $currentXmp = $xmpResourceReferencePacket(
            'Current Resource Reference XMP Title',
            'Fragment resource references stay document metadata before WordPress import',
            '2026-06-05T14:30:45-04:00'
        );
        $decoyXmp = $xmpResourceReferencePacket(
            'Trailing Resource Reference Decoy Title',
            'Trailing resource-reference packet must stay outside metadata',
            '2026-06-05T19:00:00Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpResourceReferencePdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Resource Reference Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Resource Reference XMP Title', $metadata['title']);
        $t->same('Fragment resource references stay document metadata before WordPress import', $metadata['description']);
        $t->same(['Resource Ref Author One', 'Resource Ref Author Two'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-resource-reference'], $metadata['keywords']);
        $t->same('Resource Reference Tool', $metadata['creator_tool']);
        $t->same('Resource Reference Producer', $metadata['producer']);
        $t->same('2026-06-05T14:30:45-04:00', $metadata['created_at']);
        $t->same('2026-06-05T18:30:45Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T18:30:45Z', $metadata['metadata_date_utc']);
        $t->true(!isset($metadata['modified_at']));
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same('Resource Reference Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Resource Reference Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'title reference qualifier noise'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'author reference qualifier noise'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'description reference qualifier noise'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'keyword reference qualifier noise'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Resource Reference Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Resource Reference XMP Title'));
        $t->true(!str_contains($plainText, 'Resource Ref Author One'));
        $t->true(!str_contains($plainText, 'Trailing Resource Reference Decoy Title'));
    },
    'summarizes rejected XMP rdf resource-reference streams without exposing target text' => static function (
        TestRunner $t
    ) use ($xmpResourceReferencePacket, $xmpResourceReferencePdf): void {
        $currentXmp = $xmpResourceReferencePacket(
            'Rejected Resource Reference XMP Title',
            'Rejected resource-reference packet is summarized only',
            '2026-06-05T18:31:45Z'
        );
        $decoyXmp = $xmpResourceReferencePacket(
            'Rejected Resource Reference Decoy Title',
            'Rejected trailing resource-reference packet stays hidden',
            '2026-06-05T19:00:00Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpResourceReferencePdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Resource Reference Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Resource Reference Info Title', $metadata['title']);
        $t->same('Rejected XMP Resource Reference Boundary Body', $plainText);
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
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-05T18:31:45Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T18:30:45Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Resource Reference XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Resource Ref Author One'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'author reference qualifier noise'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Resource Reference Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Resource Reference XMP Title'));
        $t->true(!str_contains($plainText, 'Resource Ref Author One'));
        $t->true(!str_contains($plainText, 'Rejected Resource Reference Decoy Title'));
    },
];

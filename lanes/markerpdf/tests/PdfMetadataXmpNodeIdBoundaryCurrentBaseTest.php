<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpNodeIdBoundaryPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title rdf:nodeID="nodeTitleAlt"/>'
        . '<dc:creator rdf:nodeID="nodeCreatorSeq"/>'
        . '<dc:description rdf:nodeID="nodeDescriptionAlt"/>'
        . '<dc:subject rdf:nodeID="nodeSubjectBag"/>'
        . '<pdf:Producer rdf:nodeID="nodeProducerValue"/>'
        . '<xmp:CreatorTool rdf:nodeID="nodeCreatorToolValue"/>'
        . '<xmp:CreateDate rdf:nodeID="nodeCreateDateValue"/>'
        . '<xmp:MetadataDate rdf:nodeID="nodeMetadataDateValue"/>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:nodeID="nodeDecoyProducer" xmlns:pdf="http://ns.adobe.com/pdf/1.3/">'
        . '<pdf:Producer>NodeID Decoy Producer</pdf:Producer>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:nodeID="nodeTitleAlt">'
        . '<rdf:Alt>'
        . '<rdf:li xml:lang="fr-FR">Titre nodeID ignore</rdf:li>'
        . '<rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li>'
        . '</rdf:Alt>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:nodeID="nodeCreatorSeq">'
        . '<rdf:Seq><rdf:li>NodeID Author One</rdf:li><rdf:li>NodeID Author Two</rdf:li></rdf:Seq>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:nodeID="nodeDescriptionAlt">'
        . '<rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:nodeID="nodeSubjectBag">'
        . '<rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-nodeid</rdf:li></rdf:Bag>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:nodeID="nodeProducerValue"><rdf:value>NodeID Boundary Producer</rdf:value></rdf:Description>'
        . '<rdf:Description rdf:nodeID="nodeCreatorToolValue"><rdf:value>NodeID Boundary Tool</rdf:value></rdf:Description>'
        . '<rdf:Description rdf:nodeID="nodeCreateDateValue"><rdf:value>' . htmlspecialchars($date, ENT_XML1) . '</rdf:value></rdf:Description>'
        . '<rdf:Description rdf:nodeID="nodeMetadataDateValue"><rdf:value>2026-06-05T20:22:48Z</rdf:value></rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpNodeIdBoundaryPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP nodeID boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (NodeID Info Title) /Author (NodeID Info Author) /Producer (NodeID Info Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'resolves local rdf nodeID blank-node references before WordPress XMP metadata import' => static function (
        TestRunner $t
    ) use ($xmpNodeIdBoundaryPacket, $xmpNodeIdBoundaryPdf): void {
        $currentXmp = $xmpNodeIdBoundaryPacket(
            'Current NodeID XMP Title',
            'RDF blank-node references remain document metadata before WordPress import.',
            '2026-06-05T16:22:48-04:00'
        );
        $decoyXmp = $xmpNodeIdBoundaryPacket(
            'Trailing NodeID Decoy Title',
            'Trailing nodeID packet stays outside the current metadata boundary.',
            '2026-06-05T20:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpNodeIdBoundaryPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP NodeID Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current NodeID XMP Title', $metadata['title']);
        $t->same('RDF blank-node references remain document metadata before WordPress import.', $metadata['description']);
        $t->same(['NodeID Author One', 'NodeID Author Two'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-nodeid'], $metadata['keywords']);
        $t->same('NodeID Boundary Tool', $metadata['creator_tool']);
        $t->same('NodeID Boundary Producer', $metadata['producer']);
        $t->same('2026-06-05T16:22:48-04:00', $metadata['created_at']);
        $t->same('2026-06-05T20:22:48Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T20:22:48Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('NodeID Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP NodeID Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'NodeID Decoy Producer'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Titre nodeID ignore'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing NodeID Decoy Title'));
        $t->true(!str_contains($plainText, 'Current NodeID XMP Title'));
        $t->true(!str_contains($plainText, 'NodeID Author One'));
        $t->true(!str_contains($plainText, 'Trailing NodeID Decoy Title'));
    },
    'summarizes rejected rdf nodeID XMP streams without exposing blank-node payload text' => static function (
        TestRunner $t
    ) use ($xmpNodeIdBoundaryPacket, $xmpNodeIdBoundaryPdf): void {
        $currentXmp = $xmpNodeIdBoundaryPacket(
            'Rejected NodeID XMP Title',
            'Rejected nodeID XMP is summarized only.',
            '2026-06-05T20:23:48Z'
        );
        $decoyXmp = $xmpNodeIdBoundaryPacket(
            'Rejected NodeID Decoy Title',
            'Rejected trailing nodeID packet stays hidden.',
            '2026-06-05T20:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpNodeIdBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP NodeID Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('NodeID Info Title', $metadata['title']);
        $t->same('Rejected XMP NodeID Boundary Body', $plainText);
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
        $t->same('2026-06-05T20:23:48Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T20:22:48Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected NodeID XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'NodeID Author One'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'NodeID Decoy Producer'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected NodeID Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected NodeID XMP Title'));
        $t->true(!str_contains($plainText, 'NodeID Author One'));
        $t->true(!str_contains($plainText, 'Rejected NodeID Decoy Title'));
    },
];

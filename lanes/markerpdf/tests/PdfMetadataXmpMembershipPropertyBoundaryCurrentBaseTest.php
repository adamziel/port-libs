<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpMembershipPropertyPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt>'
        . '<rdf:_1 xml:lang="fr-FR">Titre membership ignore</rdf:_1>'
        . '<rdf:_2 xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:_2>'
        . '</rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq>'
        . '<rdf:_2>Membership Author Two</rdf:_2>'
        . '<rdf:_1>Membership Author One</rdf:_1>'
        . '</rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt>'
        . '<rdf:_1 xml:lang="fr-FR">Membership localized description decoy</rdf:_1>'
        . '<rdf:_2 xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:_2>'
        . '</rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag>'
        . '<rdf:_2>xmp-membership-property</rdf:_2>'
        . '<rdf:_1>wordpress</rdf:_1>'
        . '</rdf:Bag></dc:subject>'
        . '<pdf:Producer>Membership Property Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Membership Property Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T00:12:02Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpMembershipPropertyPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP membership-property boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Membership Property Info Title) /Author (Info Membership Author) /Producer (Info Membership Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'extracts ordered XMP RDF membership properties before WordPress metadata import' => static function (
        TestRunner $t
    ) use ($xmpMembershipPropertyPacket, $xmpMembershipPropertyPdf): void {
        $currentXmp = $xmpMembershipPropertyPacket(
            'Current Membership Property XMP Title',
            'RDF membership properties remain separate metadata values',
            '2026-06-05T20:12:02-04:00'
        );
        $decoyXmp = $xmpMembershipPropertyPacket(
            'Trailing Membership Property Decoy Title',
            'Trailing membership-property packet stays outside the current metadata boundary',
            '2026-06-06T00:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpMembershipPropertyPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Membership Property Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Membership Property XMP Title', $metadata['title']);
        $t->same('RDF membership properties remain separate metadata values', $metadata['description']);
        $t->same(['Membership Author One', 'Membership Author Two'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-membership-property'], $metadata['keywords']);
        $t->same('Membership Property Tool', $metadata['creator_tool']);
        $t->same('Membership Property Producer', $metadata['producer']);
        $t->same('2026-06-05T20:12:02-04:00', $metadata['created_at']);
        $t->same('2026-06-06T00:12:02Z', $metadata['created_at_utc']);
        $t->same('2026-06-06T00:12:02Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Membership Property Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Membership Property Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Membership Author OneMembership Author Two'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'wordpressxmp-membership-property'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Membership Property Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Membership Property XMP Title'));
        $t->true(!str_contains($plainText, 'Membership Author One'));
        $t->true(!str_contains($plainText, 'Trailing Membership Property Decoy Title'));
    },
    'summarizes rejected RDF membership-property XMP streams without concatenating values' => static function (
        TestRunner $t
    ) use ($xmpMembershipPropertyPacket, $xmpMembershipPropertyPdf): void {
        $currentXmp = $xmpMembershipPropertyPacket(
            'Rejected Membership Property XMP Title',
            'Rejected membership-property packet is summarized only',
            '2026-06-06T00:13:02Z'
        );
        $decoyXmp = $xmpMembershipPropertyPacket(
            'Rejected Membership Property Decoy Title',
            'Rejected trailing membership-property packet stays hidden',
            '2026-06-06T00:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpMembershipPropertyPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Membership Property Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Membership Property Info Title', $metadata['title']);
        $t->same('Rejected XMP Membership Property Boundary Body', $plainText);
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
        $t->same('2026-06-06T00:13:02Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-06T00:12:02Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Membership Property XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Membership Author OneMembership Author Two'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Membership Property Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Membership Property XMP Title'));
        $t->true(!str_contains($plainText, 'Membership Author One'));
        $t->true(!str_contains($plainText, 'Rejected Membership Property Decoy Title'));
    },
];

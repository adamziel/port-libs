<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpLangAltBoundaryPacket = static function (
    string $title,
    string $localizedTitle,
    string $description,
    string $localizedDescription,
    string $date
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt>'
        . '<rdf:li xml:lang="fr-FR">' . htmlspecialchars($localizedTitle, ENT_XML1) . '</rdf:li>'
        . '<rdf:li xml:lang="X-DEFAULT">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li>'
        . '</rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Lang Alt Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt>'
        . '<rdf:li xml:lang="fr-FR">' . htmlspecialchars($localizedDescription, ENT_XML1) . '</rdf:li>'
        . '<rdf:li xml:lang="X-DEFAULT">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li>'
        . '</rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-lang-alt-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Lang Alt Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Lang Alt Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T03:59:13Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpLangAltBoundaryPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP language alternative boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Lang Alt Info Title) /Author (Info Fallback Author) /Producer (Info Fallback Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'prefers uppercase XMP x-default language alternatives before WordPress metadata import' => static function (
        TestRunner $t
    ) use ($xmpLangAltBoundaryPacket, $xmpLangAltBoundaryPdf): void {
        $localizedTitle = 'Localized Lang Alt Decoy Title';
        $localizedDescription = 'Localized lang alt description must not become the document summary';
        $currentXmp = $xmpLangAltBoundaryPacket(
            'Current Lang Alt XMP Title',
            $localizedTitle,
            'Default language XMP description wins case-insensitively',
            $localizedDescription,
            '2026-06-04T23:59:13-04:00'
        );
        $pdf = $xmpLangAltBoundaryPdf(
            $currentXmp,
            '/Type /Metadata /Subtype /XML',
            'XMP Lang Alt Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Lang Alt XMP Title', $metadata['title']);
        $t->same('Default language XMP description wins case-insensitively', $metadata['description']);
        $t->same('Current Lang Alt XMP Title', $metadata['xmp']['title'] ?? null);
        $t->same('Default language XMP description wins case-insensitively', $metadata['xmp']['description'] ?? null);
        $t->same(['Lang Alt Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-lang-alt-boundary'], $metadata['keywords']);
        $t->same('Lang Alt Boundary Tool', $metadata['creator_tool']);
        $t->same('Lang Alt Boundary Producer', $metadata['producer']);
        $t->same('2026-06-04T23:59:13-04:00', $metadata['created_at']);
        $t->same('2026-06-05T03:59:13Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T03:59:13Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same('Lang Alt Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Lang Alt Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, $localizedTitle));
        $t->true(is_string($encoded) && !str_contains($encoded, $localizedDescription));
        $t->true(!str_contains($plainText, 'Current Lang Alt XMP Title'));
        $t->true(!str_contains($plainText, $localizedTitle));
    },
    'summarizes rejected uppercase x-default XMP streams without leaking alternatives' => static function (
        TestRunner $t
    ) use ($xmpLangAltBoundaryPacket, $xmpLangAltBoundaryPdf): void {
        $metadataBytes = $xmpLangAltBoundaryPacket(
            'Rejected Lang Alt XMP Title',
            'Rejected Localized Lang Alt Title',
            'Rejected default language description',
            'Rejected localized description',
            '2026-06-05T03:58:13Z'
        );
        $pdf = $xmpLangAltBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Lang Alt Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Lang Alt Info Title', $metadata['title']);
        $t->same('Rejected XMP Lang Alt Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-05T03:58:13Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Lang Alt XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Localized Lang Alt Title'));
        $t->true(!str_contains($plainText, 'Rejected Lang Alt XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Localized Lang Alt Title'));
    },
];

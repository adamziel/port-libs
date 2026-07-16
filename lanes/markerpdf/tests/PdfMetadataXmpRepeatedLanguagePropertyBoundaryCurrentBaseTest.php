<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpRepeatedLanguagePropertyPacket = static function (
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
        . '<dc:title xml:lang="fr-FR">' . htmlspecialchars($localizedTitle, ENT_XML1) . '</dc:title>'
        . '<dc:title xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Repeated Language Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description xml:lang="fr-FR">' . htmlspecialchars($localizedDescription, ENT_XML1) . '</dc:description>'
        . '<dc:description xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-repeated-language-property</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Repeated Language Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Repeated Language Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T21:57:35Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpRepeatedLanguagePropertyPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP repeated-language property fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Repeated Language Info Title) /Author (Info Repeated Language Author) /Producer (Info Repeated Language Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'prefers x-default repeated XMP language properties before WordPress metadata import' => static function (
        TestRunner $t
    ) use ($xmpRepeatedLanguagePropertyPacket, $xmpRepeatedLanguagePropertyPdf): void {
        $localizedTitle = 'Localized Repeated Language Decoy Title';
        $localizedDescription = 'Localized repeated language description must not become the summary';
        $currentXmp = $xmpRepeatedLanguagePropertyPacket(
            'Current Repeated Language XMP Title',
            $localizedTitle,
            'Repeated simple XMP properties use the x-default sibling',
            $localizedDescription,
            '2026-06-08T17:57:35-04:00'
        );
        $decoyXmp = $xmpRepeatedLanguagePropertyPacket(
            'Trailing Repeated Language Decoy Title',
            'Trailing localized decoy title',
            'Trailing repeated language packet stays outside metadata',
            'Trailing localized decoy description',
            '2026-06-08T22:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpRepeatedLanguagePropertyPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Repeated Language Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Repeated Language XMP Title', $metadata['title']);
        $t->same('Repeated simple XMP properties use the x-default sibling', $metadata['description']);
        $t->same('Current Repeated Language XMP Title', $metadata['xmp']['title'] ?? null);
        $t->same('Repeated simple XMP properties use the x-default sibling', $metadata['xmp']['description'] ?? null);
        $t->same(['Repeated Language Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-repeated-language-property'], $metadata['keywords']);
        $t->same('Repeated Language Tool', $metadata['creator_tool']);
        $t->same('Repeated Language Producer', $metadata['producer']);
        $t->same('2026-06-08T17:57:35-04:00', $metadata['created_at']);
        $t->same('2026-06-08T21:57:35Z', $metadata['created_at_utc']);
        $t->same('2026-06-08T21:57:35Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Repeated Language Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Repeated Language Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, $localizedTitle));
        $t->true(is_string($encoded) && !str_contains($encoded, $localizedDescription));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Repeated Language Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Repeated Language XMP Title'));
        $t->true(!str_contains($plainText, $localizedTitle));
        $t->true(!str_contains($plainText, 'Trailing Repeated Language Decoy Title'));
    },
    'summarizes rejected repeated-language XMP streams without leaking localized siblings' => static function (
        TestRunner $t
    ) use ($xmpRepeatedLanguagePropertyPacket, $xmpRepeatedLanguagePropertyPdf): void {
        $metadataBytes = $xmpRepeatedLanguagePropertyPacket(
            'Rejected Repeated Language XMP Title',
            'Rejected Localized Repeated Language Title',
            'Rejected repeated x-default description',
            'Rejected localized repeated description',
            '2026-06-08T21:58:35Z'
        );
        $pdf = $xmpRepeatedLanguagePropertyPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Repeated Language Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Repeated Language Info Title', $metadata['title']);
        $t->same('Rejected XMP Repeated Language Boundary Body', $plainText);
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
        $t->same('2026-06-08T21:58:35Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-08T21:57:35Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Repeated Language XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Localized Repeated Language Title'));
        $t->true(!str_contains($plainText, 'Rejected Repeated Language XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Localized Repeated Language Title'));
    },
];

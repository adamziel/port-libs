<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpInheritedLanguagePacket = static function (
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
        . '<dc:title><rdf:Alt xml:lang="x-default">'
        . '<rdf:li xml:lang="fr-FR">' . htmlspecialchars($localizedTitle, ENT_XML1) . '</rdf:li>'
        . '<rdf:li>' . htmlspecialchars($title, ENT_XML1) . '</rdf:li>'
        . '</rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Inherited Language Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt xml:lang="x-default">'
        . '<rdf:li xml:lang="fr-FR">' . htmlspecialchars($localizedDescription, ENT_XML1) . '</rdf:li>'
        . '<rdf:li>' . htmlspecialchars($description, ENT_XML1) . '</rdf:li>'
        . '</rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-inherited-language</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Inherited Language Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Inherited Language Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T15:22:56Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpInheritedLanguagePdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP inherited-language boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Inherited Language Info Title) /Author (Info Inherited Author) /Producer (Info Inherited Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'uses inherited x-default xml language in XMP alternatives before WordPress metadata import' => static function (
        TestRunner $t
    ) use ($xmpInheritedLanguagePacket, $xmpInheritedLanguagePdf): void {
        $localizedTitle = 'Localized Inherited Language Decoy Title';
        $localizedDescription = 'Localized inherited language description must not become the document summary';
        $metadataBytes = $xmpInheritedLanguagePacket(
            'Current Inherited Language XMP Title',
            $localizedTitle,
            'Inherited x-default XMP description wins before WordPress import',
            $localizedDescription,
            '2026-06-05T11:22:56-04:00'
        );
        $pdf = $xmpInheritedLanguagePdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Inherited Language Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Inherited Language XMP Title', $metadata['title']);
        $t->same('Inherited x-default XMP description wins before WordPress import', $metadata['description']);
        $t->same('Current Inherited Language XMP Title', $metadata['xmp']['title'] ?? null);
        $t->same('Inherited x-default XMP description wins before WordPress import', $metadata['xmp']['description'] ?? null);
        $t->same(['Inherited Language Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-inherited-language'], $metadata['keywords']);
        $t->same('Inherited Language Tool', $metadata['creator_tool']);
        $t->same('Inherited Language Producer', $metadata['producer']);
        $t->same('2026-06-05T11:22:56-04:00', $metadata['created_at']);
        $t->same('2026-06-05T15:22:56Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T15:22:56Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same('Inherited Language Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Inherited Language Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, $localizedTitle));
        $t->true(is_string($encoded) && !str_contains($encoded, $localizedDescription));
        $t->true(!str_contains($plainText, 'Current Inherited Language XMP Title'));
        $t->true(!str_contains($plainText, $localizedTitle));
    },
    'summarizes rejected inherited-language XMP streams without leaking localized alternatives' => static function (
        TestRunner $t
    ) use ($xmpInheritedLanguagePacket, $xmpInheritedLanguagePdf): void {
        $metadataBytes = $xmpInheritedLanguagePacket(
            'Rejected Inherited Language XMP Title',
            'Rejected Localized Inherited Title',
            'Rejected inherited x-default description',
            'Rejected localized inherited description',
            '2026-06-05T15:23:56Z'
        );
        $pdf = $xmpInheritedLanguagePdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Inherited Language Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Inherited Language Info Title', $metadata['title']);
        $t->same('Rejected XMP Inherited Language Boundary Body', $plainText);
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
        $t->same('2026-06-05T15:23:56Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T15:22:56Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Inherited Language XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Localized Inherited Title'));
        $t->true(!str_contains($plainText, 'Rejected Inherited Language XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Localized Inherited Title'));
    },
];

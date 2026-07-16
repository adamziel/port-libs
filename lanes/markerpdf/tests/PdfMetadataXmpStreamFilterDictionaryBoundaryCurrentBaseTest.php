<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpStreamFilterDictionaryPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Stream Filter Dictionary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-filter-dictionary-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Stream Filter Dictionary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Stream Filter Dictionary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T13:33:52Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpStreamFilterDictionaryPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Length " . strlen($metadataBytes) . " >>\nstream\n{$metadataBytes}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Stream Filter Dictionary Info Title) /Author (Info Stream Filter Author) /Producer (Info Stream Filter Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'ignores fake metadata stream Filter names inside dictionary strings before XMP promotion' => static function (
        TestRunner $t
    ) use ($xmpStreamFilterDictionaryPacket, $xmpStreamFilterDictionaryPdf): void {
        $metadataBytes = $xmpStreamFilterDictionaryPacket(
            'Current Stream Filter Dictionary XMP Title',
            'A fake Filter name inside a metadata dictionary string is not a stream filter.',
            '2026-06-05T09:33:52-04:00'
        );
        $pdf = $xmpStreamFilterDictionaryPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML /Desc (Decoy /Filter /FlateDecode inside metadata string)',
            'XMP Stream Filter Dictionary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Stream Filter Dictionary XMP Title', $metadata['title']);
        $t->same('A fake Filter name inside a metadata dictionary string is not a stream filter.', $metadata['description']);
        $t->same(['Stream Filter Dictionary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-filter-dictionary-boundary'], $metadata['keywords']);
        $t->same('Stream Filter Dictionary Tool', $metadata['creator_tool']);
        $t->same('Stream Filter Dictionary Producer', $metadata['producer']);
        $t->same('2026-06-05T09:33:52-04:00', $metadata['created_at']);
        $t->same('2026-06-05T13:33:52Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T13:33:52Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same('Stream Filter Dictionary Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Stream Filter Dictionary Body', $plainText);
        $t->same(false, isset($metadata['catalog']['metadata_stream_review']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Decoy /Filter /FlateDecode'));
        $t->true(!str_contains($plainText, 'Current Stream Filter Dictionary XMP Title'));
        $t->true(!str_contains($plainText, 'Decoy /Filter /FlateDecode'));
    },
    'summarizes rejected unfiltered XML streams without treating string Filter decoys as filters' => static function (
        TestRunner $t
    ) use ($xmpStreamFilterDictionaryPacket, $xmpStreamFilterDictionaryPdf): void {
        $metadataBytes = $xmpStreamFilterDictionaryPacket(
            'Rejected Stream Filter Dictionary XMP Title',
            'Rejected unfiltered XML metadata is summarized only.',
            '2026-06-05T13:34:52Z'
        );
        $pdf = $xmpStreamFilterDictionaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml /Desc (Decoy /Filter /FlateDecode inside review string)',
            'Rejected XMP Stream Filter Dictionary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Stream Filter Dictionary Info Title', $metadata['title']);
        $t->same('Rejected XMP Stream Filter Dictionary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(false, isset($review['filters']));
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-05T13:34:52Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T13:33:52Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Stream Filter Dictionary XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Decoy /Filter /FlateDecode'));
        $t->true(!str_contains($plainText, 'Rejected Stream Filter Dictionary XMP Title'));
        $t->true(!str_contains($plainText, 'Decoy /Filter /FlateDecode'));
    },
];

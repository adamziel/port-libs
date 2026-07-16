<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpDublinCoreDatePacket = static function (
    string $title,
    string $description,
    string $date,
    string $laterDate
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Dublin Core Date Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-dc-date</rdf:li></rdf:Bag></dc:subject>'
        . '<dc:date><rdf:Seq>'
        . '<rdf:li>' . htmlspecialchars($date, ENT_XML1) . '</rdf:li>'
        . '<rdf:li>' . htmlspecialchars($laterDate, ENT_XML1) . '</rdf:li>'
        . '</rdf:Seq></dc:date>'
        . '<pdf:Producer>Dublin Core Date Producer</pdf:Producer>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpDublinCoreDatePdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP Dublin Core date boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Dublin Core Date Info Title) /Author (Info Date Author) /CreationDate (D:20240101000000Z) /Producer (Info Date Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'uses XMP Dublin Core date sequences before stale Info dates' => static function (
        TestRunner $t
    ) use ($xmpDublinCoreDatePacket, $xmpDublinCoreDatePdf): void {
        $currentXmp = $xmpDublinCoreDatePacket(
            'Current Dublin Core Date XMP Title',
            'Dublin Core date sequences can define the current document date',
            '2026-06-07T09:34:56-08:00',
            '2026-06-08T10:00:00Z'
        );
        $decoyXmp = $xmpDublinCoreDatePacket(
            'Trailing Dublin Core Date Decoy Title',
            'Trailing Dublin Core date packet must stay outside metadata',
            '2026-06-09T10:00:00Z',
            '2026-06-10T10:00:00Z'
        );
        $pdf = $xmpDublinCoreDatePdf(
            $currentXmp . "\0\0 \n" . $decoyXmp,
            '/Type /Metadata /Subtype /XML',
            'XMP Dublin Core Date Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Dublin Core Date XMP Title', $metadata['title']);
        $t->same('Dublin Core date sequences can define the current document date', $metadata['description']);
        $t->same(['Dublin Core Date Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-dc-date'], $metadata['keywords']);
        $t->same('Dublin Core Date Producer', $metadata['producer']);
        $t->same('2026-06-07T09:34:56-08:00', $metadata['created_at']);
        $t->same('2026-06-07T17:34:56Z', $metadata['created_at_utc']);
        $t->same(['2026-06-07T09:34:56-08:00', '2026-06-08T10:00:00Z'], $metadata['xmp']['dates'] ?? null);
        $t->same(['2026-06-07T09:34:56-08:00', '2026-06-08T10:00:00Z'], $metadata['xmp_dublin_core']['dates'] ?? null);
        $t->same(2, $metadata['xmp_dublin_core']['date_count'] ?? null);
        $t->same(['2026-06-07T17:34:56Z', '2026-06-08T10:00:00Z'], $metadata['xmp_dublin_core']['dates_utc'] ?? null);
        $t->same('D:20240101000000Z', $metadata['info']['CreationDate'] ?? null);
        $t->same('XMP Dublin Core Date Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Dublin Core Date Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, '2024-01-01T00:00:00Z'));
        $t->true(!str_contains($plainText, 'Current Dublin Core Date XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Dublin Core Date Decoy Title'));
    },
    'summarizes rejected Dublin Core date XMP streams without exposing date text values' => static function (
        TestRunner $t
    ) use ($xmpDublinCoreDatePacket, $xmpDublinCoreDatePdf): void {
        $currentXmp = $xmpDublinCoreDatePacket(
            'Rejected Dublin Core Date XMP Title',
            'Rejected Dublin Core date packet is summarized only',
            '2026-06-07T17:35:56Z',
            '2026-06-08T10:00:00Z'
        );
        $decoyXmp = $xmpDublinCoreDatePacket(
            'Rejected Dublin Core Date Decoy Title',
            'Rejected trailing Dublin Core date packet stays hidden',
            '2026-06-09T10:00:00Z',
            '2026-06-10T10:00:00Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpDublinCoreDatePdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Dublin Core Date Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Dublin Core Date Info Title', $metadata['title']);
        $t->same('Rejected XMP Dublin Core Date Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(['title', 'description', 'producer', 'created_at', 'authors', 'keywords', 'dublin_core'], $summary['field_names'] ?? null);
        $t->same(2, $summary['author_count'] ?? null);
        $t->same(2, $summary['keyword_count'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-07T17:35:56Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Dublin Core Date XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, '2026-06-08T10:00:00Z'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Dublin Core Date Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Dublin Core Date XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Dublin Core Date Decoy Title'));
    },
];

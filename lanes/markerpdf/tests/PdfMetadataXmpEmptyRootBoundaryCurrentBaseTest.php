<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpEmptyRootBoundaryPacket = static function (string $title, string $description, string $date): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Empty Root Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-empty-root</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Empty Root Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Empty Root Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T06:02:41Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$xmpEmptyRootBoundaryBytes = static function (
    string $title,
    string $description,
    string $date
) use ($xmpEmptyRootBoundaryPacket): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/"></x:xmpmeta>'
        . $xmpEmptyRootBoundaryPacket($title, $description, $date)
        . '<?xpacket end="w"?>';
};

$xmpEmptyRootBoundaryPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP empty-root boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Empty Root Info Title) /Author (Info Fallback Author) /Producer (Info Fallback Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'skips empty non-self-closing XMP wrappers before the current metadata root' => static function (
        TestRunner $t
    ) use ($xmpEmptyRootBoundaryBytes, $xmpEmptyRootBoundaryPacket, $xmpEmptyRootBoundaryPdf): void {
        $currentXmp = $xmpEmptyRootBoundaryBytes(
            'Current Empty Root XMP Title',
            'Current XMP root follows an empty non-self-closing wrapper',
            '2026-06-05T02:02:41-04:00'
        );
        $decoyXmp = $xmpEmptyRootBoundaryPacket(
            'Trailing Empty Root Decoy Title',
            'Trailing empty-root packet must not replace current metadata',
            '2026-06-05T06:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpEmptyRootBoundaryPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'Empty Root XMP Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Empty Root XMP Title', $metadata['title']);
        $t->same('Current XMP root follows an empty non-self-closing wrapper', $metadata['description']);
        $t->same(['Empty Root Boundary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-empty-root'], $metadata['keywords']);
        $t->same('Empty Root Boundary Tool', $metadata['creator_tool']);
        $t->same('Empty Root Boundary Producer', $metadata['producer']);
        $t->same('2026-06-05T02:02:41-04:00', $metadata['created_at']);
        $t->same('2026-06-05T06:02:41Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T06:02:41Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Empty Root Info Title', $metadata['info']['Title'] ?? null);
        $t->same('Empty Root XMP Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Empty Root Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing empty-root packet'));
        $t->true(!str_contains($plainText, 'Current Empty Root XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Empty Root Decoy Title'));
    },
    'summarizes rejected XML streams after empty non-self-closing XMP wrappers' => static function (
        TestRunner $t
    ) use ($xmpEmptyRootBoundaryBytes, $xmpEmptyRootBoundaryPacket, $xmpEmptyRootBoundaryPdf): void {
        $currentXmp = $xmpEmptyRootBoundaryBytes(
            'Rejected Empty Root XMP Title',
            'Rejected current XMP root remains review-only',
            '2026-06-05T06:03:41Z'
        );
        $decoyXmp = $xmpEmptyRootBoundaryPacket(
            'Rejected Empty Root Decoy Title',
            'Rejected trailing empty-root packet stays hidden',
            '2026-06-05T06:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpEmptyRootBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected Empty Root XMP Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Empty Root Info Title', $metadata['title']);
        $t->same('Rejected Empty Root XMP Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-05T06:03:41Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T06:02:41Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Empty Root XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Empty Root Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Empty Root XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Empty Root Decoy Title'));
    },
];

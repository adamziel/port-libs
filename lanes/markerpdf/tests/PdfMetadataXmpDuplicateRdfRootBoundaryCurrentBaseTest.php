<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpDuplicateRdfRootBytes = static function (
    string $title,
    string $date
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T18:01:42Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:creator><rdf:Seq><rdf:li>Stale Duplicate RDF Author</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Stale duplicate RDF description leak</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>stale-rdf-keyword</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Stale Duplicate RDF Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Stale Duplicate RDF Tool</xmp:CreatorTool>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpDuplicateRdfRootPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP duplicate-RDF-root fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Duplicate RDF Info Title) /Author (Info Duplicate RDF Author) /Producer (Info Duplicate RDF Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'uses only the first document-level RDF root in catalog XMP metadata streams' => static function (
        TestRunner $t
    ) use ($xmpDuplicateRdfRootBytes, $xmpDuplicateRdfRootPdf): void {
        $metadataBytes = $xmpDuplicateRdfRootBytes(
            'Current Duplicate RDF XMP Title',
            '2026-06-06T14:01:42-04:00'
        );
        $pdf = $xmpDuplicateRdfRootPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Duplicate RDF Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Duplicate RDF XMP Title', $metadata['title']);
        $t->same('2026-06-06T14:01:42-04:00', $metadata['created_at']);
        $t->same('2026-06-06T18:01:42Z', $metadata['created_at_utc']);
        $t->same('2026-06-06T18:01:42Z', $metadata['metadata_date_utc']);
        $t->same(['Info Duplicate RDF Author'], $metadata['authors'] ?? []);
        $t->same(false, isset($metadata['description']));
        $t->same('Info Duplicate RDF Producer', $metadata['producer'] ?? null);
        $t->same(false, isset($metadata['creator_tool']));
        $t->same(false, isset($metadata['keywords']));
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Duplicate RDF Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Duplicate RDF Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Duplicate RDF Author'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale duplicate RDF description leak'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Duplicate RDF Producer'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-rdf-keyword'));
        $t->true(!str_contains($plainText, 'Current Duplicate RDF XMP Title'));
        $t->true(!str_contains($plainText, 'Stale Duplicate RDF Author'));
    },
    'summarizes rejected XML streams from the first RDF root only' => static function (
        TestRunner $t
    ) use ($xmpDuplicateRdfRootBytes, $xmpDuplicateRdfRootPdf): void {
        $metadataBytes = $xmpDuplicateRdfRootBytes(
            'Rejected Duplicate RDF XMP Title',
            '2026-06-06T18:02:42Z'
        );
        $pdf = $xmpDuplicateRdfRootPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Duplicate RDF Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Duplicate RDF Info Title', $metadata['title']);
        $t->same('Rejected XMP Duplicate RDF Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'created_at', 'metadata_date'], $summary['field_names'] ?? null);
        $t->same(0, $summary['author_count'] ?? null);
        $t->same(0, $summary['keyword_count'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-06T18:02:42Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-06T18:01:42Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Duplicate RDF XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Duplicate RDF Author'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Duplicate RDF Producer'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-rdf-keyword'));
        $t->true(!str_contains($plainText, 'Rejected Duplicate RDF XMP Title'));
        $t->true(!str_contains($plainText, 'Stale Duplicate RDF Author'));
    },
];

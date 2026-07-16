<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpUnboundedRootBoundaryRoot = static function (string $title, string $description, string $date): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Unbounded Root Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-unbounded-root-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Unbounded Root Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Unbounded Root Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T10:08:21Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$xmpUnboundedRootBoundaryBytes = static function (
    string $malformedTitle,
    string $trailingTitle
) use ($xmpUnboundedRootBoundaryRoot): string {
    $malformedFirstRoot = preg_replace(
        '/<\/x:xmpmeta>$/',
        '',
        $xmpUnboundedRootBoundaryRoot(
            $malformedTitle,
            'An unclosed first Adobe XMP root must fail closed before WordPress metadata import.',
            '2026-06-08T06:08:21-04:00'
        )
    ) ?? '';

    $trailingDecoyRoot = $xmpUnboundedRootBoundaryRoot(
        $trailingTitle,
        'A later valid-looking XMP root must not replace the malformed first root.',
        '2026-06-08T10:59:59Z'
    );

    return $malformedFirstRoot . "\0\0\n" . $trailingDecoyRoot;
};

$xmpUnboundedRootBoundaryPdf = static function (
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
        . "6 0 obj\n<< /Title (Unbounded Root Info Title) /Author (Info Unbounded Root Author) /Producer (Info Unbounded Root Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'fails closed on unbounded unpacketed XMP root before trailing root promotion' => static function (
        TestRunner $t
    ) use ($xmpUnboundedRootBoundaryBytes, $xmpUnboundedRootBoundaryPdf): void {
        $metadataBytes = $xmpUnboundedRootBoundaryBytes(
            'Unbounded First XMP Root Title',
            'Trailing Valid XMP Root Decoy Title'
        );
        $pdf = $xmpUnboundedRootBoundaryPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Unbounded Root Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Unbounded Root Info Title', $metadata['title']);
        $t->same(['Info Unbounded Root Author'], $metadata['authors']);
        $t->same('XMP Unbounded Root Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_malformed_document_xmp_packet', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same('rejected_malformed_first_xmp_packet', $summary['status'] ?? null);
        $t->same('unbounded_adobe_xmpmeta_root', $summary['malformed_packet_reason'] ?? null);
        $t->same(0, $summary['malformed_packet_index'] ?? null);
        $t->same([], $summary['field_names'] ?? null);
        $t->same(0, $summary['field_count'] ?? null);
        $t->same(false, array_key_exists('packet_boundary_applied', $summary));
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Unbounded First XMP Root Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Valid XMP Root Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'xmp-unbounded-root-boundary'));
        $t->true(!str_contains($plainText, 'Unbounded First XMP Root Title'));
        $t->true(!str_contains($plainText, 'Trailing Valid XMP Root Decoy Title'));
    },
    'summarizes rejected XML streams from unbounded unpacketed roots without trailing replacement' => static function (
        TestRunner $t
    ) use ($xmpUnboundedRootBoundaryBytes, $xmpUnboundedRootBoundaryPdf): void {
        $metadataBytes = $xmpUnboundedRootBoundaryBytes(
            'Rejected Unbounded First XMP Root Title',
            'Rejected Trailing Valid XMP Root Decoy Title'
        );
        $pdf = $xmpUnboundedRootBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Unbounded Root Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Unbounded Root Info Title', $metadata['title']);
        $t->same(['Info Unbounded Root Author'], $metadata['authors']);
        $t->same('Rejected XMP Unbounded Root Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same('rejected_malformed_first_xmp_packet', $summary['status'] ?? null);
        $t->same('unbounded_adobe_xmpmeta_root', $summary['malformed_packet_reason'] ?? null);
        $t->same([], $summary['field_names'] ?? null);
        $t->same(0, $summary['field_count'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Unbounded First XMP Root Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Trailing Valid XMP Root Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Unbounded First XMP Root Title'));
        $t->true(!str_contains($plainText, 'Rejected Trailing Valid XMP Root Decoy Title'));
    },
];

<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpNamespaceBoundaryPacket = static function (
    string $title,
    string $description,
    string $date,
    string $rootPrefix = 'x',
    string $rootNamespace = 'adobe:ns:meta/'
): string {
    return '<' . $rootPrefix . ':xmpmeta xmlns:' . $rootPrefix . '="' . htmlspecialchars($rootNamespace, ENT_XML1) . '">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Namespace Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-namespace-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Namespace Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Namespace Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T06:23:17Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</' . $rootPrefix . ':xmpmeta>';
};

$xmpNamespaceBoundaryBytes = static function (
    string $title,
    string $description,
    string $date
) use ($xmpNamespaceBoundaryPacket): string {
    $wrongNamespace = $xmpNamespaceBoundaryPacket(
        'Wrong Namespace Decoy XMP Title',
        'A non-Adobe xmpmeta local-name wrapper must not block the current packet.',
        '2026-06-05T06:59:59Z',
        'notxmp',
        'urn:not-adobe-xmp'
    );
    $current = $xmpNamespaceBoundaryPacket($title, $description, $date);
    $trailing = $xmpNamespaceBoundaryPacket(
        'Trailing Namespace Decoy XMP Title',
        'Trailing namespace packet stays outside the current root.',
        '2026-06-05T07:00:00Z'
    );

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . $wrongNamespace
        . $current
        . '<?xpacket end="w"?>'
        . "\0\0"
        . $trailing;
};

$xmpUnmappedAdobeRootBoundaryBytes = static function () use ($xmpNamespaceBoundaryPacket): string {
    $unmappedCurrentRoot = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:wp="https://example.org/xmp/wp-private/1.0/">'
        . '<wp:PrivateReviewValue>Current Adobe root has no promoted document fields</wp:PrivateReviewValue>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
    $trailing = $xmpNamespaceBoundaryPacket(
        'Trailing Unmapped Decoy XMP Title',
        'A later XMP packet must not replace the first Adobe root boundary.',
        '2026-06-05T07:01:00Z'
    );

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . $unmappedCurrentRoot
        . '<?xpacket end="w"?>'
        . "\0\0"
        . $trailing;
};

$xmpNamespaceBoundaryPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP namespace boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Namespace Boundary Info Title) /Author (Info Namespace Author) /Producer (Info Namespace Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'skips non-Adobe xmpmeta wrappers before the current document XMP root' => static function (
        TestRunner $t
    ) use ($xmpNamespaceBoundaryBytes, $xmpNamespaceBoundaryPdf): void {
        $metadataBytes = $xmpNamespaceBoundaryBytes(
            'Current Namespace Boundary XMP Title',
            'Current Adobe XMP root follows a non-document xmpmeta wrapper',
            '2026-06-05T02:23:17-04:00'
        );
        $pdf = $xmpNamespaceBoundaryPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'Namespace XMP Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Namespace Boundary XMP Title', $metadata['title']);
        $t->same('Current Adobe XMP root follows a non-document xmpmeta wrapper', $metadata['description']);
        $t->same(['Namespace Boundary Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-namespace-boundary'], $metadata['keywords']);
        $t->same('Namespace Boundary Tool', $metadata['creator_tool']);
        $t->same('Namespace Boundary Producer', $metadata['producer']);
        $t->same('2026-06-05T02:23:17-04:00', $metadata['created_at']);
        $t->same('2026-06-05T06:23:17Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T06:23:17Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Namespace Boundary Info Title', $metadata['info']['Title'] ?? null);
        $t->same('Namespace XMP Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Wrong Namespace Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Namespace Decoy XMP Title'));
        $t->true(!str_contains($plainText, 'Current Namespace Boundary XMP Title'));
        $t->true(!str_contains($plainText, 'Wrong Namespace Decoy XMP Title'));
    },
    'summarizes rejected XML streams after non-Adobe xmpmeta wrappers' => static function (
        TestRunner $t
    ) use ($xmpNamespaceBoundaryBytes, $xmpNamespaceBoundaryPdf): void {
        $metadataBytes = $xmpNamespaceBoundaryBytes(
            'Rejected Namespace Boundary XMP Title',
            'Rejected Adobe XMP root remains review-only',
            '2026-06-05T06:24:17Z'
        );
        $pdf = $xmpNamespaceBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected Namespace XMP Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Namespace Boundary Info Title', $metadata['title']);
        $t->same('Rejected Namespace XMP Boundary Body', $plainText);
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
        $t->same('2026-06-05T06:24:17Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Namespace Boundary XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Wrong Namespace Decoy XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Namespace Boundary XMP Title'));
        $t->true(!str_contains($plainText, 'Wrong Namespace Decoy XMP Title'));
    },
    'does not promote trailing packets after the first Adobe XMP root boundary' => static function (
        TestRunner $t
    ) use ($xmpUnmappedAdobeRootBoundaryBytes, $xmpNamespaceBoundaryPdf): void {
        $metadataBytes = $xmpUnmappedAdobeRootBoundaryBytes();
        $pdf = $xmpNamespaceBoundaryPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'Unmapped Adobe Root Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['info'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Namespace Boundary Info Title', $metadata['title']);
        $t->same('Unmapped Adobe Root Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Unmapped Decoy XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'A later XMP packet must not replace'));
        $t->true(!str_contains($plainText, 'Trailing Unmapped Decoy XMP Title'));
        $t->true(!str_contains($plainText, 'Current Adobe root has no promoted document fields'));
    },
];

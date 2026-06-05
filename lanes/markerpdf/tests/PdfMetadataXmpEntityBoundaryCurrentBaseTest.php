<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpEntityBoundaryPacket = static function (
    string $titleEntity,
    string $descriptionEntity,
    string $dateEntity,
    string $bodyText
): string {
    return '<!DOCTYPE x:xmpmeta ['
        . '<!ENTITY entityTitle "' . htmlspecialchars($titleEntity, ENT_XML1) . '">'
        . '<!ENTITY entityDescription "' . htmlspecialchars($descriptionEntity, ENT_XML1) . '">'
        . '<!ENTITY entityDate "' . htmlspecialchars($dateEntity, ENT_XML1) . '">'
        . ']>'
        . '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">&entityTitle;</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Entity Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">&entityDescription;</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-entity-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Entity Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Entity Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>&entityDate;</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T03:27:55Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>'
        . "\n<!-- {$bodyText} stays visible only through the page content stream. -->";
};

$xmpEntityBoundaryPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP entity boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Entity Boundary Info Title) /Author (Info Entity Author) /Producer (Info Entity Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'rejects document XMP DTD entity declarations before metadata promotion' => static function (
        TestRunner $t
    ) use ($xmpEntityBoundaryPacket, $xmpEntityBoundaryPdf): void {
        $metadataBytes = $xmpEntityBoundaryPacket(
            'Expanded Entity XMP Title',
            'Expanded entity description must not become WordPress metadata',
            '2026-06-04T23:27:55-04:00',
            'XMP Entity Boundary Body'
        );
        $pdf = $xmpEntityBoundaryPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Entity Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Entity Boundary Info Title', $metadata['title']);
        $t->same('XMP Entity Boundary Body', $plainText);
        $t->same('rejected_unsafe_document_xmp_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same('rejected_dtd_or_entity_declaration', $summary['status'] ?? null);
        $t->same(['DOCTYPE', 'ENTITY'], $summary['unsafe_markup'] ?? null);
        $t->same([], $summary['field_names'] ?? null);
        $t->same(0, $summary['field_count'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Expanded Entity XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Expanded entity description'));
        $t->true(!str_contains($plainText, 'Expanded Entity XMP Title'));
        $t->true(!str_contains($plainText, 'Expanded entity description'));
    },
    'summarizes rejected XML metadata stream DTD entities without expansion' => static function (
        TestRunner $t
    ) use ($xmpEntityBoundaryPacket, $xmpEntityBoundaryPdf): void {
        $metadataBytes = $xmpEntityBoundaryPacket(
            'Rejected Entity XMP Title',
            'Rejected entity description must stay review-only',
            '2026-06-05T03:28:55Z',
            'Rejected XMP Entity Boundary Body'
        );
        $pdf = $xmpEntityBoundaryPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Entity Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Entity Boundary Info Title', $metadata['title']);
        $t->same('Rejected XMP Entity Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same('rejected_dtd_or_entity_declaration', $summary['status'] ?? null);
        $t->same(['DOCTYPE', 'ENTITY'], $summary['unsafe_markup'] ?? null);
        $t->same([], $summary['field_names'] ?? null);
        $t->same(0, $summary['field_count'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Entity XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected entity description'));
        $t->true(!str_contains($plainText, 'Rejected Entity XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected entity description'));
    },
];

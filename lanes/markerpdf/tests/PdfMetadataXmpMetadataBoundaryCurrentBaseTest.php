<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpMetadataBoundaryPdf = static function (
    string $catalogMetadataValue,
    string $bodyText,
    string $extraObjects = ''
): string {
    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata {$catalogMetadataValue} >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . $extraObjects
        . "6 0 obj\n<< /Title (Metadata Boundary Info Title) /Author (Metadata Boundary Author) /Producer (Metadata Boundary Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

$xmpMetadataBoundaryPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Duplicate Metadata Editor</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-duplicate-metadata</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Duplicate Metadata Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Duplicate Metadata Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T23:30:38Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

return [
    'treats catalog Metadata null as absent before WordPress import' => static function (
        TestRunner $t
    ) use ($xmpMetadataBoundaryPdf): void {
        $pdf = $xmpMetadataBoundaryPdf(
            'null',
            'Null Metadata Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['info'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Metadata Boundary Info Title', $metadata['title']);
        $t->same(['Metadata Boundary Author'], $metadata['authors']);
        $t->same('Null Metadata Boundary Body', $plainText);
        $t->same(false, isset($metadata['catalog']['metadata_stream_review']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'catalog_metadata_stream_boundary'));
    },
    'keeps direct catalog Metadata dictionaries review-only before WordPress import' => static function (
        TestRunner $t
    ) use ($xmpMetadataBoundaryPdf): void {
        $pdf = $xmpMetadataBoundaryPdf(
            '<< /Type /Metadata /Subtype /XML /HiddenTitle (Direct Catalog Metadata Leak) >>',
            'Direct Metadata Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Metadata Boundary Info Title', $metadata['title']);
        $t->same(['Metadata Boundary Author'], $metadata['authors']);
        $t->same('Direct Metadata Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_non_indirect_metadata_reference', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Direct Catalog Metadata Leak'));
        $t->true(!str_contains($plainText, 'Direct Catalog Metadata Leak'));
    },
    'keeps unresolved catalog Metadata references as fail-closed review metadata' => static function (
        TestRunner $t
    ) use ($xmpMetadataBoundaryPdf): void {
        $pdf = $xmpMetadataBoundaryPdf(
            '99 0 R',
            'Unresolved Metadata Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Metadata Boundary Info Title', $metadata['title']);
        $t->same('Unresolved Metadata Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('unresolved_metadata_reference', $review['status'] ?? null);
        $t->same(99, $review['object_number'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
    },
    'rejects indirect catalog Metadata dictionaries that are not PDF streams' => static function (
        TestRunner $t
    ) use ($xmpMetadataBoundaryPdf): void {
        $metadataObject = "5 0 obj\n"
            . "<< /Type /Metadata /Subtype /XML /HiddenTitle (Indirect Metadata Dictionary Leak) /Length 123 >>\n"
            . "endobj\n";
        $pdf = $xmpMetadataBoundaryPdf(
            '5 0 R',
            'Indirect Dictionary Metadata Boundary Body',
            $metadataObject
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Metadata Boundary Info Title', $metadata['title']);
        $t->same('Indirect Dictionary Metadata Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_non_stream_metadata_object', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(123, $review['declared_length'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Indirect Metadata Dictionary Leak'));
        $t->true(!str_contains($plainText, 'Indirect Metadata Dictionary Leak'));
    },
    'records unreadable XMP metadata stream filters without promoting payload text' => static function (
        TestRunner $t
    ) use ($xmpMetadataBoundaryPdf): void {
        $badCompressedXmp = 'not-a-valid-flate-xmp-stream with Unreadable Metadata XMP Leak Title';
        $metadataObject = "5 0 obj\n"
            . '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($badCompressedXmp) . " >>\n"
            . "stream\n{$badCompressedXmp}\nendstream\nendobj\n";
        $pdf = $xmpMetadataBoundaryPdf(
            '5 0 R',
            'Unreadable Metadata Boundary Body',
            $metadataObject
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Metadata Boundary Info Title', $metadata['title']);
        $t->same('Unreadable Metadata Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('unreadable_metadata_stream', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($badCompressedXmp), $review['declared_length'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Unreadable Metadata XMP Leak Title'));
        $t->true(!str_contains($plainText, 'Unreadable Metadata XMP Leak Title'));
    },
    'rejects duplicate catalog Metadata entries before XMP promotion' => static function (
        TestRunner $t
    ) use ($xmpMetadataBoundaryPdf, $xmpMetadataBoundaryPacket): void {
        $xmp = $xmpMetadataBoundaryPacket(
            'Duplicate Catalog Metadata Hidden XMP Title',
            'A duplicate catalog Metadata key must not define WordPress metadata',
            '2026-06-05T19:30:38-04:00'
        );
        $compressedXmp = gzcompress($xmp);
        if (!is_string($compressedXmp)) {
            throw new RuntimeException('Unable to compress duplicate catalog Metadata fixture stream.');
        }

        $metadataObject = "5 0 obj\n"
            . '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($compressedXmp) . " >>\n"
            . "stream\n{$compressedXmp}\nendstream\nendobj\n";
        $pdf = $xmpMetadataBoundaryPdf(
            'null /Metadata 5 0 R',
            'Duplicate Catalog Metadata Boundary Body',
            $metadataObject
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $entries = $review['entries'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Metadata Boundary Info Title', $metadata['title']);
        $t->same(['Metadata Boundary Author'], $metadata['authors']);
        $t->same('Duplicate Catalog Metadata Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_duplicate_metadata_entries', $review['status'] ?? null);
        $t->same(2, $review['metadata_entry_count'] ?? null);
        $t->same([5], $review['candidate_object_numbers'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('null', $entries[0]['kind'] ?? null);
        $t->same('indirect_reference', $entries[1]['kind'] ?? null);
        $t->same(5, $entries[1]['object_number'] ?? null);
        $t->same(0, $entries[1]['object_generation'] ?? null);
        $t->same('Metadata', $entries[1]['type'] ?? null);
        $t->same('XML', $entries[1]['subtype'] ?? null);
        $t->same(true, $entries[1]['has_stream_keyword'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Duplicate Catalog Metadata Hidden XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'duplicate catalog Metadata key'));
        $t->true(!str_contains($plainText, 'Duplicate Catalog Metadata Hidden XMP Title'));
    },
    'rejects catalog Metadata references followed by extra top-level operands before XMP promotion' => static function (
        TestRunner $t
    ) use ($xmpMetadataBoundaryPdf, $xmpMetadataBoundaryPacket): void {
        $xmp = $xmpMetadataBoundaryPacket(
            'Extra Operand Metadata Hidden XMP Title',
            'A catalog Metadata reference followed by an extra operand must not define WordPress metadata',
            '2026-06-06T02:06:10Z'
        );

        $metadataObject = "5 0 obj\n"
            . '<< /Type /Metadata /Subtype /XML /Length ' . strlen($xmp) . " >>\n"
            . "stream\n{$xmp}\nendstream\nendobj\n"
            . "7 0 obj\n<< /S /JavaScript /JS (app.alert\\('extra metadata operand action tail'\\)) >>\nendobj\n";
        $pdf = $xmpMetadataBoundaryPdf(
            '5 0 R 7 0 R',
            'Extra Operand Metadata Boundary Body',
            $metadataObject
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Metadata Boundary Info Title', $metadata['title']);
        $t->same(['Metadata Boundary Author'], $metadata['authors']);
        $t->same('Extra Operand Metadata Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_malformed_metadata_operand', $review['status'] ?? null);
        $t->same(1, $review['metadata_entry_count'] ?? null);
        $t->same(2, $review['metadata_operand_count'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same([7], $review['trailing_reference_object_numbers'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Extra Operand Metadata Hidden XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'extra metadata operand action tail'));
        $t->true(!str_contains($plainText, 'Extra Operand Metadata Hidden XMP Title'));
        $t->true(!str_contains($plainText, 'extra metadata operand action tail'));
    },
];

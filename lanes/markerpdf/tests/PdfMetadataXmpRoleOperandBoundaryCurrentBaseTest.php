<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpRoleOperandBoundaryPacket = static function (
    string $title,
    string $description,
    string $date
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>XMP Role Operand Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-role-operand-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Role Operand Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Role Operand Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T09:03:51Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpRoleOperandBoundaryPdf = static function (
    string $metadataDictionary,
    string $bodyText,
    string $extraObjects = ''
) use ($xmpRoleOperandBoundaryPacket): string {
    $xmp = $xmpRoleOperandBoundaryPacket(
        'Hidden Role Operand XMP Title',
        'Tailed role operands must not promote document XMP metadata.',
        '2026-06-08T05:03:51-04:00'
    );
    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Length " . strlen($xmp) . " >>\nstream\n{$xmp}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Role Operand Info Title) /Author (Info Role Operand Author) /Producer (Info Role Operand Producer) >>\nendobj\n"
        . $extraObjects
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'rejects indirect tailed metadata stream Subtype name helpers before document XMP promotion' => static function (
        TestRunner $t
    ) use ($xmpRoleOperandBoundaryPdf): void {
        $pdf = $xmpRoleOperandBoundaryPdf(
            '/Type /Metadata /Subtype 7 0 R',
            'XMP Indirect Role Operand Boundary Body',
            "7 0 obj\n/XML /EmbeddedFile\nendobj\n"
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];
        $operand = $review['role_operands'][0] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Role Operand Info Title', $metadata['title']);
        $t->same(['Info Role Operand Author'], $metadata['authors']);
        $t->same('XMP Indirect Role Operand Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_tailed_metadata_stream_role_operand', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same(['Subtype'], $review['tailed_role_keys'] ?? null);
        $t->same(1, $review['tailed_role_operand_count'] ?? null);
        $t->same('Subtype', $operand['key'] ?? null);
        $t->same('indirect', $operand['kind'] ?? null);
        $t->same(7, $operand['object_number'] ?? null);
        $t->same('XML', $operand['name'] ?? null);
        $t->same('/EmbeddedFile', $operand['trailing_operand_preview'] ?? null);
        $t->same(false, $operand['single_name_token'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-08T09:03:51Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden Role Operand XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'xmp-role-operand-boundary'));
        $t->true(!str_contains($plainText, 'Hidden Role Operand XMP Title'));
    },
    'rejects direct tailed metadata stream Type names before document XMP promotion' => static function (
        TestRunner $t
    ) use ($xmpRoleOperandBoundaryPdf): void {
        $pdf = $xmpRoleOperandBoundaryPdf(
            '/Type /Metadata EmbeddedFile /Subtype /XML',
            'XMP Direct Role Operand Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];
        $operand = $review['role_operands'][0] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Role Operand Info Title', $metadata['title']);
        $t->same('XMP Direct Role Operand Boundary Body', $plainText);
        $t->same('rejected_tailed_metadata_stream_role_operand', $review['status'] ?? null);
        $t->same(['Type'], $review['tailed_role_keys'] ?? null);
        $t->same('Type', $operand['key'] ?? null);
        $t->same('direct', $operand['kind'] ?? null);
        $t->same('Metadata', $operand['name'] ?? null);
        $t->same('EmbeddedFile', $operand['trailing_operand_preview'] ?? null);
        $t->same(false, $operand['single_name_token'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same('2026-06-08T09:03:51Z', $summary['dates_utc']['created_at'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden Role Operand XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Tailed role operands must not promote'));
        $t->true(!str_contains($plainText, 'Hidden Role Operand XMP Title'));
    },
];

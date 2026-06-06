<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpIndirectFilterOperandPacket = static function (
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
        . '<dc:creator><rdf:Seq><rdf:li>Indirect Filter Operand Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-indirect-filter-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Indirect Filter Operand Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Indirect Filter Operand Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T15:53:02Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpIndirectFilterOperandPdf = static function (
    string $xmp,
    string $filterHelperBody,
    string $bodyText
): string {
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress XMP indirect-filter fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter 7 0 R /Length " . strlen($compressedXmp) . " >>\n"
        . "stream\n{$compressedXmp}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Indirect Filter Operand Info Title) /Author (Info Indirect Filter Author) /Producer (Info Indirect Filter Producer) >>\nendobj\n"
        . "7 0 obj\n{$filterHelperBody}\nendobj\n"
        . "8 0 obj\n<< /S /JavaScript /JS (app.alert\\('indirect metadata filter operand tail'\\)) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'rejects document XMP streams with indirect Filter helpers that carry extra operands' => static function (
        TestRunner $t
    ) use ($xmpIndirectFilterOperandPacket, $xmpIndirectFilterOperandPdf): void {
        $xmp = $xmpIndirectFilterOperandPacket(
            'Malformed Indirect Filter XMP Title',
            'An indirect filter helper with extra operands must not define document metadata.',
            '2026-06-06T11:53:02-04:00'
        );
        $pdf = $xmpIndirectFilterOperandPdf(
            $xmp,
            "/FlateDecode /Crypt 8 0 R\n",
            'XMP Indirect Filter Operand Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $filterOperand = $review['filter_operands'][0] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Indirect Filter Operand Info Title', $metadata['title']);
        $t->same(['Info Indirect Filter Author'], $metadata['authors']);
        $t->same('XMP Indirect Filter Operand Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_malformed_metadata_stream_filter_operand', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same('reject_malformed_filter_operands', $review['filter_operand_policy'] ?? null);
        $t->same(1, $review['invalid_filter_operand_count'] ?? null);
        $t->same(1, $review['malformed_filter_operand_count'] ?? null);
        $t->same(0, $review['dictionary_filter_operand_count'] ?? null);
        $t->same(0, $review['unresolved_filter_operand_count'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(1, is_countable($review['filter_operands'] ?? null) ? count($review['filter_operands']) : null);
        $t->same('Filter', $filterOperand['name'] ?? null);
        $t->same('indirect', $filterOperand['kind'] ?? null);
        $t->same(7, $filterOperand['object_number'] ?? null);
        $t->same(0, $filterOperand['generation'] ?? null);
        $t->same(true, $filterOperand['resolved'] ?? null);
        $t->same('/FlateDecode /Crypt 8 0 R', $filterOperand['value_preview'] ?? null);
        $t->same('name', $filterOperand['token_type'] ?? null);
        $t->same(false, $filterOperand['valid_filter_operand'] ?? null);
        $t->same(false, $filterOperand['dictionary_filter_operand'] ?? null);
        $t->same(true, $filterOperand['extra_filter_operand'] ?? null);
        $t->same('name', $filterOperand['extra_filter_operand_type'] ?? null);
        $t->same('/Crypt', $filterOperand['extra_filter_operand_preview'] ?? null);
        $t->same(true, $filterOperand['extra_filter_name_operand'] ?? null);
        $t->same('Crypt', $filterOperand['extra_filter_name'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Malformed Indirect Filter XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'indirect metadata filter operand tail'));
        $t->true(!str_contains($plainText, 'Malformed Indirect Filter XMP Title'));
        $t->true(!str_contains($plainText, 'indirect metadata filter operand tail'));
    },
    'accepts document XMP streams with single-name indirect Filter helpers' => static function (
        TestRunner $t
    ) use ($xmpIndirectFilterOperandPacket, $xmpIndirectFilterOperandPdf): void {
        $xmp = $xmpIndirectFilterOperandPacket(
            'Valid Indirect Filter XMP Title',
            'A single-name indirect filter helper remains a valid metadata stream filter.',
            '2026-06-06T15:53:02Z'
        );
        $pdf = $xmpIndirectFilterOperandPdf(
            $xmp,
            "/FlateDecode\n",
            'XMP Valid Indirect Filter Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Valid Indirect Filter XMP Title', $metadata['title']);
        $t->same('A single-name indirect filter helper remains a valid metadata stream filter.', $metadata['description']);
        $t->same(['Indirect Filter Operand Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-indirect-filter-boundary'], $metadata['keywords']);
        $t->same('Indirect Filter Operand Tool', $metadata['creator_tool']);
        $t->same('Indirect Filter Operand Producer', $metadata['producer']);
        $t->same('2026-06-06T15:53:02Z', $metadata['created_at']);
        $t->same('2026-06-06T15:53:02Z', $metadata['created_at_utc']);
        $t->same('2026-06-06T15:53:02Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same('Indirect Filter Operand Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Valid Indirect Filter Body', $plainText);
        $t->same(false, isset($metadata['catalog']['metadata_stream_review']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'indirect metadata filter operand tail'));
        $t->true(!str_contains($plainText, 'Valid Indirect Filter XMP Title'));
    },
];

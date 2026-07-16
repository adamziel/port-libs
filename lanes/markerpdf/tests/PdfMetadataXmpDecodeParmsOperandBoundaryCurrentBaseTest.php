<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpDecodeParmsOperandPacket = static function (
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
        . '<dc:creator><rdf:Seq><rdf:li>DecodeParms Operand Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-decodeparms-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>DecodeParms Operand Producer</pdf:Producer>'
        . '<xmp:CreatorTool>DecodeParms Operand Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T16:31:24Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpDecodeParmsOperandPdf = static function (
    string $xmp,
    string $decodeParmsHelperBody,
    string $bodyText
): string {
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress XMP DecodeParms operand fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /DecodeParms 7 0 R /Length " . strlen($compressedXmp) . " >>\n"
        . "stream\n{$compressedXmp}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (DecodeParms Operand Info Title) /Author (Info DecodeParms Author) /Producer (Info DecodeParms Producer) >>\nendobj\n"
        . "7 0 obj\n{$decodeParmsHelperBody}\nendobj\n"
        . "8 0 obj\n<< /S /JavaScript /JS (app.alert\\('indirect metadata decodeparms operand tail'\\)) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'rejects document XMP streams with indirect DecodeParms helpers that carry extra operands' => static function (
        TestRunner $t
    ) use ($xmpDecodeParmsOperandPacket, $xmpDecodeParmsOperandPdf): void {
        $xmp = $xmpDecodeParmsOperandPacket(
            'Malformed DecodeParms XMP Title',
            'An indirect DecodeParms helper with extra operands must not define document metadata.',
            '2026-06-06T12:31:24-04:00'
        );
        $pdf = $xmpDecodeParmsOperandPdf(
            $xmp,
            "<< /Predictor 1 /Columns 1 >> /Crypt 8 0 R\n",
            'XMP DecodeParms Operand Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $decodeParmsOperand = $review['decodeparms_operands'][0] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('DecodeParms Operand Info Title', $metadata['title']);
        $t->same(['Info DecodeParms Author'], $metadata['authors']);
        $t->same('XMP DecodeParms Operand Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_malformed_metadata_stream_decodeparms_operand', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(1, $review['invalid_decodeparms_operand_count'] ?? null);
        $t->same(1, $review['malformed_decodeparms_operand_count'] ?? null);
        $t->same(0, $review['non_dictionary_decodeparms_operand_count'] ?? null);
        $t->same(0, $review['unresolved_decodeparms_operand_count'] ?? null);
        $t->same('reject_malformed_decodeparms_operands', $review['decodeparms_operand_policy'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(1, is_countable($review['decodeparms_operands'] ?? null) ? count($review['decodeparms_operands']) : null);
        $t->same('DecodeParms', $decodeParmsOperand['name'] ?? null);
        $t->same('indirect', $decodeParmsOperand['kind'] ?? null);
        $t->same(7, $decodeParmsOperand['object_number'] ?? null);
        $t->same(0, $decodeParmsOperand['generation'] ?? null);
        $t->same(true, $decodeParmsOperand['resolved'] ?? null);
        $t->same('<< /Predictor 1 /Columns 1 >> /Crypt 8 0 R', $decodeParmsOperand['value_preview'] ?? null);
        $t->same('dictionary', $decodeParmsOperand['token_type'] ?? null);
        $t->same(false, $decodeParmsOperand['valid_decodeparms_operand'] ?? null);
        $t->same(true, $decodeParmsOperand['dictionary_decodeparms_operand'] ?? null);
        $t->same(true, $decodeParmsOperand['extra_decodeparms_operand'] ?? null);
        $t->same('name', $decodeParmsOperand['extra_decodeparms_operand_type'] ?? null);
        $t->same('/Crypt', $decodeParmsOperand['extra_decodeparms_operand_preview'] ?? null);
        $t->same(true, $decodeParmsOperand['extra_decodeparms_name_operand'] ?? null);
        $t->same('Crypt', $decodeParmsOperand['extra_decodeparms_name'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Malformed DecodeParms XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'indirect metadata decodeparms operand tail'));
        $t->true(!str_contains($plainText, 'Malformed DecodeParms XMP Title'));
        $t->true(!str_contains($plainText, 'indirect metadata decodeparms operand tail'));
    },
    'accepts document XMP streams with single-dictionary indirect DecodeParms helpers' => static function (
        TestRunner $t
    ) use ($xmpDecodeParmsOperandPacket, $xmpDecodeParmsOperandPdf): void {
        $xmp = $xmpDecodeParmsOperandPacket(
            'Valid DecodeParms XMP Title',
            'A single-dictionary indirect DecodeParms helper remains valid metadata stream parameters.',
            '2026-06-06T16:31:24Z'
        );
        $pdf = $xmpDecodeParmsOperandPdf(
            $xmp,
            "<< /Predictor 1 /Columns 1 >>\n",
            'XMP Valid DecodeParms Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Valid DecodeParms XMP Title', $metadata['title']);
        $t->same('A single-dictionary indirect DecodeParms helper remains valid metadata stream parameters.', $metadata['description']);
        $t->same(['DecodeParms Operand Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-decodeparms-boundary'], $metadata['keywords']);
        $t->same('DecodeParms Operand Tool', $metadata['creator_tool']);
        $t->same('DecodeParms Operand Producer', $metadata['producer']);
        $t->same('2026-06-06T16:31:24Z', $metadata['created_at']);
        $t->same('2026-06-06T16:31:24Z', $metadata['created_at_utc']);
        $t->same('2026-06-06T16:31:24Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same('DecodeParms Operand Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Valid DecodeParms Body', $plainText);
        $t->same(false, isset($metadata['catalog']['metadata_stream_review']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'indirect metadata decodeparms operand tail'));
        $t->true(!str_contains($plainText, 'Valid DecodeParms XMP Title'));
    },
];

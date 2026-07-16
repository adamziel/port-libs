<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserStreamFilterPredictorRangeBoundaryCurrentBaseAscii85 = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($value === 0 && $chunkLength === 4) {
            $encoded .= 'z';
            continue;
        }

        $chars = '';
        for ($index = 0; $index < 5; $index++) {
            $chars = chr(($value % 85) + 33) . $chars;
            $value = intdiv($value, 85);
        }

        $encoded .= substr($chars, 0, $chunkLength + 1);
    }

    return $encoded;
};

$parserStreamFilterPredictorRangeBoundaryCurrentBaseCMapPdf = static function (): string {
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
        . "/CMapName /UnsupportedPredictorRange def\n"
        . "/CMapType 2 def\n"
        . "1 begincodespacerange\n"
        . "<01> <01>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<01> <0058>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end end\n";
    $compressed = gzcompress($cMap);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress focused CMap predictor range stream.');
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /CMap /CMapName /UnsupportedPredictorRange /Filter /FlateDecode /DecodeParms << /Predictor 16 /Columns 1 >> /Length " . strlen($compressed) . " >>\n"
        . "stream\n{$compressed}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserStreamFilterPredictorRangeBoundaryCurrentBaseStackPdf = static function () use (
    $parserStreamFilterPredictorRangeBoundaryCurrentBaseAscii85
): string {
    $badContent = 'BT /F1 12 Tf 72 720 Td (Unsupported Predictor Range Leak) Tj ET';
    $badCompressed = gzcompress($badContent);
    if (!is_string($badCompressed)) {
        throw new RuntimeException('Unable to compress focused page predictor range stream.');
    }
    $badStack = $parserStreamFilterPredictorRangeBoundaryCurrentBaseAscii85($badCompressed) . '~>';

    $visibleAfter = 'BT /F1 12 Tf 72 700 Td (Visible After Unsupported Predictor Range) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ /A85 /Fl ] /DecodeParms [ null << /Predictor 16 /Columns 1 >> ] /Length " . strlen($badStack) . " >>\nstream\n{$badStack}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'classifies unsupported Flate predictor values as malformed DecodeParms before CMap decoding' => static function (TestRunner $t) use ($parserStreamFilterPredictorRangeBoundaryCurrentBaseCMapPdf): void {
        $review = (new PdfTextExtractor())->extractCMapStreamFilterLengthOwnerReview($parserStreamFilterPredictorRangeBoundaryCurrentBaseCMapPdf());
        $entry = $review['entries'][0] ?? [];

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source'] ?? null);
        $t->same(true, $review['review_only'] ?? null);
        $t->same(1, $review['cmap_stream_count'] ?? null);
        $t->same(0, $review['unsupported_filter_count'] ?? null);
        $t->same(1, $review['invalid_decodeparms_parameter_count'] ?? null);
        $t->same(0, $review['filter_decode_error_count'] ?? null);
        $t->same(0, $review['decoded_cmap_count'] ?? null);

        $t->same(1, $entry['object_number'] ?? null);
        $t->same('UnsupportedPredictorRange', $entry['cmap_name'] ?? null);
        $t->same(['FlateDecode'], $entry['filters'] ?? null);
        $t->same(1, $entry['invalid_decodeparms_parameter_count'] ?? null);
        $t->same(0, $entry['filter_decode_error_count'] ?? null);
        $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
        $t->same('filter_decode_not_reached', $entry['filter_decode_policy'] ?? null);
        $t->same('reject_malformed_decodeparms_parameters', $entry['decodeparms_operand_policy'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(null, $entry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
    },
    'rejects unsupported predictor values in stacked text streams before WordPress text import' => static function (TestRunner $t) use ($parserStreamFilterPredictorRangeBoundaryCurrentBaseStackPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterPredictorRangeBoundaryCurrentBaseStackPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Visible After Unsupported Predictor Range'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same('Visible After Unsupported Predictor Range', $text);
        $t->same("Visible After Unsupported Predictor Range\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Unsupported Predictor Range Leak'));
        $t->true(!str_contains($text, 'DecodeParms'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));
    },
];

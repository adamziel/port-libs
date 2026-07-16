<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$fontWidthExtGStateFontBoundaryCurrentBaseCMap = static function (): string {
    $entries = [
        '0001' => 'W',
        '0002' => 'i',
        '0003' => 'd',
        '0004' => 'e',
    ];
    $body = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . count($entries) . " beginbfchar\n";

    foreach ($entries as $sourceHex => $text) {
        $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode focused ExtGState font boundary CMap text.');
        }

        $body .= '<' . strtoupper($sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /FontWidthExtGStateFontBoundaryCurrentBaseCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$fontWidthExtGStateFontBoundaryCurrentBasePdf = static function (
    string $extGStateName,
    string $extGStateValue,
    string $extraObjects = ''
) use ($fontWidthExtGStateFontBoundaryCurrentBaseCMap): string {
    $toUnicode = $fontWidthExtGStateFontBoundaryCurrentBaseCMap();
    $content = 'BT /Fwide 12 Tf /' . $extGStateName . ' gs '
        . '1 0 0 1 72 720 Tm <00010002> Tj '
        . '1 0 0 1 96 720 Tm <00030004> Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fwide 2 0 R /Fnarrow 5 0 R >> /ExtGState << /{$extGStateName} {$extGStateValue} >> >> /Contents 8 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WideExtGStateFont /Encoding /Identity-H /DescendantFonts [3 0 R] /ToUnicode 4 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WideExtGStateFont /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NarrowExtGStateFont /Encoding /Identity-H /DescendantFonts [6 0 R] /ToUnicode 4 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NarrowExtGStateFont /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 250 >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . $extraObjects
        . "%%EOF";
};

$fontWidthExtGStateFontBoundaryCurrentBaseExtract = static function (string $pdf): array {
    $extractor = new PdfTextExtractor();
    $pages = $extractor->extractStyledTextPages($pdf);
    $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
    $spans = $line['spans'] ?? [];

    return [
        'lines' => $extractor->extractTextLines($pdf),
        'runs' => $extractor->extractTextRuns($pdf),
        'plain' => $extractor->extractPlainText($pdf),
        'naive' => $extractor->naiveGetText($pdf),
        'span_texts' => array_column($spans, 'text'),
        'span_bboxes' => array_column($spans, 'bbox'),
        'line_bbox' => $line['bbox'] ?? null,
    ];
};

return [
    'rejects malformed direct ExtGState Font arrays before width advance grouping on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthExtGStateFontBoundaryCurrentBasePdf,
        $fontWidthExtGStateFontBoundaryCurrentBaseExtract
    ): void {
        $pdf = $fontWidthExtGStateFontBoundaryCurrentBasePdf(
            'BadFont',
            '<< /Type /ExtGState /Font [/Fnarrow 12 /Tail] >>'
        );
        $result = $fontWidthExtGStateFontBoundaryCurrentBaseExtract($pdf);

        $t->same(['Wide'], $result['lines']);
        $t->same(['Wi', 'de'], $result['runs']);
        $t->same('Wide', $result['plain']);
        $t->same("Wide\n", $result['naive']);
        $t->same(['Wi', 'de'], $result['span_texts']);
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 48.0, 12.0], $result['line_bbox']);
        $t->true(!str_contains($result['plain'], 'Wi de'));
        $t->true(!str_contains($result['plain'], 'Tail'));
        $t->true(!str_contains($result['plain'], 'Fnarrow'));
    },
    'rejects tailed indirect ExtGState Font array helpers before width advance grouping on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthExtGStateFontBoundaryCurrentBasePdf,
        $fontWidthExtGStateFontBoundaryCurrentBaseExtract
    ): void {
        $pdf = $fontWidthExtGStateFontBoundaryCurrentBasePdf(
            'IndirectBadFont',
            '9 0 R',
            "9 0 obj\n<< /Type /ExtGState /Font 10 0 R >>\nendobj\n"
                . "10 0 obj\n[/Fnarrow 12] /Tail\nendobj\n"
        );
        $result = $fontWidthExtGStateFontBoundaryCurrentBaseExtract($pdf);

        $t->same(['Wide'], $result['lines']);
        $t->same(['Wi', 'de'], $result['runs']);
        $t->same('Wide', $result['plain']);
        $t->same("Wide\n", $result['naive']);
        $t->same(['Wi', 'de'], $result['span_texts']);
        $t->same([[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 48.0, 12.0], $result['line_bbox']);
        $t->true(!str_contains($result['plain'], 'Wi de'));
        $t->true(!str_contains($result['plain'], 'Tail'));
        $t->true(!str_contains($result['plain'], 'Fnarrow'));
    },
    'still applies valid two-item ExtGState Font arrays for current advance grouping on current base' => static function (
        TestRunner $t
    ) use (
        $fontWidthExtGStateFontBoundaryCurrentBasePdf,
        $fontWidthExtGStateFontBoundaryCurrentBaseExtract
    ): void {
        $pdf = $fontWidthExtGStateFontBoundaryCurrentBasePdf(
            'GoodFont',
            '<< /Type /ExtGState /Font [/Fnarrow 12] >>'
        );
        $result = $fontWidthExtGStateFontBoundaryCurrentBaseExtract($pdf);

        $t->same(['Wi de'], $result['lines']);
        $t->same(['Wi', 'de'], $result['runs']);
        $t->same('Wi de', $result['plain']);
        $t->same("Wi de\n", $result['naive']);
        $t->same(['Wi', 'de'], $result['span_texts']);
        $t->same([[0.0, 0.0, 6.0, 12.0], [24.0, 0.0, 30.0, 12.0]], $result['span_bboxes']);
        $t->same([0.0, 0.0, 30.0, 12.0], $result['line_bbox']);
        $t->true($result['span_bboxes'] !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]]);
        $t->true(!str_contains($result['plain'], 'Tail'));
        $t->true(!str_contains($result['plain'], 'GoodFont'));
    },
];

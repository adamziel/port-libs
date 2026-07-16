<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserCMapFilterOwnerStreamLengthCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $currentCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPCurrentCMapOwner-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "2 beginbfchar\n"
        . "<01> <" . $utf16beHex('Current CMap Owner') . ">\n"
        . "<02> <" . $utf16beHex('Length Filter Review') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n"
        . "endstream\nendobj\n"
        . "99 0 obj\n<< /Length 64 >>\nstream\nBT /Fcid 12 Tf 72 620 Td (Fake CMap stream owner leak) Tj ET\nendstream\nendobj\n";
    $currentCompressed = gzcompress($currentCMap, 0);
    if (!is_string($currentCompressed) || !str_contains($currentCompressed, "\nendstream\nendobj\n99 0 obj")) {
        throw new RuntimeException('Unable to build focused current CMap owner fixture.');
    }

    $staleCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<01> <" . $utf16beHex('Stale CMap Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "end\n"
        . "end\n";
    $content = 'BT /Fcid 12 Tf 72 720 Td <01> Tj T* <02> Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset ?? 0,
        $generation,
        $state
    );

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /WPCurrentCMapOwner /Encoding /Identity-H /DescendantFonts [7 0 R] /ToUnicode 6 1 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream");
    $addObject(7, 0, '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPCurrentCMapOwner /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 >>');
    $addObject(20, 0, '/ASCIIHexDecode');
    $addObject(21, 0, '1');
    $addObject(6, 1, "<< /Type /CMap /CMapName /WPCurrentCMapOwner-H /Filter 20 1 R /Length 21 1 R >>\nstream\n{$currentCompressed}\nendstream");
    $addObject(20, 1, '/FlateDecode');
    $addObject(21, 1, (string) strlen($currentCompressed));

    $selected = [
        1 => ['generation' => 0, 'offset' => $offsets['1:0']],
        2 => ['generation' => 0, 'offset' => $offsets['2:0']],
        3 => ['generation' => 0, 'offset' => $offsets['3:0']],
        4 => ['generation' => 0, 'offset' => $offsets['4:0']],
        5 => ['generation' => 0, 'offset' => $offsets['5:0']],
        6 => ['generation' => 1, 'offset' => $offsets['6:1']],
        7 => ['generation' => 0, 'offset' => $offsets['7:0']],
        20 => ['generation' => 1, 'offset' => $offsets['20:1']],
        21 => ['generation' => 1, 'offset' => $offsets['21:1']],
    ];

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 22\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 21; $objectNumber++) {
        if (!isset($selected[$objectNumber])) {
            $pdf .= $xrefRow(0, 65535, 'f');
            continue;
        }

        $pdf .= $xrefRow($selected[$objectNumber]['offset'], $selected[$objectNumber]['generation']);
    }
    $pdf .= "trailer\n<< /Size 22 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'reviews filtered ToUnicode CMap stream Length and Filter owners before current-base text extraction' => static function (TestRunner $t) use ($parserCMapFilterOwnerStreamLengthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserCMapFilterOwnerStreamLengthCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperand = $entry['filter_operands'][0] ?? [];
        $lengthOperand = $entry['length_operand'] ?? [];

        $t->same(['Current CMap Owner', 'Length Filter Review'], $extractor->extractTextLines($pdf));
        $t->same(['Current CMap Owner', 'Length Filter Review'], $extractor->extractTextRuns($pdf));
        $t->same("Current CMap Owner\nLength Filter Review", $text);
        $t->same("Current CMap Owner\nLength Filter Review\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale CMap Leak'));
        $t->true(!str_contains($text, 'Fake CMap stream owner leak'));
        $t->true(!str_contains($text, '99 0 obj'));
        $t->true(!str_contains($text, 'ASCIIHexDecode'));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(1, $review['indirect_filter_count']);
        $t->same(1, $review['indirect_length_count']);
        $t->same(2, $review['xref_selected_operand_count']);
        $t->same(0, $review['unresolved_operand_count']);
        $t->same(1, $review['decoded_cmap_count']);
        $t->same(6, $entry['object_number']);
        $t->same(1, $entry['generation']);
        $t->same('WPCurrentCMapOwner-H', $entry['cmap_name']);
        $t->same(['FlateDecode'], $entry['filters']);
        $t->same('xref_selected_indirect_operands', $entry['owner_policy']);
        $t->true($entry['decoded_with_current_operands']);
        $t->same('to_unicode', $entry['reference_usages'][0]['usage'] ?? null);
        $t->same('xref_selected_direct_object', $filterOperand['owner_policy'] ?? null);
        $t->same(20, $filterOperand['object_number'] ?? null);
        $t->true($filterOperand['xref_selected'] ?? false);
        $t->same('/FlateDecode', $filterOperand['value_preview'] ?? null);
        $t->same('xref_selected_direct_object', $lengthOperand['owner_policy'] ?? null);
        $t->same(21, $lengthOperand['object_number'] ?? null);
        $t->true($lengthOperand['xref_selected'] ?? false);
        $t->same((string) $entry['declared_length'], $lengthOperand['value_preview'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $encryptedPdf = str_replace(
            "trailer\n<< /Size 22 /Root 1 0 R >>",
            "trailer\n<< /Size 22 /Root 1 0 R /Encrypt 8 0 R >>",
            $pdf
        );
        $encrypted = $extractor->extractCMapStreamFilterLengthOwnerReview($encryptedPdf);
        $t->same(true, $encrypted['encrypted']);
        $t->same(0, $encrypted['cmap_stream_count']);
        $t->same([], $encrypted['entries']);
    },
];

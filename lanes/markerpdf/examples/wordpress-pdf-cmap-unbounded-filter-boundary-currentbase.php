<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$utf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$ascii85Encode = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($chunkLength === 4 && $value === 0) {
            $encoded .= 'z';
            continue;
        }

        $digits = '';
        for ($index = 0; $index < 5; $index++) {
            $digits = chr(($value % 85) + 33) . $digits;
            $value = intdiv($value, 85);
        }

        $encoded .= substr($digits, 0, $chunkLength + 1);
    }

    return $encoded . '~>';
};

$buildPdf = static function (bool $stackedInnerAscii85) use ($utf16beHex, $ascii85Encode): array {
    $safeText = $stackedInnerAscii85 ? 'Inner Bounded Safe Import' : 'Bounded EOD Safe Import';
    $leakingText = $stackedInnerAscii85 ? 'Inner Unbounded CMap Leak' : 'Unbounded EOD CMap Leak';
    $safeHex = $utf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMapName = $stackedInnerAscii85 ? 'WPInnerUnboundedEodBoundary-H' : 'WPUnboundedEodBoundary-H';
    $baseFont = $stackedInnerAscii85 ? 'WPInnerUnboundedEodBoundary' : 'WPUnboundedEodBoundary';

    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$sourceCode}> <" . $utf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $trailingDecoy = "\n/CMapName /{$cMapName}TrailingDecoy def\n1 beginbfchar\n<{$sourceCode}> <"
        . $utf16beHex('Trailing Filter CMap Leak') . ">\nendbfchar\n";

    if ($stackedInnerAscii85) {
        $stream = gzcompress($ascii85Encode($cMap) . $trailingDecoy, 0);
        if (!is_string($stream)) {
            throw new RuntimeException('Unable to compress inner ASCII85 CMap filter-boundary fixture.');
        }
        $filter = '[/FlateDecode /ASCII85Decode]';
    } else {
        $stream = strtoupper(bin2hex($cMap)) . '>' . $trailingDecoy;
        $filter = '/ASCIIHexDecode';
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter {$filter} /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $leakingText, $cMapName];
};

$extractor = new PdfTextExtractor();
[$directPdf, $directSafeText, $directLeakingText, $directCMapName] = $buildPdf(false);
[$stackedPdf, $stackedSafeText, $stackedLeakingText, $stackedCMapName] = $buildPdf(true);
$directLines = $extractor->extractTextLines($directPdf);
$stackedLines = $extractor->extractTextLines($stackedPdf);
$directReview = $extractor->extractCMapStreamFilterLengthOwnerReview($directPdf);
$stackedReview = $extractor->extractCMapStreamFilterLengthOwnerReview($stackedPdf);
$directEntry = $directReview['entries'][0] ?? [];
$stackedEntry = $stackedReview['entries'][0] ?? [];
$directProblem = $directEntry['filter_end_marker_problems'][0] ?? [];
$stackedProblem = $stackedEntry['filter_end_marker_problems'][0] ?? [];
$allText = implode("\n", array_merge($directLines, $stackedLines));

$flags = [
    'source' => 'native-pdf-cmap-unbounded-explicit-filter-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'direct_unbounded_asciihex_rejected' => $directLines === [$directSafeText]
        && ($directReview['decoded_cmap_count'] ?? null) === 0
        && (($directProblem['problem'] ?? null) === 'unbounded_explicit_end_marker')
        && (($directProblem['filter'] ?? null) === 'ASCIIHexDecode'),
    'stacked_inner_ascii85_unbounded_rejected' => $stackedLines === [$stackedSafeText]
        && ($stackedReview['decoded_cmap_count'] ?? null) === 0
        && (($stackedProblem['problem'] ?? null) === 'unbounded_explicit_end_marker')
        && (($stackedProblem['filter'] ?? null) === 'ASCII85Decode')
        && (($stackedProblem['filter_index'] ?? null) === 1),
    'direct_filter_end_marker_policy' => $directEntry['filter_end_marker_policy'] ?? null,
    'stacked_filter_end_marker_policy' => $stackedEntry['filter_end_marker_policy'] ?? null,
    'visible_text_excludes_cmap_program' => !str_contains($allText, $directLeakingText)
        && !str_contains($allText, $stackedLeakingText)
        && !str_contains($allText, 'Trailing Filter CMap Leak')
        && !str_contains($allText, $directCMapName)
        && !str_contains($allText, $stackedCMapName)
        && !str_contains($allText, 'beginbfchar'),
    'paragraphs' => array_merge($directLines, $stackedLines),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'direct_filter_end_marker_policy' => true,
    'stacked_filter_end_marker_policy' => true,
    'paragraphs' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected unbounded CMap filter boundary smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-cmap-unbounded-filter-boundary-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($flags['paragraphs'] as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

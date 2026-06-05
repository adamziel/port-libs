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

$ascii85Encode = static function (string $bytes, bool $includeTerminator = true): string {
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

    return $encoded . ($includeTerminator ? '~>' : '');
};

$pdfWithCMapAsciiHexEodBoundary = static function (bool $includeAsciiHexEod) use ($utf16beHex): array {
    $safeText = $includeAsciiHexEod ? 'ASCIIHex EOD CMap Import' : 'Missing EOD Safe Import';
    $mappedText = $includeAsciiHexEod ? 'ASCIIHex EOD CMap Import' : 'Missing EOD CMap Leak';
    $sourceHex = $includeAsciiHexEod ? '0001' : $utf16beHex($safeText);
    $cMapSourceHex = $includeAsciiHexEod ? '0001' : substr($sourceHex, 0, 4);
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WordPressCMapFilterEodBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$cMapSourceHex}> <" . $utf16beHex($mappedText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $encodedCMap = strtoupper(bin2hex($cMap)) . ($includeAsciiHexEod ? '>' : '');
    $content = "BT /Fcid 12 Tf 72 720 Td <{$sourceHex}> Tj ET";

    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WordPressCMapFilterEodBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /WordPressCMapFilterEodBoundary-H /Filter /ASCIIHexDecode /Length " . strlen($encodedCMap) . " >>\nstream\n{$encodedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $mappedText];
};

$pdfWithStackedInnerAscii85EodBoundary = static function (bool $includeAscii85Eod) use ($utf16beHex, $ascii85Encode): array {
    $safeText = $includeAscii85Eod ? 'Inner ASCII85 CMap Import' : 'Inner ASCII85 Safe Import';
    $mappedText = $includeAscii85Eod ? 'Inner ASCII85 CMap Import' : 'Inner ASCII85 CMap Leak';
    $sourceHex = $includeAscii85Eod ? '0001' : $utf16beHex($safeText);
    $cMapSourceHex = $includeAscii85Eod ? '0001' : substr($sourceHex, 0, 4);
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WordPressStackedInnerAscii85Boundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$cMapSourceHex}> <" . $utf16beHex($mappedText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $innerAscii85CMap = $ascii85Encode($cMap, $includeAscii85Eod);
    $encodedCMap = gzcompress($innerAscii85CMap, 0);
    if (!is_string($encodedCMap)) {
        throw new RuntimeException('Unable to compress stacked CMap filter smoke fixture.');
    }
    $content = "BT /Fcid 12 Tf 72 720 Td <{$sourceHex}> Tj ET";

    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WordPressStackedInnerAscii85Boundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /WordPressStackedInnerAscii85Boundary-H /Filter [/FlateDecode /ASCII85Decode] /Length " . strlen($encodedCMap) . " >>\nstream\n{$encodedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $mappedText];
};

$extractor = new PdfTextExtractor();
[$missingEodPdf, $safeText, $leakingText] = $pdfWithCMapAsciiHexEodBoundary(false);
[$validEodPdf, $validMappedText] = $pdfWithCMapAsciiHexEodBoundary(true);
[$stackedMissingEodPdf, $stackedSafeText, $stackedLeakingText] = $pdfWithStackedInnerAscii85EodBoundary(false);
[$stackedValidEodPdf, $stackedValidMappedText] = $pdfWithStackedInnerAscii85EodBoundary(true);
$missingLines = $extractor->extractTextLines($missingEodPdf);
$validLines = $extractor->extractTextLines($validEodPdf);
$stackedMissingLines = $extractor->extractTextLines($stackedMissingEodPdf);
$stackedValidLines = $extractor->extractTextLines($stackedValidEodPdf);
$missingReview = $extractor->extractCMapStreamFilterLengthOwnerReview($missingEodPdf);
$validReview = $extractor->extractCMapStreamFilterLengthOwnerReview($validEodPdf);
$stackedMissingReview = $extractor->extractCMapStreamFilterLengthOwnerReview($stackedMissingEodPdf);
$stackedValidReview = $extractor->extractCMapStreamFilterLengthOwnerReview($stackedValidEodPdf);
$missingEntry = $missingReview['entries'][0] ?? [];
$stackedMissingEntry = $stackedMissingReview['entries'][0] ?? [];

echo '<!-- markerpdf:pdf-cmap-filter-eod-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-cmap-filter-explicit-eod-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'filters' => ['ASCIIHexDecode', ['FlateDecode', 'ASCII85Decode']],
    'missing_eod_cmap_rejected' => $missingLines === [$safeText]
        && !str_contains(implode("\n", $missingLines), $leakingText)
        && ($missingReview['decoded_cmap_count'] ?? null) === 0,
    'missing_eod_problem_count' => $missingReview['filter_end_marker_problem_count'] ?? null,
    'missing_eod_problem' => $missingEntry['filter_end_marker_problems'][0]['problem'] ?? null,
    'valid_eod_cmap_accepted' => $validLines === [$validMappedText]
        && ($validReview['decoded_cmap_count'] ?? null) === 1,
    'valid_eod_problem_count' => $validReview['filter_end_marker_problem_count'] ?? null,
    'stacked_inner_ascii85_missing_eod_rejected' => $stackedMissingLines === [$stackedSafeText]
        && !str_contains(implode("\n", $stackedMissingLines), $stackedLeakingText)
        && ($stackedMissingReview['decoded_cmap_count'] ?? null) === 0
        && (($stackedMissingEntry['filter_end_marker_problems'][0]['filter'] ?? null) === 'ASCII85Decode'),
    'stacked_inner_ascii85_problem_count' => $stackedMissingReview['filter_end_marker_problem_count'] ?? null,
    'stacked_inner_ascii85_problem' => $stackedMissingEntry['filter_end_marker_problems'][0]['problem'] ?? null,
    'stacked_inner_ascii85_filter_index' => $stackedMissingEntry['filter_end_marker_problems'][0]['filter_index'] ?? null,
    'stacked_inner_ascii85_valid_eod_accepted' => $stackedValidLines === [$stackedValidMappedText]
        && ($stackedValidReview['decoded_cmap_count'] ?? null) === 1,
    'visible_text_excludes_cmap_program' => !str_contains(implode("\n", $missingLines), 'beginbfchar')
        && !str_contains(implode("\n", $stackedMissingLines), 'beginbfchar')
        && !str_contains(implode("\n", $missingLines), 'WordPressCMapFilterEodBoundary-H')
        && !str_contains(implode("\n", $stackedMissingLines), 'WordPressStackedInnerAscii85Boundary-H'),
    'paragraphs' => array_merge($missingLines, $validLines, $stackedMissingLines, $stackedValidLines),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_merge($missingLines, $validLines, $stackedMissingLines, $stackedValidLines) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

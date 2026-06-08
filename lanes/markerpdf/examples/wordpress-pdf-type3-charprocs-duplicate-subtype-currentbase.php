<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$duplicateSubtypePagePdf = static function (string $firstSubtype, string $lastSubtype): string {
    $wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (duplicate subtype charproc text leak) Tj ET\n";
    $content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <454647> Tj ET';
    $encoding = '<< /Type /Encoding /Differences [65 /W.wide /i.wide /d.wide /e.wide '
        . '/G.gap /a.gap /p.gap] >>';
    $charProcs = '<< /W.wide 3 0 R /i.wide 3 0 R /d.wide 3 0 R /e.wide 3 0 R '
        . '/G.gap 3 0 R /a.gap 3 0 R /p.gap 3 0 R >>';
    $fallbackWidths = implode(' ', array_fill(0, 7, 250));

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /{$firstSubtype} /Subtype /{$lastSubtype} "
        . "/Name /T3DuplicateSubtype /BaseFont /T3DuplicateSubtype /FontBBox [0 0 1000 700] "
        . "/FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 65 /LastChar 71 /Widths [{$fallbackWidths}] "
        . "/Encoding {$encoding} /CharProcs {$charProcs} >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$duplicateSubtypeFallbackPdf = static function (): string {
    $charProcFallback = 'BT /F1 12 Tf 72 720 Td (CharProcs visible fallback content) Tj ET';
    $standaloneFallback = 'BT /F1 12 Tf 72 700 Td (Standalone fallback content) Tj ET';
    $charProcs = '<< /A 3 0 R /C 3 0 R /D 3 0 R /S 3 0 R /V 3 0 R >>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Subtype /Type1 /BaseFont /Helvetica "
        . "/CharProcs {$charProcs} >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($charProcFallback) . " >>\nstream\n{$charProcFallback}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($standaloneFallback) . " >>\nstream\n{$standaloneFallback}\nendstream\nendobj\n%%EOF";
};

$extractor = new PdfTextExtractor();
$currentType3Lines = $extractor->extractTextLines($duplicateSubtypePagePdf('Type1', 'Type3'));
$currentType3Text = implode("\n", $currentType3Lines);
$currentType1Lines = $extractor->extractTextLines($duplicateSubtypePagePdf('Type3', 'Type1'));
$currentType1Text = implode("\n", $currentType1Lines);
$fallbackLines = $extractor->extractTextLines($duplicateSubtypeFallbackPdf());
$fallbackText = implode("\n", $fallbackLines);

$review = [
    'source' => 'native-pdf-type3-charprocs-duplicate-subtype-boundary',
    'font_boundary_sources' => [
        'stale first /Subtype /Type1 followed by selected /Subtype /Type3',
        'stale first /Subtype /Type3 followed by selected /Subtype /Type1',
        'stream-only fallback for non-Type3 CharProcs content',
    ],
    'last_subtype_type3_charproc_widths_selected' => $currentType3Lines === ['WideGap'],
    'last_subtype_type1_charproc_widths_ignored' => $currentType1Lines === ['Wide Gap'],
    'type1_charprocs_fallback_stream_preserved' => $fallbackLines === [
        'CharProcs visible fallback content',
        'Standalone fallback content',
    ],
    'page_charproc_payload_visible_text_excluded' => !str_contains($currentType3Text . $currentType1Text, 'charproc text leak'),
    'fallback_text' => $fallbackText,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'last_subtype_type3_charproc_widths_selected',
    'last_subtype_type1_charproc_widths_ignored',
    'type1_charprocs_fallback_stream_preserved',
    'page_charproc_payload_visible_text_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs duplicate subtype boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-duplicate-subtype-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_merge($currentType3Lines, $currentType1Lines, $fallbackLines) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

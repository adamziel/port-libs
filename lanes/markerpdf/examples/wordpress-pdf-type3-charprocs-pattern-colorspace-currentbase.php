<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validPatternCharProc = "/Pattern cs /GlyphPattern scn\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (valid pattern color-space charproc text leak) Tj ET\n";
$deviceRgbPatternNameCharProc = "/DeviceRGB cs /GlyphPattern scn\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (DeviceRGB pattern-name charproc text leak) Tj ET\n";
$defaultPatternNameCharProc = "/GlyphPattern scn\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (default color-space pattern-name charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <545556> Tj '
    . '1 0 0 1 109 704 Tm <575859> Tj '
    . 'T* 1 0 0 1 72 688 Tm <616263> Tj '
    . '1 0 0 1 109 688 Tm <646566> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /W.pattern /i.pattern /d.pattern /e.pattern '
    . '/B.pattern /l.pattern /o.pattern /c.pattern /k.pattern '
    . '84 /B.device /a.device /d.device /G.device /a.device /p.device '
    . '97 /R.default /a.default /w.default /G.default /a.default /p.default] >>';
$charProcs = '<< /W.pattern 3 0 R /i.pattern 3 0 R /d.pattern 3 0 R /e.pattern 3 0 R '
    . '/B.pattern 3 0 R /l.pattern 3 0 R /o.pattern 3 0 R /c.pattern 3 0 R /k.pattern 3 0 R '
    . '/B.device 4 0 R /a.device 4 0 R /d.device 4 0 R '
    . '/G.device 4 0 R /p.device 4 0 R '
    . '/R.default 5 0 R /a.default 5 0 R /w.default 5 0 R '
    . '/G.default 5 0 R /p.default 5 0 R >>';
$fallbackWidths = implode(' ', array_fill(0, 38, 250));

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3PatternColorSpaceBoundary /BaseFont /T3PatternColorSpaceBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 102 /Widths [{$fallbackWidths}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R "
    . "/Resources << /Pattern << /GlyphPattern 30 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($validPatternCharProc) . " >>\nstream\n{$validPatternCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($deviceRgbPatternNameCharProc) . " >>\nstream\n{$deviceRgbPatternNameCharProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($defaultPatternNameCharProc) . " >>\nstream\n{$defaultPatternNameCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3PatternColorSpaceBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "30 0 obj\n<< /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 8 8] /XStep 8 /YStep 8 /Length 0 >>\nstream\n\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-pattern-colorspace-boundary',
    'font_width_sources' => [
        'valid Type3 /Pattern color space before pattern-name scn',
        'malformed Type3 /DeviceRGB color space before pattern-name scn',
        'malformed Type3 default color space before pattern-name scn',
    ],
    'valid_pattern_colorspace_width_preserved' => str_contains($plainText, 'WideBlock') && !str_contains($plainText, 'Wide Block'),
    'devicergb_pattern_name_metric_rejected' => str_contains($plainText, 'Bad Gap') && !str_contains($plainText, 'BadGap'),
    'default_colorspace_pattern_name_metric_rejected' => str_contains($plainText, 'Raw Gap') && !str_contains($plainText, 'RawGap'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'charproc text leak'),
    'pattern_resource_name_excluded' => !str_contains($plainText, 'GlyphPattern'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'valid_pattern_colorspace_width_preserved',
    'devicergb_pattern_name_metric_rejected',
    'default_colorspace_pattern_name_metric_rejected',
    'charproc_payload_visible_text_excluded',
    'pattern_resource_name_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs pattern color-space boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-pattern-colorspace-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

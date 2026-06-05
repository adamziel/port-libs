<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validCharProc = "q 1 0 0 1 0 0 cm 0 0 m 1000 0 l h Q\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (valid graphics-state charproc text leak) Tj ET\n";
$unmatchedRestoreCharProc = "Q\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (unmatched restore charproc text leak) Tj ET\n";
$savedStateCharProc = "q 1 0 0 1 0 0 cm\n1000 0 0 0 1000 700 d1\nQ\n"
    . "BT /Fghost 9 Tf (saved-state charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <45464748> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 118 704 Tm <58595A> Tj '
    . 'T* 1 0 0 1 72 688 Tm <61626364> Tj '
    . '1 0 0 1 118 688 Tm <656667> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /G.valid /o.valid /o.valid /d.valid '
    . '/P.valid /a.valid /t.valid /h.valid '
    . '84 /R.restore /e.restore /s.restore /t.restore /G.restore /a.restore /p.restore '
    . '97 /S.save /a.save /v.save /e.save /G.save /a.save /p.save] >>';
$charProcs = '<< /G.valid 3 0 R /o.valid 3 0 R /d.valid 3 0 R '
    . '/P.valid 3 0 R /a.valid 3 0 R /t.valid 3 0 R /h.valid 3 0 R '
    . '/R.restore 4 0 R /e.restore 4 0 R /s.restore 4 0 R /t.restore 4 0 R '
    . '/G.restore 4 0 R /a.restore 4 0 R /p.restore 4 0 R '
    . '/S.save 5 0 R /a.save 5 0 R /v.save 5 0 R /e.save 5 0 R '
    . '/G.save 5 0 R /p.save 5 0 R >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3GraphicsStateBoundary /BaseFont /T3GraphicsStateBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($validCharProc) . " >>\nstream\n{$validCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($unmatchedRestoreCharProc) . " >>\nstream\n{$unmatchedRestoreCharProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($savedStateCharProc) . " >>\nstream\n{$savedStateCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3GraphicsStateBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charprocs-graphics-state-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charprocs-graphics-state-boundary',
    'font_width_sources' => [
        'balanced q/Q Type3 CharProc setup before d0',
        'unmatched Q before d0 rejected',
        'saved graphics-state Type3 CharProc setup before d1',
        'FontDescriptor MissingWidth fallback',
    ],
    'balanced_graphics_state_width_preserved' => str_contains($plainText, 'GoodPath')
        && !str_contains($plainText, 'Good Path'),
    'unmatched_restore_width_rejected' => str_contains($plainText, 'Rest Gap')
        && !str_contains($plainText, 'RestGap'),
    'saved_graphics_state_width_preserved' => str_contains($plainText, 'SaveGap')
        && !str_contains($plainText, 'Save Gap'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'charproc text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

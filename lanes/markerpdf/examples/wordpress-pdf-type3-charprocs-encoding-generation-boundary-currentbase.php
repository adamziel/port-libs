<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide encoding generation charproc text leak) Tj ET\n";
$thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin encoding generation charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
$currentEncoding = '<< /Type /Encoding /Differences [65 /W.current /i.current /d.current /e.current '
    . '/B.current /l.current /o.current /c.current /k.current 84 /T.thin /h.thin /i.thin '
    . '/n.thin /T.thin /e.thin /x.thin /t.thin] >>';
$staleEncoding = '<< /Type /Encoding /Differences [65 /A.stale /B.stale /C.stale /D.stale '
    . '/E.stale /F.stale /G.stale /H.stale /I.stale 84 /T.stale /U.stale /V.stale '
    . '/W.stale /X.stale /Y.stale /Z.stale /bracketleft.stale] >>';
$charProcs = '<< /W.current 3 0 R /i.current 3 0 R /d.current 3 0 R /e.current 3 0 R '
    . '/B.current 3 0 R /l.current 3 0 R /o.current 3 0 R /c.current 3 0 R /k.current 3 0 R '
    . '/T.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R '
    . '/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3EncodingGeneration /BaseFont /T3EncodingGeneration "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding 21 % selected encoding generation split by PDF comment\n 0 R "
    . "/CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3EncodingGeneration /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "21 0 obj\n{$currentEncoding}\nendobj\n"
    . "21 1 obj\n{$staleEncoding}\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charprocs-encoding-generation-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charprocs-encoding-generation-boundary',
    'font_width_sources' => [
        'comment-split Type3 /Encoding dictionary reference',
        'exact-generation Type3 Encoding Differences glyph names',
        'Type3 CharProc d0/d1 width operators',
    ],
    'comment_split_encoding_reference_resolved' => $lines === ['WideBlock', 'Thin Text'],
    'exact_encoding_generation_selected' => str_contains($plainText, 'WideBlock') && str_contains($plainText, 'Thin Text'),
    'stale_encoding_generation_excluded' => !str_contains($plainText, 'ABC') && !str_contains($plainText, 'TUVW'),
    'wide_block_spacing_preserved' => str_contains($plainText, 'WideBlock') && !str_contains($plainText, 'Wide Block'),
    'thin_text_spacing_preserved' => str_contains($plainText, 'Thin Text') && !str_contains($plainText, 'ThinText'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'encoding generation charproc text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

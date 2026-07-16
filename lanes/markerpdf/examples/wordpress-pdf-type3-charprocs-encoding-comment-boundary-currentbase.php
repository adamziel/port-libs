<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide encoding comment charproc text leak) Tj ET\n";
$thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin encoding comment charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
$encoding = "<< /Type /Encoding /Differences [65 % /D.comment /e.comment /c.comment /o.comment /y.comment\n"
    . "/W.comment /i.comment /d.comment /e.comment /B.comment /l.comment /o.comment /c.comment /k.comment "
    . "84 % /S.comment /h.comment /i.comment /f.comment /t.comment\n"
    . "/T.thin /h.thin /i.thin /n.thin /T.thin /e.thin /x.thin /t.thin] >>";
$charProcs = '<< /W.comment 3 0 R /i.comment 3 0 R /d.comment 3 0 R /e.comment 3 0 R '
    . '/B.comment 3 0 R /l.comment 3 0 R /o.comment 3 0 R /c.comment 3 0 R /k.comment 3 0 R '
    . '/T.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R '
    . '/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcEncodingComment /BaseFont /T3CharProcEncodingComment "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3CharProcEncodingComment /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charprocs-encoding-comment-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charprocs-encoding-differences-comment-boundary',
    'font_width_sources' => [
        'Type3 /Encoding /Differences array with PDF comments',
        'Type3 /CharProcs d0 wide glyph stream',
        'Type3 /CharProcs d1 thin glyph stream',
    ],
    'encoding_comment_names_ignored' => $lines === ['WideBlock', 'Thin Text'],
    'wide_block_spacing_preserved' => str_contains($plainText, 'WideBlock') && !str_contains($plainText, 'Wide Block'),
    'thin_text_spacing_preserved' => str_contains($plainText, 'Thin Text') && !str_contains($plainText, 'ThinText'),
    'comment_glyph_names_visible_text_excluded' => !str_contains($plainText, 'D.comment') && !str_contains($plainText, 'S.comment'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'encoding comment charproc text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

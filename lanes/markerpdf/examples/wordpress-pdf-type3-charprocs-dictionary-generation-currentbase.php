<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide dictionary generation text leak) Tj ET\n";
$thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin dictionary generation text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
$currentCharProcs = '<< /W.wide 3 0 R /i.wide 3 0 R /d.wide 3 0 R /e.wide 3 0 R '
    . '/B.wide 3 0 R /l.wide 3 0 R /o.wide 3 0 R /c.wide 3 0 R /k.wide 3 0 R '
    . '/T.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R '
    . '/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>';
$staleCharProcs = '<< /W.wide 4 0 R /i.wide 4 0 R /d.wide 4 0 R /e.wide 4 0 R '
    . '/B.wide 4 0 R /l.wide 4 0 R /o.wide 4 0 R /c.wide 4 0 R /k.wide 4 0 R '
    . '/T.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R '
    . '/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcsDictGeneration /BaseFont /T3CharProcsDictGeneration /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding << /Type /Encoding /Differences [65 /W.wide /i.wide /d.wide /e.wide /B.wide /l.wide /o.wide /c.wide /k.wide 84 /T.thin /h.thin /i.thin /n.thin /T.thin /e.thin /x.thin /t.thin] >> "
    . "/CharProcs 21 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
    . "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "21 0 obj\n{$currentCharProcs}\nendobj\n"
    . "21 1 obj\n{$staleCharProcs}\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charprocs-dictionary-generation-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charprocs-indirect-dictionary-generation-boundary',
    'font_text_sources' => [
        'Type3 /CharProcs 21 0 R current dictionary generation',
        'stale same-number /CharProcs 21 1 R decoy dictionary',
        'CharProc d0/d1 glyph widths',
    ],
    'exact_charprocs_dictionary_generation_resolved' => $lines === ['WideBlock', 'Thin Text'],
    'stale_dictionary_widths_excluded' => !str_contains($plainText, 'Wide Block') && !str_contains($plainText, 'ThinText'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'dictionary generation text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

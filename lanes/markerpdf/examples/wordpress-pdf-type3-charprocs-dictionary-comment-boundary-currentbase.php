<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide dictionary comment charproc text leak) Tj ET\n";
$thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin dictionary comment charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /W.comment /i.comment /d.comment /e.comment '
    . '/B.comment /l.comment /o.comment /c.comment /k.comment 84 /T.thin /h.thin /i.thin '
    . '/n.thin /T.thin /e.thin /x.thin /t.thin] >>';
$commentDecoy = '<< /W.comment 4 0 R /i.comment 4 0 R /d.comment 4 0 R /e.comment 4 0 R '
    . '/B.comment 4 0 R /l.comment 4 0 R /o.comment 4 0 R /c.comment 4 0 R /k.comment 4 0 R '
    . '/T.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R '
    . '/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>';
$currentCharProcs = '<< /W.comment 3 0 R /i.comment 3 0 R /d.comment 3 0 R /e.comment 3 0 R '
    . '/B.comment 3 0 R /l.comment 3 0 R /o.comment 3 0 R /c.comment 3 0 R /k.comment 3 0 R '
    . '/T.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R '
    . '/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>';

$pagePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcsDictionaryComment /BaseFont /T3CharProcsDictionaryComment "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding {$encoding} /CharProcs 21 0 R /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3CharProcsDictionaryComment /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "21 0 obj\n% {$commentDecoy}\n{$currentCharProcs}\nendobj\n%%EOF";

$fallbackWideCharProc = "650 0 d0\nBT /Fghost 9 Tf (WIDE COMMENT PROGRAM LEAK) Tj ET\n";
$fallbackThinCharProc = "250 0 d1\nBT /Fghost 9 Tf (THIN COMMENT PROGRAM LEAK) Tj ET\n";
$visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$fallbackCommentDecoy = '<< /A 4 0 R /B 4 0 R /C 4 0 R /D 4 0 R /V 4 0 R >>';
$fallbackCharProcs = '<< /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R /E 4 0 R '
    . '/V 3 0 R /i 3 0 R /s 3 0 R /b 3 0 R /l 3 0 R /e 3 0 R '
    . '/f 3 0 R /a 3 0 R /c 3 0 R /k 3 0 R /o 3 0 R /n 3 0 R /t 3 0 R >>';

$fallbackPdf = "%PDF-1.4\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3CharProcsDictionaryCommentFallback "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs 21 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($fallbackWideCharProc) . " >>\nstream\n{$fallbackWideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($fallbackThinCharProc) . " >>\nstream\n{$fallbackThinCharProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
    . "21 0 obj\n% {$fallbackCommentDecoy}\n{$fallbackCharProcs}\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pagePdf);
$plainText = implode("\n", $lines);
$fallbackPlainText = $extractor->extractPlainText($fallbackPdf);

$review = [
    'source' => 'native-pdf-type3-charprocs-dictionary-comment-boundary',
    'font_width_sources' => [
        'indirect /CharProcs dictionary object with a leading PDF comment',
        'comment-contained fake glyph map ignored as non-structural text',
        'real Type3 CharProc d0/d1 glyph width streams',
    ],
    'leading_comment_ignored_before_charprocs_dictionary' => $lines === ['WideBlock', 'Thin Text'],
    'comment_decoy_widths_excluded' => str_contains($plainText, 'WideBlock') && !str_contains($plainText, 'Wide Block'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'dictionary comment charproc text leak'),
    'fallback_content_preserved' => $fallbackPlainText === 'Visible fallback content',
    'real_charproc_streams_excluded_from_fallback' => !str_contains($fallbackPlainText, 'COMMENT PROGRAM LEAK'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'leading_comment_ignored_before_charprocs_dictionary',
    'comment_decoy_widths_excluded',
    'charproc_payload_visible_text_excluded',
    'fallback_content_preserved',
    'real_charproc_streams_excluded_from_fallback',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs dictionary comment boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-dictionary-comment-boundary-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

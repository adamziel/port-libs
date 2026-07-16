<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "4 beginbfchar\n"
    . "<43> <0043>\n"
    . "<44> <0044>\n"
    . "<45> <0045>\n"
    . "<46> <0046>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$content = 'BT /Ft3miss 12 Tf '
    . '1 0 0 1 72 720 Tm <4344> Tj '
    . '1 0 0 1 96 720 Tm <4546> Tj '
    . 'T* 1 0 0 1 72 704 Tm <4344> Tj '
    . '1 0 0 1 108 704 Tm <4546> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3miss 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3MissingMatrix /BaseFont /T3MissingMatrix "
    . "/FontBBox [0 0 500 700] /FontMatrix [0.002 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 66 /Widths [500 500] "
    . "/Encoding /WinAnsiEncoding /CharProcs << >> /ToUnicode 6 0 R /FontDescriptor 7 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /FontDescriptor /FontName /T3MissingMatrix /Flags 4 /MissingWidth 500 >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$pages = $extractor->extractStyledTextPages($pdf);
$styledLines = [];
foreach (($pages[0]['blocks'] ?? []) as $block) {
    foreach (($block['lines'] ?? []) as $line) {
        $styledLines[] = $line;
    }
}
$firstBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $styledLines[0]['spans'] ?? []
);
$secondBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $styledLines[1]['spans'] ?? []
);

echo '<!-- markerpdf-type3-missingwidth-fontmatrix-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-type3-missingwidth-fontmatrix-currentbase',
    'source' => 'native-pdf-type3-fontdescriptor-missingwidth-fontmatrix-advance',
    'type3_missingwidth_fontmatrix_join_preserved' => ($lines[0] ?? null) === 'CDEF',
    'type3_missingwidth_real_gap_preserved' => ($lines[1] ?? null) === 'CD EF',
    'raw_missingwidth_false_gap_excluded' => !str_contains($plainText, 'CD EF' . "\n" . 'CD EF'),
    'type3_missingwidth_bboxes_preserved' => $firstBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'real_gap_bbox_preserved' => $secondBboxes === [[0.0, 0.0, 24.0, 12.0], [36.0, 0.0, 60.0, 12.0]],
    'raw_missingwidth_bboxes_excluded' => $firstBboxes !== [[0.0, 0.0, 12.0, 12.0], [24.0, 0.0, 36.0, 12.0]],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

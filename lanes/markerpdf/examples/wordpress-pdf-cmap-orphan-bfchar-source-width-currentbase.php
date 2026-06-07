<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /OrphanBfcharSourceWidth-H def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "3 beginbfchar\n"
    . "<0009>\n"
    . "<0001> <0041>\n"
    . "<0002> <0042>\n"
    . "<0003> <0043>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <00010002> Tj '
    . '1 0 0 1 96 720 Tm <0003> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /Pages /Kids [5 0 R] /Count 1 >>\nendobj\n"
    . "5 0 obj\n<< /Type /Page /Parent 7 0 R /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /OrphanBfcharSourceWidth /Encoding /Identity-H /DescendantFonts [3 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /OrphanBfcharSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1 2 1000 3 3 250] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];

$metadata = [
    'source' => 'native-pdf-cmap-orphan-bfchar-source-width-currentbase',
    'support_component' => 'pdf-text-dictionary-core',
    'font_resource' => 'Fcid',
    'orphan_singleton_bfchar_ignored' => $plainText === 'ABC',
    'later_bfchar_rows_recovered' => $runs === ['AB', 'C'],
    'source_width_spans_applied' => array_column($spans, 'bbox') === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 27.0, 12.0]],
    'control_text_excluded' => !str_contains($plainText, "\0") && !str_contains($plainText, "\u{0001}"),
    'cmap_program_bytes_excluded' => !str_contains($plainText, 'beginbfchar'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

$behaviorOk = $metadata['orphan_singleton_bfchar_ignored']
    && $metadata['later_bfchar_rows_recovered']
    && $metadata['source_width_spans_applied']
    && $metadata['control_text_excluded']
    && $metadata['cmap_program_bytes_excluded']
    && $metadata['executes_python_or_models'] === false
    && $metadata['executes_external_pdf_tools'] === false
    && $lines === ['ABC'];

if ($behaviorOk) {
    echo '<!-- markerpdf:pdf-cmap-orphan-bfchar-source-width-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
    foreach ($lines as $paragraph) {
        echo "<!-- wp:paragraph -->\n";
        echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
        echo "<!-- /wp:paragraph -->\n\n";
    }
    return;
}

throw new RuntimeException('Expected orphan bfchar CMap source-width smoke to import ABC without control text.');

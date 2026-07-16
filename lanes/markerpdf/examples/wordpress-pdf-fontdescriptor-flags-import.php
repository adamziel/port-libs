<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\FontStyleCleaner;
use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Fplain 12 Tf 72 720 Td (Plain ) Tj /Fitalic 12 Tf (italic segment) Tj /Fplain 12 Tf ( bridge ) Tj /Fbold 12 Tf (bold segment) Tj /Fplain 12 Tf ( outro) Tj ET';
$plainFlags = (1 << 1) | (1 << 5);
$italicFlags = (1 << 1) | (1 << 5) | (1 << 6);
$boldFlags = (1 << 5) | (1 << 18);
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fplain 2 0 R /Fitalic 4 0 R /Fbold 6 0 R >> >> /Contents 8 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /SourceSerif /Encoding /WinAnsiEncoding /FontDescriptor 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /FontDescriptor /FontName /SourceSerif /Flags {$plainFlags} /FontWeight 400 >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /SourceSerifItalic /Encoding /WinAnsiEncoding /FontDescriptor << /Type /FontDescriptor /FontName /SourceSerifItalic /Flags {$italicFlags} /ItalicAngle -12 >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /SourceSansForceBold /Encoding /WinAnsiEncoding /FontDescriptor 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /FontDescriptor /FontName /SourceSansForceBold /Flags {$boldFlags} /FontWeight 700 >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$pages = $extractor->extractStyledTextPages($pdf);
foreach ($pages as $pageIndex => $page) {
    $pages[$pageIndex]['blocks'] = (new FontStyleCleaner())->markBoldItalicSpans($page['blocks']);
}

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($pages));
$firstLineSpans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

echo '<!-- markerpdf:pdf-fontdescriptor-flags ' . htmlspecialchars(json_encode([
    'source' => 'marker.pdf.utils font_flags_decomposer + pdftext FontDescriptor flags',
    'font_flags' => array_values(array_filter(array_map(
        static fn (array $span): ?int => isset($span['font_flags']) ? (int) $span['font_flags'] : null,
        $firstLineSpans
    ), static fn (?int $flags): bool => $flags !== null)),
    'fonts' => array_map(static fn (array $span): string => (string) ($span['font'] ?? ''), $firstLineSpans),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($blocks as $block) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

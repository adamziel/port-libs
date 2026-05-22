<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkScorer;
use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-wrapped-content.pdf');
if ($fixture === false) {
    throw new RuntimeException('Unable to read markerpdf wrapped WordPress fixture.');
}

$lines = (new PdfTextExtractor())->extractTextLines($fixture);
array_shift($lines);

$markdown = (new MarkdownPostProcessor())->mergeLines($lines);
$reference = 'Clean hyphenated paragraphs keep WordPress imports readable.';
$score = (new BenchmarkScorer())->scoreText($markdown, $reference);

echo json_encode([
    'scenario' => 'wordpress-pdf-import-quality',
    'score' => $score,
    'passes_marker_ci_style_threshold' => $score > 0.40,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

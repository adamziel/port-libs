<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkScorer;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tableToHtml = static function (string $markdownTable): string {
    $rows = array_values(array_filter(
        preg_split('/\R/', trim($markdownTable)) ?: [],
        static fn (string $row): bool => trim($row, " \t|") !== ''
            && !preg_match('/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/', $row)
    ));

    $htmlRows = [];
    foreach ($rows as $row) {
        $cells = array_map(
            static fn (string $cell): string => htmlspecialchars(
                trim(str_replace(['\\-', '\\|'], ['-', '|'], $cell)),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ),
            explode('|', trim($row, " \t|"))
        );
        $htmlRows[] = '<tr><td>' . implode('</td><td>', $cells) . '</td></tr>';
    }

    return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><tbody>"
        . implode('', $htmlRows)
        . "</tbody></table></figure>\n<!-- /wp:table -->";
};

$fixture = require dirname(__DIR__) . '/fixtures/upstream-switch-transformers-toc-table-supplied-document.php';
$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-switch-transformers-toc-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% switch transformers toc table supplied example\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        $fixture['pdftextPages'],
        $fixture['options'],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );

    preg_match('/\| 1\s+\|.*?(?=\n\n|\z)/s', $converted['text'], $match);
    $tocTableMarkdown = $match[0] ?? '';
    $score = (new BenchmarkScorer())->scoreText(
        $converted['text'],
        $fixture['markerExcerpt'],
        $fixture['chunkLength']
    );

    echo json_encode([
        'scenario' => 'wordpress-markerpdf-switch-transformers-toc-table-import',
        'purpose' => 'Import the upstream switch_trans.pdf Contents table slice into a Gutenberg table preview without running Python, pypdfium, Surya, or tabled.',
        'document' => $fixture['document'],
        'source' => [
            'pdf_path' => $fixture['pdfPath'],
            'pdf_sha256' => $fixture['pdfSha256'],
            'marker_path' => $fixture['markerPath'],
            'reference_path' => $fixture['referencePath'],
            'reference_kind' => $fixture['referenceKind'],
        ],
        'metadata' => [
            'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
            'table_counts' => $converted['metadata']['table_plan']['table_counts'] ?? [],
            'inserted_tables' => $converted['metadata']['inserted_tables'] ?? 0,
            'computed_toc' => $converted['metadata']['computed_toc'] ?? [],
        ],
        'score' => $score,
        'threshold' => $fixture['scoreThreshold'],
        'passes_wordpress_gate' => $score > $fixture['scoreThreshold'],
        'blockPreview' => [
            [
                'blockName' => 'core/heading',
                'attrs' => ['level' => 2],
                'innerHTML' => '<h2>Contents</h2>',
            ],
            [
                'blockName' => 'core/table',
                'innerHTML' => $tableToHtml($tocTableMarkdown),
            ],
        ],
        'markdown' => $converted['text'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    unlink($pdfPath);
}

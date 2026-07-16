<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkScorer;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = require dirname(__DIR__) . '/fixtures/upstream-switch-transformers-supplied-document.php';
$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-switch-transformers-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% switch transformers supplied benchmark example\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        $fixture['pdftextPages'],
        $fixture['options'],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );

    $score = (new BenchmarkScorer())->scoreText(
        $converted['text'],
        $fixture['referenceExcerpt'],
        $fixture['chunkLength']
    );

    echo json_encode([
        'scenario' => 'wordpress-markerpdf-switch-transformers-supplied-benchmark',
        'purpose' => 'Import a fuller upstream switch_trans.pdf supplied-dictionary excerpt into block-ready Markdown and score it before editorial review.',
        'document' => $fixture['document'],
        'source' => [
            'pdf_path' => $fixture['pdfPath'],
            'pdf_sha256' => $fixture['pdfSha256'],
            'marker_path' => $fixture['markerPath'],
            'reference_path' => $fixture['referencePath'],
            'reference_kind' => $fixture['referenceKind'],
        ],
        'blockPreview' => [
            [
                'blockName' => 'core/heading',
                'attrs' => ['level' => 1],
                'innerHTML' => '<h1>Switch Transformers: Scaling To Trillion Parameter Models With Simple And Efficient Sparsity</h1>',
            ],
            [
                'blockName' => 'core/paragraph',
                'innerHTML' => '<p>Mixture of Experts (MoE) models defy this and instead select <em>different</em> parameters...</p>',
            ],
        ],
        'metadata' => [
            'languages' => $converted['metadata']['languages'] ?? [],
            'page_range' => $converted['metadata']['page_range'] ?? [],
            'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
            'ocr_stats' => $converted['metadata']['ocr_stats'] ?? [],
            'computed_toc_count' => count($converted['metadata']['computed_toc'] ?? []),
        ],
        'score' => $score,
        'threshold' => $fixture['scoreThreshold'],
        'passes_wordpress_gate' => $score > $fixture['scoreThreshold'],
        'markdown' => $converted['text'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    unlink($pdfPath);
}

<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/upstream-formula-supplied-document.php';
$path = sys_get_temp_dir() . '/markerpdf-wordpress-supplied-equation-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% supplied equation WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        $fixture['pdftextPages'],
        $fixture['options'],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

foreach (preg_split('/\n{2,}/', trim($result['text'])) ?: [] as $block) {
    $block = trim($block);
    if ($block === '') {
        continue;
    }

    if (str_starts_with($block, '# ')) {
        echo "<!-- wp:heading {\"level\":1} -->\n";
        echo '<h1>' . htmlspecialchars(substr($block, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h1>\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    if (str_starts_with($block, '$$') && str_ends_with($block, '$$')) {
        echo "<!-- wp:html -->\n";
        echo '<div class="wp-block-markerpdf-equation">' . htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</div>\n";
        echo "<!-- /wp:html -->\n\n";
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo json_encode([
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'],
    'block_stats' => $result['metadata']['block_stats'],
    'converted_equation' => $result['metadata']['converted_equation_spans'][0]['text'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

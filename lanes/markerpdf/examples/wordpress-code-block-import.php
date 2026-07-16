<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\CodeBlockDetector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$blocks = [
    [
        'type' => 'Text',
        'lines' => [
            ['text' => 'The imported PDF includes a code sample below.', 'left' => 72.0],
        ],
    ],
    [
        'type' => 'Text',
        'lines' => [
            ['text' => '// source: imported PDF sample', 'left' => 72.0, 'right' => 268.0],
            ['text' => '// target: WordPress code block', 'left' => 72.0, 'right' => 275.0],
            ['text' => '// cleaner: marker.cleaners.code', 'left' => 72.0, 'right' => 275.0],
            ['text' => 'function migrate_pdf() {', 'left' => 72.0, 'right' => 184.0],
            ['text' => '// emit a code block', 'left' => 86.0, 'right' => 198.0],
            ['text' => 'return true;', 'left' => 86.0, 'right' => 156.0],
            ['text' => '}', 'left' => 72.0, 'right' => 79.0],
        ],
    ],
];

$detector = new CodeBlockDetector();
$classifiedBlocks = $detector->identifyCodeBlocks($blocks);

foreach ($classifiedBlocks as $block) {
    if ($block['type'] === 'Code') {
        $code = htmlspecialchars($detector->indentBlock($block['lines']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo "<!-- wp:code -->\n";
        echo '<pre class="wp-block-code"><code>' . $code . "</code></pre>\n";
        echo "<!-- /wp:code -->\n\n";
        continue;
    }

    $text = implode(' ', array_map(
        static fn (array|string $line): string => is_string($line) ? $line : (string) ($line['text'] ?? ''),
        $block['lines']
    ));
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

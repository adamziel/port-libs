<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OutputWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$outputRoot = sys_get_temp_dir() . '/markerpdf-wordpress-output';
$writer = new OutputWriter();

$markdown = <<<'MD'
<!-- wp:paragraph -->
<p>Imported PDF media summary.</p>
<!-- /wp:paragraph -->

<!-- wp:image -->
<figure class="wp-block-image"><img src="3_image_0.png" alt="3_image_0.png"/></figure>
<!-- /wp:image -->
MD;

$subfolder = $writer->saveMarkdown(
    $outputRoot,
    'wordpress-media-import.pdf',
    $markdown,
    ['3_image_0.png' => "PNG"],
    [
        'scenario' => 'wordpress-pdf-output-artifact',
        'pages' => 4,
        'images' => ['3_image_0.png'],
        'successful_ocr' => 0,
        'unsuccessful_ocr' => 0,
    ]
);

echo json_encode([
    'scenario' => 'wordpress-pdf-output-artifact',
    'output_folder' => $subfolder,
    'markdown' => $writer->getMarkdownFilepath($outputRoot, 'wordpress-media-import.pdf'),
    'markdown_exists' => $writer->markdownExists($outputRoot, 'wordpress-media-import.pdf'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

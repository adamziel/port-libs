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
<figure class="wp-block-image"><img src="../3 image 0?.jpeg" alt="../3 image 0?.jpeg"/></figure>
<!-- /wp:image -->
MD;

$unsafeImageName = '../3 image 0?.jpeg';
$subfolder = $writer->saveMarkdown(
    $outputRoot,
    'wordpress-media-import.pdf',
    $markdown,
    [$unsafeImageName => "PNG"],
    [
        'scenario' => 'wordpress-pdf-output-artifact',
        'pages' => 4,
        'images' => [$unsafeImageName],
        'successful_ocr' => 0,
        'unsuccessful_ocr' => 0,
    ]
);

$markdownPath = $writer->getMarkdownFilepath($outputRoot, 'wordpress-media-import.pdf');
$markdownOut = (string) file_get_contents($markdownPath);

echo json_encode([
    'scenario' => 'wordpress-pdf-output-artifact',
    'output_folder' => $subfolder,
    'markdown' => $markdownPath,
    'markdown_exists' => $writer->markdownExists($outputRoot, 'wordpress-media-import.pdf'),
    'sanitized_image' => '3_image_0.png',
    'sanitized_image_exists' => is_file($subfolder . DIRECTORY_SEPARATOR . '3_image_0.png'),
    'markdown_rewritten_to_sanitized_image' => str_contains($markdownOut, 'src="3_image_0.png"')
        && str_contains($markdownOut, 'alt="3_image_0.png"'),
    'traversal_image_outside_subfolder_exists' => is_file($outputRoot . DIRECTORY_SEPARATOR . '3 image 0?.jpeg'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

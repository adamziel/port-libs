<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\SingleDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$outputRoot = sys_get_temp_dir() . '/markerpdf-wordpress-single-output-' . bin2hex(random_bytes(4));
$fixture = dirname(__DIR__) . '/fixtures/wordpress-import-content.pdf';

$extractor = new PdfTextExtractor();
$markdown = new MarkdownPostProcessor();
$single = new SingleDocumentConverter();

$result = $single->convert(
    $fixture,
    $outputRoot,
    static function (string $filename, array $options) use ($extractor, $markdown): array {
        $bytes = file_get_contents($filename);
        $text = is_string($bytes) ? $extractor->extractPlainText($bytes) : '';
        $paragraph = $markdown->mergeLines(preg_split('/\R+/', trim($text)) ?: []);

        return [
            'text' => "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->",
            'images' => [],
            'metadata' => [
                'scenario' => 'wordpress-markerpdf-single-convert',
                'source' => basename($filename),
                'max_pages' => $options['max_pages'],
                'start_page' => $options['start_page'],
                'langs' => $options['langs'],
                'batch_multiplier' => $options['batch_multiplier'],
            ],
        ];
    },
    maxPages: 1,
    startPage: 0,
    languages: 'English',
    batchMultiplier: 2
);

echo json_encode([
    'scenario' => 'wordpress-markerpdf-single-convert',
    'purpose' => 'Single uploaded PDF-to-block import with convert_single.py-style option passing and Marker output artifact layout.',
    'status' => $result['status'],
    'markdown' => $result['markdown'],
    'output_folder' => $result['output_folder'],
    'options' => $result['options'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

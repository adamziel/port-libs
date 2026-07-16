<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerServerAdapter;
use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = dirname(__DIR__) . '/fixtures/wordpress-import-content.pdf';
$bytes = file_get_contents($fixture);
if (!is_string($bytes)) {
    throw new RuntimeException('Unable to read markerPDF WordPress upload fixture.');
}

$uploadRoot = sys_get_temp_dir() . '/markerpdf-wordpress-api-upload-' . bin2hex(random_bytes(4));
$adapter = new MarkerServerAdapter();
$extractor = new PdfTextExtractor();
$postProcessor = new MarkdownPostProcessor();

try {
    $response = $adapter->convertPdfFromUpload(
        [
            'filename' => 'editorial-upload.pdf',
            'content_type' => 'application/pdf',
            'bytes' => $bytes,
        ],
        [
            'max_pages' => 1,
            'langs' => 'English',
            'force_ocr' => false,
        ],
        $uploadRoot,
        true,
        static function (string $filepath, array $options) use ($extractor, $postProcessor): array {
            $pdfBytes = file_get_contents($filepath);
            $text = is_string($pdfBytes) ? $extractor->extractPlainText($pdfBytes) : '';
            $paragraph = $postProcessor->mergeLines(preg_split('/\R+/', trim($text)) ?: []);

            return [
                'markdown' => "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->",
                'images' => [],
                'metadata' => [
                    'scenario' => 'wordpress-marker-api-upload',
                    'source' => basename($filepath),
                    'max_pages' => $options['max_pages'],
                    'langs' => $options['langs'],
                    'ocr_all_pages' => $options['ocr_all_pages'],
                ],
            ];
        }
    );

    echo json_encode([
        'scenario' => 'wordpress-marker-api-upload',
        'purpose' => 'FastAPI marker_server.py-style PDF upload validation and local conversion response for a WordPress import endpoint.',
        'success' => $response['success'],
        'markdown' => $response['markdown'] ?? '',
        'images' => array_keys($response['images'] ?? []),
        'metadata' => $response['metadata'] ?? [],
        'upload_removed' => !is_file($uploadRoot . DIRECTORY_SEPARATOR . 'editorial-upload.pdf'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    if (is_dir($uploadRoot)) {
        foreach (scandir($uploadRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $uploadRoot . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($uploadRoot);
    }
}

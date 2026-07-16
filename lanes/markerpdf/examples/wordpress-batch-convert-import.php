<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-wordpress-batch-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-wordpress-batch-output-' . $runId;
@mkdir($input, 0777, true);
@mkdir($output, 0777, true);

$writePdf = static function (string $path, string $text): void {
    $content = 'BT /F1 12 Tf 72 720 Td (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text) . ') Tj ET';
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
    file_put_contents($path, $pdf);
};

$writePdf($input . DIRECTORY_SEPARATOR . 'alpha.pdf', 'Alpha migration packet');
$writePdf($input . DIRECTORY_SEPARATOR . 'beta.pdf', 'Beta editor checklist');
$metadataPath = $output . DIRECTORY_SEPARATOR . 'metadata.json';
file_put_contents($metadataPath, json_encode([
    'alpha.pdf' => ['title' => 'Alpha Data Liberation'],
    'beta.pdf' => ['title' => 'Beta Editorial Checklist'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$extractor = new PdfTextExtractor();
$markdown = new MarkdownPostProcessor();
$batch = new BatchConverter();

$summary = $batch->processFolder(
    $input,
    $output,
    static function (string $filepath, ?array $metadata) use ($extractor, $markdown): array {
        $bytes = file_get_contents($filepath);
        $text = is_string($bytes) ? $extractor->extractPlainText($bytes) : '';
        $title = $metadata['title'] ?? basename($filepath);
        $paragraph = $markdown->mergeLines(preg_split('/\R+/', trim($text)) ?: []);

        return [
            'text' => "<!-- wp:heading -->\n<h2>" . htmlspecialchars((string) $title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n<!-- /wp:heading -->\n\n"
                . "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->",
            'images' => [],
            'metadata' => [
                'scenario' => 'wordpress-markerpdf-batch-convert',
                'source' => basename($filepath),
                'title' => $title,
            ],
        ];
    },
    metadataByFilename: $batch->loadMetadataFile($metadataPath),
    minLength: 5
);

echo json_encode([
    'scenario' => 'wordpress-markerpdf-batch-convert',
    'purpose' => 'Bulk PDF-to-block import with convert.py-style chunk planning, metadata lookup, min-length preflight, and Marker output artifact layout.',
    'converted' => $summary['converted'],
    'skipped' => $summary['skipped'],
    'errors' => $summary['errors'],
    'statuses' => array_column($summary['results'], 'status', 'filename'),
    'output_folder' => $output,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

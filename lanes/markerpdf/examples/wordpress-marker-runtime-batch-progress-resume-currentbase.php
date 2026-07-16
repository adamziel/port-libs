<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\OutputWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-resume-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-resume-output-' . $runId;
@mkdir($input, 0777, true);
@mkdir($output, 0777, true);

$writePdf = static function (string $path, string $text): void {
    $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $content = 'BT /F1 12 Tf 72 720 Td (' . $escaped . ') Tj ET';
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
    file_put_contents($path, $pdf);
};

foreach ([
    'already-imported.pdf' => 'Already imported migration PDF',
    'editor-checklist.pdf' => 'Editor checklist migration PDF',
    'empty-output.pdf' => 'Empty output migration PDF',
] as $filename => $text) {
    $writePdf($input . DIRECTORY_SEPARATOR . $filename, $text);
}

$writer = new OutputWriter();
$writer->saveMarkdown(
    $output,
    'already-imported.pdf',
    "<!-- wp:paragraph -->\n<p>Previously imported PDF block.</p>\n<!-- /wp:paragraph -->",
    [],
    ['title' => 'Previously Imported PDF']
);

$batch = new BatchConverter();
$metadataByFilename = [
    'editor-checklist.pdf' => ['title' => 'Editor Checklist', 'languages' => ['English']],
    'empty-output.pdf' => ['title' => 'Empty Output Review', 'languages' => ['English']],
];

$resumePlan = $batch->batchProgressResumePlan(
    $input,
    $output,
    metadataByFilename: $metadataByFilename,
    minLength: 5
);

if ($resumePlan['progress']['initial_completed'] !== 1 || $resumePlan['resume']['pending_filenames'] !== ['editor-checklist.pdf', 'empty-output.pdf']) {
    throw new RuntimeException('Expected runtime batch resume plan to skip one completed PDF and queue two pending PDFs.');
}
if ($resumePlan['executes_python_or_models'] !== false || $resumePlan['executes_multiprocessing'] !== false) {
    throw new RuntimeException('Runtime batch progress smoke must not execute Python workers or multiprocessing.');
}

$events = [];
$summary = $batch->processFolder(
    $input,
    $output,
    static function (string $filepath, ?array $metadata): array {
        if (basename($filepath) === 'empty-output.pdf') {
            return ['', [], []];
        }

        $title = (string) ($metadata['title'] ?? basename($filepath));

        return [
            'text' => "<!-- wp:heading -->\n<h2>" . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n<!-- /wp:heading -->\n\n"
                . "<!-- wp:paragraph -->\n<p>Resumed MarkerPDF import for " . htmlspecialchars(basename($filepath), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ".</p>\n<!-- /wp:paragraph -->",
            'images' => [],
            'metadata' => [
                'scenario' => 'wordpress-marker-runtime-batch-progress-resume-currentbase',
                'source' => basename($filepath),
                'title' => $title,
            ],
        ];
    },
    metadataByFilename: $metadataByFilename,
    minLength: 5,
    progressCallback: static function (array $event) use (&$events): void {
        $events[] = [
            'filename' => $event['filename'],
            'status' => $event['status'],
            'completed' => $event['completed'],
            'total' => $event['total'],
            'percent_complete' => $event['percent_complete'],
        ];
    }
);

if ($summary['converted'] !== 1 || $summary['skipped'] !== 2 || $summary['errors'] !== 0) {
    throw new RuntimeException('Expected resumed WordPress batch to convert one file, skip existing markdown, and skip empty output.');
}

echo json_encode([
    'scenario' => 'wordpress-marker-runtime-batch-progress-resume-currentbase',
    'purpose' => 'Resume a WordPress MarkerPDF batch import using convert.py-style markdown_exists skips and tqdm progress metadata without launching Python, Torch, pdftext, pypdfium, or model workers.',
    'resume_progress' => $resumePlan['progress'],
    'resume_status_by_filename' => $resumePlan['resume']['status_by_filename'],
    'pending_filenames' => $resumePlan['resume']['pending_filenames'],
    'summary_progress' => $summary['progress'],
    'summary_counts' => [
        'converted' => $summary['converted'],
        'skipped' => $summary['skipped'],
        'errors' => $summary['errors'],
    ],
    'events' => $events,
    'output_folder' => $output,
    'executes_python_or_models' => $resumePlan['executes_python_or_models'],
    'executes_multiprocessing' => $resumePlan['executes_multiprocessing'],
    'executes_external_pdf_tools' => $resumePlan['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\OutputWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-preflight-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-preflight-output-' . $runId;
$blockedOutputRoot = sys_get_temp_dir() . '/markerpdf-runtime-preflight-blocked-output-' . $runId;
$relativeMetadataRoot = sys_get_temp_dir() . '/markerpdf-runtime-preflight-relative-metadata-' . $runId;
@mkdir($input, 0777, true);
@mkdir($output, 0777, true);
@mkdir($blockedOutputRoot, 0777, true);
@mkdir($relativeMetadataRoot, 0777, true);

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            unlink($child);
        }
    }

    rmdir($path);
};

$writePdf = static function (string $path, string $text): void {
    $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $content = 'BT /F1 12 Tf 72 720 Td (' . $escaped . ') Tj ET';
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
    file_put_contents($path, $pdf);
};

$runtimeDirectoryOrder = static function (string $path, bool $filesOnly = false): array {
    $handle = opendir($path);
    if ($handle === false) {
        throw new RuntimeException('Unable to inspect runtime directory order.');
    }

    $entries = [];
    try {
        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if ($filesOnly && !is_file($path . DIRECTORY_SEPARATOR . $entry)) {
                continue;
            }

            $entries[] = $entry;
        }
    } finally {
        closedir($handle);
    }

    return $entries;
};

try {
    $writePdf($input . DIRECTORY_SEPARATOR . 'already-imported.pdf', 'Already imported WordPress PDF');
    $writePdf($input . DIRECTORY_SEPARATOR . 'short-text.pdf', 'Short text PDF');
    $writePdf($input . DIRECTORY_SEPARATOR . 'ready-for-marker.pdf', 'Ready for Marker conversion PDF');
    file_put_contents($input . DIRECTORY_SEPARATOR . 'extension-spoof.pdf', "PK\x03\x04not really a pdf");
    file_put_contents($input . DIRECTORY_SEPARATOR . 'upload-notes.txt', 'WordPress sidecar notes queued by upstream before per-file preflight.');
    mkdir($input . DIRECTORY_SEPARATOR . 'nested.pdf');
    $runtimeEntryOrder = $runtimeDirectoryOrder($input);
    $runtimeFileOrder = $runtimeDirectoryOrder($input, filesOnly: true);

    (new OutputWriter())->saveMarkdown(
        $output,
        'already-imported.pdf',
        '<!-- wp:paragraph --><p>Previously imported.</p><!-- /wp:paragraph -->',
        [],
        ['title' => 'Previously Imported']
    );

    $batch = new BatchConverter();
    $textLength = static fn (string $filepath): int => basename($filepath) === 'short-text.pdf' ? 12 : 180;
    $runtimePlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        maxFiles: 0,
        metadataByFilename: [
            'ready-for-marker.pdf' => ['title' => 'Ready for Marker', 'languages' => ['English']],
            'short-text.pdf' => ['title' => 'Short Text Review'],
        ],
        minLength: 80,
        workers: 8,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );
    $mpsRuntimePlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        minLength: 80,
        workers: 8,
        torchDevice: 'cpu',
        torchDeviceModel: 'mps'
    );
    $spawnCollisionPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        metadataByFilename: [
            'ready-for-marker.pdf' => ['title' => 'Ready for Marker'],
        ],
        minLength: 80,
        workers: 8,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu',
        spawnStartMethodAlreadySet: true
    );
    $negativeMaxPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        maxFiles: -1,
        workers: 8
    );
    $negativeChunkPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        chunkIndex: -2,
        numChunks: 2,
        workers: 8
    );
    $negativeNumChunksPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        chunkIndex: 0,
        numChunks: -2,
        workers: 8
    );
    $zeroWorkerPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 0
    );
    $negativeWorkerPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: -2
    );
    $missingMetadata = $input . DIRECTORY_SEPARATOR . 'missing-metadata.json';
    $blockedOutput = $blockedOutputRoot . DIRECTORY_SEPARATOR . 'marker-output';
    file_put_contents($blockedOutput, 'not a directory');
    $fileValuedInput = $blockedOutputRoot . DIRECTORY_SEPARATOR . 'not-a-folder.pdf';
    file_put_contents($fileValuedInput, '%PDF file-valued input folder boundary');
    $unreadableInput = $blockedOutputRoot . DIRECTORY_SEPARATOR . 'unreadable-input';
    mkdir($unreadableInput);
    file_put_contents($unreadableInput . DIRECTORY_SEPARATOR . 'queued.pdf', "%PDF-1.4\n% unreadable queue\n%%EOF");
    chmod($unreadableInput, 0000);
    $missingInputBoundary = $batch->runtimeMainPreflightErrorBoundary(
        $input . DIRECTORY_SEPARATOR . 'missing-input',
        $blockedOutput,
        metadataFile: $missingMetadata,
        workers: 8,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );
    $fileInputBoundary = $batch->runtimeMainPreflightErrorBoundary(
        $fileValuedInput,
        $blockedOutput,
        metadataFile: $missingMetadata,
        workers: 8,
        torchDevice: 'mps',
        torchDeviceModel: 'cpu'
    );
    $unreadableInputBoundary = $batch->runtimeMainPreflightErrorBoundary(
        $unreadableInput,
        $blockedOutput,
        metadataFile: $missingMetadata,
        workers: 8,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );
    $outputConflictPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $blockedOutput,
        metadataFile: $missingMetadata,
        workers: 8
    );
    $blockedOutputParent = $blockedOutputRoot . DIRECTORY_SEPARATOR . 'blocked-parent';
    $parentBlockedOutput = $blockedOutputParent . DIRECTORY_SEPARATOR . 'marker-output';
    file_put_contents($blockedOutputParent, 'not a directory');
    $outputParentConflictPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $parentBlockedOutput,
        metadataFile: $missingMetadata,
        workers: 8
    );
    $missingMetadataPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        metadataFile: $missingMetadata,
        workers: 8,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );
    $malformedMetadata = $output . DIRECTORY_SEPARATOR . 'malformed-metadata.json';
    file_put_contents($malformedMetadata, '{"ready-for-marker.pdf": {"title": "Ready"');
    $metadataErrorPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        metadataFile: $malformedMetadata,
        workers: 8
    );
    $shapeMetadata = $output . DIRECTORY_SEPARATOR . 'list-metadata.json';
    file_put_contents($shapeMetadata, json_encode([
        ['title' => 'List metadata cannot be keyed by basename'],
    ], JSON_THROW_ON_ERROR));
    $metadataShapePlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        metadataFile: $shapeMetadata,
        workers: 8,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );
    $valueMetadata = $output . DIRECTORY_SEPARATOR . 'metadata-values.json';
    $valueMetadataByFilename = [
        'already-imported.pdf' => ['title' => 'Already Imported'],
        'ready-for-marker.pdf' => 'English',
        'short-text.pdf' => null,
        'upload-notes.txt' => ['English'],
        'extension-spoof.pdf' => 0,
    ];
    $expectedTruthyNonMappingMetadata = [];
    $expectedFalsyNonMappingMetadata = [];
    foreach ($runtimeFileOrder as $filename) {
        if (!array_key_exists($filename, $valueMetadataByFilename)) {
            continue;
        }

        $metadataValue = $valueMetadataByFilename[$filename];
        if (is_array($metadataValue) && array_is_list($metadataValue)) {
            $expectedTruthyNonMappingMetadata[] = $filename;
            continue;
        }
        if (is_array($metadataValue)) {
            continue;
        }
        if ($metadataValue) {
            $expectedTruthyNonMappingMetadata[] = $filename;
        } else {
            $expectedFalsyNonMappingMetadata[] = $filename;
        }
    }
    file_put_contents($valueMetadata, json_encode($valueMetadataByFilename, JSON_THROW_ON_ERROR));
    $metadataValuePlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        metadataFile: $valueMetadata,
        workers: 8,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );
    $relativeInput = $relativeMetadataRoot . DIRECTORY_SEPARATOR . 'uploads';
    $relativeOutput = $relativeMetadataRoot . DIRECTORY_SEPARATOR . 'marker-output';
    $relativeDotDir = $relativeMetadataRoot . DIRECTORY_SEPARATOR . 'runtime';
    mkdir($relativeInput);
    mkdir($relativeOutput);
    mkdir($relativeDotDir);
    $writePdf($relativeInput . DIRECTORY_SEPARATOR . 'alpha.pdf', 'Relative metadata process cwd alpha');
    $writePdf($relativeInput . DIRECTORY_SEPARATOR . 'beta.pdf', 'Relative metadata process cwd beta');
    file_put_contents($relativeMetadataRoot . DIRECTORY_SEPARATOR . 'relative-metadata.json', json_encode([
        'alpha.pdf' => ['title' => 'Process CWD Metadata'],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($relativeInput . DIRECTORY_SEPARATOR . 'relative-metadata.json', json_encode([
        'beta.pdf' => ['title' => 'Input Folder Decoy'],
    ], JSON_THROW_ON_ERROR));
    $relativeFileOrder = $runtimeDirectoryOrder($relativeInput, filesOnly: true);
    $relativeSelectedMetadataFilenames = array_values(array_filter(
        $relativeFileOrder,
        static fn (string $filename): bool => $filename === 'alpha.pdf'
    ));
    $relativeMissingMetadataFilenames = array_values(array_filter(
        $relativeFileOrder,
        static fn (string $filename): bool => $filename !== 'alpha.pdf'
    ));
    file_put_contents($relativeOutput . DIRECTORY_SEPARATOR . 'relative-metadata.json', '{"beta.pdf": {"title": "Output Folder Decoy",}');
    $previousCwd = getcwd();
    if (!is_string($previousCwd)) {
        throw new RuntimeException('Unable to capture current working directory for relative metadata smoke.');
    }
    try {
        if (!chdir($relativeMetadataRoot)) {
            throw new RuntimeException('Unable to enter relative metadata smoke directory.');
        }
        $relativeMetadataPlan = $batch->runtimeMainPreflightPlan(
            $relativeInput,
            $relativeOutput,
            metadataFile: './runtime/../relative-metadata.json',
            workers: 4,
            torchDevice: 'cuda',
            torchDeviceModel: 'cpu'
        );
    } finally {
        chdir($previousCwd);
    }
    $emptyMetadataArgPlan = $batch->runtimeMainArgumentPreflightPlan([
        '--metadata_file=',
        $input,
        $output,
    ]);
    $emptyMetadataRuntimePlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        metadataByFilename: [
            'ready-for-marker.pdf' => ['title' => 'Empty metadata_file fallback'],
        ],
        metadataFile: '',
        workers: 8
    );
    $plans = [];
    foreach (['already-imported.pdf', 'extension-spoof.pdf', 'short-text.pdf', 'ready-for-marker.pdf'] as $filename) {
        $plans[$filename] = $batch->processFilePreflightPlan(
            $input . DIRECTORY_SEPARATOR . $filename,
            $output,
            ['title' => ucfirst(str_replace(['-', '.pdf'], [' ', ''], $filename)), 'languages' => ['English']],
            80,
            $textLength
        );
    }
    $zeroMinLengthSpoof = $batch->processFilePreflightPlan(
        $input . DIRECTORY_SEPARATOR . 'extension-spoof.pdf',
        $output,
        null,
        0,
        $textLength
    );
    $negativeMinLengthSpoof = $batch->processFilePreflightPlan(
        $input . DIRECTORY_SEPARATOR . 'extension-spoof.pdf',
        $output,
        null,
        -1,
        $textLength
    );
    $postConversionConverted = $batch->processFile(
        $input . DIRECTORY_SEPARATOR . 'ready-for-marker.pdf',
        $output,
        ['title' => 'Ready for Marker'],
        null,
        static fn (): array => [
            'text' => '<!-- wp:paragraph --><p>Ready for Marker runtime import.</p><!-- /wp:paragraph -->',
            'images' => [],
            'metadata' => ['title' => 'Ready for Marker'],
        ]
    );
    $postConversionEmpty = $batch->processFile(
        $input . DIRECTORY_SEPARATOR . 'short-text.pdf',
        $output,
        null,
        null,
        static fn (): array => [" \n\t", [], []]
    );
    $postConversionError = $batch->processFile(
        $input . DIRECTORY_SEPARATOR . 'extension-spoof.pdf',
        $output,
        ['languages' => ['English']],
        null,
        static fn (): string => throw new RuntimeException('runtime model boundary unavailable')
    );
    $saveFailurePath = $blockedOutputRoot . DIRECTORY_SEPARATOR . 'save-markdown-failure.pdf';
    file_put_contents($saveFailurePath, "%PDF-1.4\n% save markdown failure\n%%EOF");
    file_put_contents($output . DIRECTORY_SEPARATOR . 'save-markdown-failure', 'subfolder collision');
    $postConversionSaveError = $batch->processFile(
        $saveFailurePath,
        $output,
        ['title' => 'Save Markdown Failure'],
        null,
        static fn (): array => [
            'text' => '<!-- wp:paragraph --><p>Save markdown failure should not persist.</p><!-- /wp:paragraph -->',
            'images' => [],
            'metadata' => ['title' => 'Save Markdown Failure'],
        ]
    );
    $textLengthErrorPath = $blockedOutputRoot . DIRECTORY_SEPARATOR . 'text-length-error.pdf';
    file_put_contents($textLengthErrorPath, "%PDF-1.4\n% text length error\n%%EOF");
    $textLengthErrorPreflight = $batch->processFilePreflightPlan(
        $textLengthErrorPath,
        $output,
        ['title' => 'Text Length Error'],
        80,
        static fn (): int => throw new RuntimeException('runtime text length boundary unavailable')
    );
    $textLengthErrorConverterCalled = false;
    $textLengthErrorResult = $batch->processFile(
        $textLengthErrorPath,
        $output,
        ['title' => 'Text Length Error'],
        80,
        static function () use (&$textLengthErrorConverterCalled): string {
            $textLengthErrorConverterCalled = true;

            return 'should not run';
        },
        static fn (): int => throw new RuntimeException('runtime text length boundary unavailable')
    );
    $capturePreflightError = static function (callable $callback): string {
        try {
            $callback();
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return '';
    };
    $missingInputError = $capturePreflightError(
        static fn (): array => $batch->runtimeMainPreflightPlan($input . DIRECTORY_SEPARATOR . 'missing-input', $output, metadataFile: $missingMetadata)
    );
    $invalidChunkError = $capturePreflightError(
        static fn (): array => $batch->runtimeMainPreflightPlan($input, $output, numChunks: 0, metadataFile: $missingMetadata)
    );
    $zeroNumChunksBoundary = $batch->runtimeMainPreflightErrorBoundary(
        $input,
        $output,
        numChunks: 0,
        metadataFile: $missingMetadata,
        workers: 8,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );

    if ($plans['already-imported.pdf']['status'] !== 'skipped-existing') {
        throw new RuntimeException('Expected existing WordPress import output to skip before filetype checks.');
    }
    if ($plans['extension-spoof.pdf']['status'] !== 'skipped-unsupported-filetype') {
        throw new RuntimeException('Expected extension-spoofed upload to skip before embedded text checks.');
    }
    if ($plans['short-text.pdf']['status'] !== 'skipped-short-text') {
        throw new RuntimeException('Expected short embedded text PDF to skip before converter invocation.');
    }
    if ($plans['ready-for-marker.pdf']['status'] !== 'ready-for-conversion' || $plans['ready-for-marker.pdf']['should_invoke_converter'] !== true) {
        throw new RuntimeException('Expected ready PDF to reach the convert_single_pdf handoff boundary.');
    }
    if (
        $plans['already-imported.pdf']['upstream_return_value'] !== null
        || $plans['short-text.pdf']['upstream_return_value'] !== null
        || $plans['ready-for-marker.pdf']['upstream_return_value'] !== null
    ) {
        throw new RuntimeException('Expected existing, short-text, and conversion branches to preserve upstream Python None return boundaries.');
    }
    if (
        $plans['extension-spoof.pdf']['upstream_return_value'] !== 0
        || $plans['extension-spoof.pdf']['upstream_return_boundary'] !== 'unsupported-filetype-return-zero'
    ) {
        throw new RuntimeException('Expected unsupported filetype branch to preserve upstream return 0 boundary.');
    }
    if ($plans['ready-for-marker.pdf']['executes_python_or_models'] !== false || $plans['ready-for-marker.pdf']['executes_external_pdf_tools'] !== false) {
        throw new RuntimeException('Preflight smoke must not execute Python models or external PDF tools.');
    }
    if ($runtimePlan['worker_pool']['total_processes'] !== 5 || $runtimePlan['worker_pool']['pool_launchable'] !== true) {
        throw new RuntimeException('Expected runtime main preflight to clamp worker count to selected task count.');
    }
    if (
        $runtimePlan['model_handoff']['model_handoff_reached'] !== true
        || $runtimePlan['model_handoff']['main_load_all_models'] !== true
        || $runtimePlan['model_handoff']['share_memory_before_pool'] !== true
        || $runtimePlan['model_handoff']['worker_init_argument'] !== 'model_lst'
        || $runtimePlan['model_handoff']['executes_python_or_models'] !== false
    ) {
        throw new RuntimeException('Expected CUDA/CPU runtime preflight to record parent load_all_models/share_memory handoff without execution.');
    }
    if (
        $mpsRuntimePlan['model_handoff']['uses_mps_no_shared_memory_branch'] !== true
        || $mpsRuntimePlan['model_handoff']['main_load_all_models'] !== false
        || $mpsRuntimePlan['model_handoff']['worker_init_argument'] !== null
        || $mpsRuntimePlan['model_handoff']['worker_loads_models_when_init_arg_null'] !== true
        || !str_contains((string) $mpsRuntimePlan['model_handoff']['warning'], 'Cannot use MPS with torch multiprocessing share_memory')
    ) {
        throw new RuntimeException('Expected MPS runtime preflight to keep model loading in workers and avoid shared-memory handoff.');
    }
    if (
        $spawnCollisionPlan['spawn_start_method']['start_method_success'] !== false
        || $spawnCollisionPlan['spawn_start_method']['error_boundary'] !== 'spawn-start-method-failed'
        || $spawnCollisionPlan['metadata']['metadata_load_success'] !== true
        || $spawnCollisionPlan['model_handoff']['blocked_by'] !== 'spawn-start-method-failed'
        || $spawnCollisionPlan['worker_pool']['task_args_count'] !== 0
        || $spawnCollisionPlan['console_summary']['summary_reached'] !== false
    ) {
        throw new RuntimeException('Expected repeated spawn start-method failures to block before model handoff, task args, summary, and pool launch.');
    }
    if (
        $runtimePlan['console_summary']['summary_reached'] !== true
        || $runtimePlan['console_summary']['message_line'] !== 'Converting 5 pdfs in chunk 1/1 with 5 processes, and storing in ' . $output
        || $runtimePlan['console_summary']['emission_order'] !== 'after_model_handoff_before_task_args'
    ) {
        throw new RuntimeException('Expected convert.py conversion summary stdout to be recorded before task args and pool launch.');
    }
    if (
        $outputConflictPlan['paths']['output_folder_creation_blocked'] !== true
        || $outputConflictPlan['paths']['output_path_type'] !== 'file'
        || $outputConflictPlan['paths']['output_folder_creation_error_class'] !== 'FileExistsError'
        || $outputConflictPlan['metadata']['metadata_load_reached'] !== false
        || $outputConflictPlan['worker_pool']['pool_error_boundary'] !== 'output-folder-create-failed'
        || $outputConflictPlan['console_summary']['summary_reached'] !== false
        || $outputConflictPlan['console_summary']['blocked_by'] !== 'output-folder-create-failed'
    ) {
        throw new RuntimeException('Expected output-folder file conflicts to block before metadata loading, conversion summary, or worker-pool planning.');
    }
    if (
        $outputParentConflictPlan['paths']['output_folder_creation_blocked'] !== true
        || $outputParentConflictPlan['paths']['output_path_type'] !== 'missing'
        || $outputParentConflictPlan['paths']['output_folder_parent_conflict_path'] !== $blockedOutputParent
        || $outputParentConflictPlan['paths']['output_folder_parent_conflict_type'] !== 'file'
        || $outputParentConflictPlan['paths']['output_folder_creation_error_boundary'] !== 'output-folder-parent-not-directory'
        || $outputParentConflictPlan['paths']['output_folder_creation_error_class'] !== 'NotADirectoryError'
        || $outputParentConflictPlan['metadata']['metadata_load_reached'] !== false
        || $outputParentConflictPlan['worker_pool']['task_args_count'] !== 0
        || $outputParentConflictPlan['console_summary']['summary_reached'] !== false
    ) {
        throw new RuntimeException('Expected output-folder parent file conflicts to block at os.makedirs before metadata, task args, summary, or worker launch.');
    }
    if (
        $missingInputBoundary['success'] !== false
        || $missingInputBoundary['error_boundary'] !== 'input-folder-list-failed'
        || $missingInputBoundary['error_class'] !== 'FileNotFoundError'
        || $missingInputBoundary['paths']['output_folder_creation_reached'] !== false
        || $missingInputBoundary['metadata']['metadata_load_reached'] !== false
        || $missingInputBoundary['model_handoff']['model_handoff_reached'] !== false
        || $missingInputBoundary['worker_pool']['task_args_count'] !== 0
        || $missingInputBoundary['console_summary']['summary_reached'] !== false
        || $missingInputBoundary['executes_python_or_models'] !== false
        || $missingInputBoundary['executes_multiprocessing'] !== false
    ) {
        throw new RuntimeException('Expected missing input folder boundary to block before output creation, metadata loading, model handoff, summary, task args, and multiprocessing.');
    }
    if (
        $fileInputBoundary['success'] !== false
        || $fileInputBoundary['error_boundary'] !== 'input-folder-list-failed'
        || $fileInputBoundary['error_class'] !== 'NotADirectoryError'
        || $fileInputBoundary['paths']['input_path_type'] !== 'file'
        || $fileInputBoundary['input_listing']['listing_success'] !== false
        || $fileInputBoundary['paths']['output_folder_creation_reached'] !== false
        || $fileInputBoundary['metadata']['metadata_load_reached'] !== false
        || $fileInputBoundary['executes_python_or_models'] !== false
    ) {
        throw new RuntimeException('Expected file-valued input folder boundary to mirror upstream os.listdir NotADirectoryError before any runtime handoff.');
    }
    if (
        $unreadableInputBoundary['success'] !== false
        || $unreadableInputBoundary['error_boundary'] !== 'input-folder-list-failed'
        || $unreadableInputBoundary['error_class'] !== 'PermissionError'
        || $unreadableInputBoundary['paths']['input_path_type'] !== 'directory'
        || $unreadableInputBoundary['input_listing']['listing_success'] !== false
        || $unreadableInputBoundary['paths']['output_folder_creation_reached'] !== false
        || $unreadableInputBoundary['metadata']['metadata_load_reached'] !== false
        || $unreadableInputBoundary['worker_pool']['task_args_count'] !== 0
        || $unreadableInputBoundary['executes_python_or_models'] !== false
    ) {
        throw new RuntimeException('Expected unreadable input folder boundary to mirror upstream os.listdir PermissionError before any runtime handoff.');
    }
    if (
        $metadataErrorPlan['metadata']['metadata_load_success'] !== false
        || $metadataErrorPlan['metadata']['metadata_error_boundary'] !== 'metadata-file-json-load-failed'
        || $metadataErrorPlan['worker_pool']['pool_error_boundary'] !== 'metadata-file-json-load-failed'
        || $metadataErrorPlan['worker_pool']['task_args_count'] !== 0
        || $metadataErrorPlan['console_summary']['summary_reached'] !== false
        || $metadataErrorPlan['console_summary']['blocked_by'] !== 'metadata-file-json-load-failed'
    ) {
        throw new RuntimeException('Expected malformed metadata JSON to block before model handoff, task tuple construction, conversion summary, or worker-pool launch.');
    }
    if (
        $missingMetadataPlan['chunking']['chunking_reached'] !== true
        || $missingMetadataPlan['metadata']['metadata_load_reached'] !== true
        || $missingMetadataPlan['metadata']['metadata_load_success'] !== false
        || $missingMetadataPlan['metadata']['metadata_error_boundary'] !== 'metadata-file-load-failed'
        || $missingMetadataPlan['metadata']['metadata_error_class'] !== 'FileNotFoundError'
        || !str_contains((string) $missingMetadataPlan['metadata']['metadata_error_message'], 'No such file or directory')
        || $missingMetadataPlan['spawn_start_method']['start_method_reached'] !== false
        || $missingMetadataPlan['model_handoff']['model_handoff_reached'] !== false
        || $missingMetadataPlan['worker_pool']['task_args_count'] !== 0
        || $missingMetadataPlan['worker_pool']['pool_error_boundary'] !== 'metadata-file-load-failed'
        || $missingMetadataPlan['console_summary']['summary_reached'] !== false
    ) {
        throw new RuntimeException('Expected missing metadata files to fail after chunking but before spawn, model handoff, summary, task args, or worker-pool launch.');
    }
    if (
        $metadataShapePlan['metadata']['metadata_load_success'] !== true
        || $metadataShapePlan['metadata']['metadata_json_type'] !== 'list'
        || $metadataShapePlan['metadata']['metadata_get_available'] !== false
        || $metadataShapePlan['metadata']['metadata_shape_error_boundary'] !== 'metadata-get-failed'
        || $metadataShapePlan['console_summary']['summary_reached'] !== true
        || $metadataShapePlan['worker_pool']['task_args_count'] !== 0
        || $metadataShapePlan['worker_pool']['pool_error_boundary'] !== 'metadata-get-failed'
    ) {
        throw new RuntimeException('Expected list-shaped metadata JSON to fail at metadata.get task construction after summary, not at json.load.');
    }
    if (
        $metadataValuePlan['metadata']['metadata_load_success'] !== true
        || $metadataValuePlan['metadata']['metadata_json_type'] !== 'object'
        || $metadataValuePlan['metadata']['metadata_get_available'] !== true
        || $metadataValuePlan['metadata']['metadata_value_review']['truthy_non_mapping_metadata_filenames'] !== $expectedTruthyNonMappingMetadata
        || $metadataValuePlan['metadata']['metadata_value_review']['falsy_non_mapping_metadata_filenames'] !== $expectedFalsyNonMappingMetadata
        || $metadataValuePlan['metadata']['metadata_value_review']['conversion_error_boundary'] !== 'convert-single-pdf-metadata-get-failed'
        || $metadataValuePlan['worker_pool']['pool_launchable'] !== true
        || $metadataValuePlan['worker_pool']['task_args_count'] !== 5
    ) {
        throw new RuntimeException('Expected per-file scalar/list metadata values to pass task tuple construction while recording convert_single_pdf metadata.get risk.');
    }
    if (
        $relativeMetadataPlan['metadata']['metadata_file'] !== $relativeMetadataRoot . DIRECTORY_SEPARATOR . 'relative-metadata.json'
        || $relativeMetadataPlan['metadata']['metadata_file_abspath_base'] !== 'process_cwd'
        || $relativeMetadataPlan['metadata']['metadata_file_relative_to_process_cwd'] !== true
        || $relativeMetadataPlan['metadata']['metadata_file_relative_to_input_folder'] !== false
        || $relativeMetadataPlan['metadata']['metadata_file_relative_to_output_folder'] !== false
        || $relativeMetadataPlan['metadata']['metadata_filenames'] !== ['alpha.pdf']
        || $relativeMetadataPlan['metadata']['selected_metadata_filenames'] !== $relativeSelectedMetadataFilenames
        || $relativeMetadataPlan['metadata']['missing_metadata_filenames'] !== $relativeMissingMetadataFilenames
    ) {
        throw new RuntimeException('Expected relative metadata_file paths to resolve against process cwd after chunking, not input/output folders.');
    }
    if (
        $emptyMetadataArgPlan['arguments']['options']['metadata_file'] !== ''
        || $emptyMetadataArgPlan['semantic_boundaries']['metadata_file_read_deferred_until_after_chunk_files'] !== false
        || $emptyMetadataArgPlan['semantic_boundaries']['metadata_file_truthy_for_json_load'] !== false
        || $emptyMetadataArgPlan['semantic_boundaries']['empty_metadata_file_skips_json_load'] !== true
    ) {
        throw new RuntimeException('Expected empty --metadata_file= to remain an argparse value but skip runtime json.load like upstream Python truthiness.');
    }
    if (
        $emptyMetadataRuntimePlan['metadata']['source'] !== 'metadataByFilename argument'
        || $emptyMetadataRuntimePlan['metadata']['metadata_file'] !== null
        || $emptyMetadataRuntimePlan['metadata']['metadata_file_input'] !== null
        || $emptyMetadataRuntimePlan['metadata']['metadata_filenames'] !== ['ready-for-marker.pdf']
        || $emptyMetadataRuntimePlan['metadata']['selected_metadata_filenames'] !== ['ready-for-marker.pdf']
        || !in_array('upload-notes.txt', $emptyMetadataRuntimePlan['metadata']['missing_metadata_filenames'], true)
    ) {
        throw new RuntimeException('Expected empty metadata_file runtime preflight to use explicit metadata arguments and ignore metadata-file decoys.');
    }
    $relativeTaskArgsByName = [];
    foreach ($relativeMetadataPlan['worker_pool']['task_args'] as $taskArg) {
        $relativeTaskArgsByName[basename($taskArg['filepath'])] = $taskArg;
    }
    if (
        $relativeTaskArgsByName['alpha.pdf']['metadata'] !== ['title' => 'Process CWD Metadata']
        || $relativeTaskArgsByName['beta.pdf']['metadata'] !== null
        || $relativeTaskArgsByName['relative-metadata.json']['metadata'] !== null
    ) {
        throw new RuntimeException('Expected relative metadata task tuples to keep process-cwd metadata keyed by basename after runtime ordering.');
    }
    if ($runtimePlan['chunking']['max_files_limit_active'] !== false || $runtimePlan['chunking']['selected_count'] !== 5) {
        throw new RuntimeException('Expected --max=0 to behave like upstream convert.py and leave the WordPress queue uncapped.');
    }
    if (
        $runtimePlan['input_listing']['entry_basenames'] !== $runtimeEntryOrder
        || $runtimePlan['input_listing']['file_basenames'] !== $runtimeFileOrder
        || $runtimePlan['input_listing']['entry_order_source'] !== 'os.listdir filesystem order'
        || $runtimePlan['input_listing']['sort_applied_before_chunking'] !== false
        || $runtimePlan['input_listing']['preserves_os_listdir_order'] !== true
        || $runtimePlan['chunking']['input_order_source'] !== 'os.listdir filesystem order after os.path.isfile'
        || $runtimePlan['chunking']['sorts_before_chunking'] !== false
        || $runtimePlan['chunking']['preserves_input_listing_order'] !== true
        || $runtimePlan['input_listing']['skipped_non_file_basenames'] !== ['nested.pdf']
        || $runtimePlan['input_listing']['extension_filter_active'] !== false
        || $runtimePlan['input_listing']['selected_non_pdf_filenames'] !== ['upload-notes.txt']
    ) {
        throw new RuntimeException('Expected convert.py runtime preflight to filter only non-files and leave regular non-PDF sidecars as task candidates.');
    }
    $runtimeTaskPreflight = $runtimePlan['worker_pool']['process_single_pdf_preflight'];
    if (
        $runtimeTaskPreflight['review_reached'] !== true
        || $runtimeTaskPreflight['selected_non_pdf_filenames'] !== ['upload-notes.txt']
        || $runtimeTaskPreflight['sidecar_rejected_by_process_single_pdf_filenames'] !== ['upload-notes.txt']
        || $runtimeTaskPreflight['status_by_filename']['upload-notes.txt'] !== 'skipped-unsupported-filetype'
        || $runtimeTaskPreflight['upstream_return_value_by_filename']['upload-notes.txt'] !== 0
        || $runtimeTaskPreflight['upstream_return_boundary_by_filename']['upload-notes.txt'] !== 'unsupported-filetype-return-zero'
    ) {
        throw new RuntimeException('Expected non-PDF sidecars to reach task args and then be rejected by process_single_pdf min_length filetype preflight.');
    }
    if ($negativeMaxPlan['chunking']['max_files_limit_active'] !== true || $negativeMaxPlan['chunking']['selected_count'] !== 4) {
        throw new RuntimeException('Expected negative --max to behave like upstream Python slicing and drop the tail of the queue.');
    }
    if (
        $negativeChunkPlan['chunking']['negative_chunk_index_active'] !== true
        || $negativeChunkPlan['chunking']['python_slice_start_index'] !== 0
        || $negativeChunkPlan['chunking']['python_slice_end_index'] !== 2
        || $negativeChunkPlan['chunking']['selected_filenames'] !== array_slice($runtimeFileOrder, 0, 2)
    ) {
        throw new RuntimeException('Expected negative --chunk_idx to follow upstream Python slice normalization before task tuples are built.');
    }
    if (
        $negativeNumChunksPlan['chunking']['negative_num_chunks_active'] !== true
        || $negativeNumChunksPlan['chunking']['chunk_size'] !== -2
        || $negativeNumChunksPlan['chunking']['start_index'] !== 0
        || $negativeNumChunksPlan['chunking']['end_index'] !== -2
        || $negativeNumChunksPlan['chunking']['python_slice_start_index'] !== 0
        || $negativeNumChunksPlan['chunking']['python_slice_end_index'] !== 3
        || $negativeNumChunksPlan['chunking']['selected_filenames'] !== array_slice($runtimeFileOrder, 0, -2)
        || $negativeNumChunksPlan['worker_pool']['task_args_count'] !== 3
        || $negativeNumChunksPlan['console_summary']['message_line'] !== 'Converting 3 pdfs in chunk 1/-2 with 3 processes, and storing in ' . $output
    ) {
        throw new RuntimeException('Expected negative --num_chunks to use upstream negative chunk-size slicing before metadata, summary, task args, or pool review.');
    }
    if (
        $zeroWorkerPlan['worker_pool']['pool_creation']['pool_creation_success'] !== false
        || $zeroWorkerPlan['worker_pool']['pool_creation']['error_boundary'] !== 'pool-process-count-failed'
        || $zeroWorkerPlan['worker_pool']['pool_creation']['error_class'] !== 'ValueError'
        || $zeroWorkerPlan['worker_pool']['pool_creation']['pool_imap_reached'] !== false
        || $negativeWorkerPlan['worker_pool']['total_processes'] !== -2
        || $negativeWorkerPlan['worker_pool']['pool_creation']['processes'] !== -2
        || $negativeWorkerPlan['worker_pool']['pool_creation']['error_boundary'] !== 'pool-process-count-failed'
    ) {
        throw new RuntimeException('Expected zero or negative worker counts to fail at upstream multiprocessing Pool creation before pool.imap.');
    }
    if (
        $runtimePlan['worker_pool']['pool_cleanup']['cleanup_reached'] !== true
        || $runtimePlan['worker_pool']['pool_cleanup']['worker_handler_terminate_assignment'] !== 'pool._worker_handler.terminate = worker_exit'
        || $runtimePlan['worker_pool']['pool_cleanup']['worker_exit_deletes_global_model_refs'] !== true
        || $runtimePlan['worker_pool']['pool_cleanup']['model_list_delete_reached'] !== true
        || $mpsRuntimePlan['worker_pool']['pool_cleanup']['parent_model_list_loaded'] !== false
        || $zeroWorkerPlan['worker_pool']['pool_cleanup']['blocked_by'] !== 'pool-process-count-failed'
        || $zeroWorkerPlan['worker_pool']['pool_cleanup']['cleanup_reached'] !== false
    ) {
        throw new RuntimeException('Expected convert.py worker cleanup boundary to be recorded after pool.imap and blocked by failed Pool creation.');
    }
    if ($zeroMinLengthSpoof['min_length_gate_active'] !== false || $zeroMinLengthSpoof['filetype_checked'] !== false || $zeroMinLengthSpoof['status'] !== 'ready-for-conversion') {
        throw new RuntimeException('Expected --min_length=0 to leave filetype preflight inactive like upstream convert.py.');
    }
    if ($negativeMinLengthSpoof['min_length_gate_active'] !== true || $negativeMinLengthSpoof['status'] !== 'skipped-unsupported-filetype') {
        throw new RuntimeException('Expected negative --min_length to keep the upstream filetype preflight gate active.');
    }
    if (
        $postConversionConverted['status'] !== 'converted'
        || $postConversionConverted['conversion_result']['save_markdown_writes_markdown'] !== true
        || $postConversionConverted['conversion_result']['upstream_return_boundary'] !== 'saved-markdown-return-none'
    ) {
        throw new RuntimeException('Expected non-empty process_single_pdf output to save Markdown and return Python None.');
    }
    if (
        $postConversionEmpty['status'] !== 'skipped-empty-output'
        || $postConversionEmpty['conversion_result']['stdout_message_line'] !== 'Empty file: ' . $input . DIRECTORY_SEPARATOR . 'short-text.pdf.  Could not convert.'
        || $postConversionEmpty['conversion_result']['save_markdown_reached'] !== false
        || $postConversionEmpty['conversion_result']['upstream_return_boundary'] !== 'empty-output-print-return-none'
    ) {
        throw new RuntimeException('Expected empty process_single_pdf output to print the upstream empty-file message and return None without writing Markdown.');
    }
    if (
        $postConversionError['status'] !== 'error'
        || $postConversionError['conversion_result']['error_boundary'] !== 'conversion-exception-print-return-none'
        || $postConversionError['conversion_result']['save_markdown_reached'] !== false
        || $postConversionError['conversion_result']['upstream_return_boundary'] !== 'conversion-exception-print-return-none'
        || $postConversionError['conversion_result']['traceback_available'] !== true
    ) {
        throw new RuntimeException('Expected converter exceptions to print error review output and return None without writing Markdown.');
    }
    if (
        $postConversionSaveError['status'] !== 'error'
        || $postConversionSaveError['conversion_result']['error_boundary'] !== 'save-markdown-exception-print-return-none'
        || $postConversionSaveError['conversion_result']['conversion_success'] !== true
        || $postConversionSaveError['conversion_result']['save_markdown_reached'] !== true
        || $postConversionSaveError['conversion_result']['save_markdown_writes_markdown'] !== false
        || $postConversionSaveError['conversion_result']['upstream_return_boundary'] !== 'save-markdown-exception-print-return-none'
        || $postConversionSaveError['conversion_result']['traceback_available'] !== true
        || is_file($output . DIRECTORY_SEPARATOR . 'save-markdown-failure' . DIRECTORY_SEPARATOR . 'save-markdown-failure.md')
    ) {
        throw new RuntimeException('Expected save_markdown exceptions after non-empty conversion to print error review output and return None without persisted Markdown.');
    }
    if (
        $textLengthErrorPreflight['status'] !== 'error'
        || $textLengthErrorPreflight['error_stage'] !== 'get_length_of_text'
        || $textLengthErrorPreflight['error_boundary'] !== 'preflight-exception-print-return-none'
        || $textLengthErrorPreflight['upstream_return_boundary'] !== 'preflight-exception-print-return-none'
        || $textLengthErrorResult['status'] !== 'error'
        || $textLengthErrorResult['upstream_return_boundary'] !== 'preflight-exception-print-return-none'
        || $textLengthErrorConverterCalled !== false
        || is_file($output . DIRECTORY_SEPARATOR . 'text-length-error' . DIRECTORY_SEPARATOR . 'text-length-error.md')
    ) {
        throw new RuntimeException('Expected get_length_of_text exceptions to be caught before converter invocation or Markdown writes.');
    }
    if (!str_contains($missingInputError, 'Batch input folder does not exist') || str_contains($missingInputError, 'metadata file')) {
        throw new RuntimeException('Expected missing input folder to be reported before metadata_file loading.');
    }
    if (!str_contains($invalidChunkError, 'Batch chunk count must be at least one') || str_contains($invalidChunkError, 'metadata file')) {
        throw new RuntimeException('Expected invalid chunk count to be reported before metadata_file loading.');
    }
    if (
        $zeroNumChunksBoundary['success'] !== false
        || $zeroNumChunksBoundary['error_boundary'] !== 'chunk-files-failed'
        || $zeroNumChunksBoundary['error_class'] !== 'ZeroDivisionError'
        || $zeroNumChunksBoundary['upstream_error_message'] !== 'division by zero'
        || $zeroNumChunksBoundary['input_listing']['listing_success'] !== true
        || $zeroNumChunksBoundary['paths']['output_folder_creation_reached'] !== true
        || $zeroNumChunksBoundary['paths']['output_folder_creation_blocked'] !== false
        || $zeroNumChunksBoundary['chunking']['chunking_reached'] !== true
        || $zeroNumChunksBoundary['chunking']['chunk_error_boundary'] !== 'chunk-files-failed'
        || $zeroNumChunksBoundary['metadata']['metadata_load_reached'] !== false
        || $zeroNumChunksBoundary['worker_pool']['task_args_count'] !== 0
        || $zeroNumChunksBoundary['console_summary']['summary_reached'] !== false
    ) {
        throw new RuntimeException('Expected zero --num_chunks to fail at convert.py chunk math after input listing and output preflight, before metadata or workers.');
    }
    if ($runtimePlan['executes_python_or_models'] !== false || $runtimePlan['executes_multiprocessing'] !== false) {
        throw new RuntimeException('Runtime main preflight smoke must not launch model workers or multiprocessing.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-preflight-boundary-currentbase',
        'purpose' => 'Review convert.py process_single_pdf preflight decisions for a WordPress PDF import queue before launching Marker model workers.',
        'source' => $plans['ready-for-marker.pdf']['source'],
        'runtime_main_source' => $runtimePlan['source'],
        'min_length' => 80,
        'runtime_main_order' => $runtimePlan['preflight_order'],
        'preflight_order' => $plans['ready-for-marker.pdf']['preflight_order'],
        'runtime_selected_filenames' => $runtimePlan['chunking']['selected_filenames'],
        'runtime_input_entries' => $runtimePlan['input_listing']['entry_basenames'],
        'runtime_file_candidates' => $runtimePlan['input_listing']['file_basenames'],
        'runtime_input_order_source' => $runtimePlan['input_listing']['entry_order_source'],
        'runtime_sort_applied_before_chunking' => $runtimePlan['input_listing']['sort_applied_before_chunking'],
        'runtime_preserves_os_listdir_order' => $runtimePlan['input_listing']['preserves_os_listdir_order'],
        'runtime_chunk_input_order_source' => $runtimePlan['chunking']['input_order_source'],
        'runtime_sorts_before_chunking' => $runtimePlan['chunking']['sorts_before_chunking'],
        'runtime_preserves_input_listing_order' => $runtimePlan['chunking']['preserves_input_listing_order'],
        'runtime_skipped_non_file_entries' => $runtimePlan['input_listing']['skipped_non_file_basenames'],
        'runtime_extension_filter_active' => $runtimePlan['input_listing']['extension_filter_active'],
        'runtime_selected_non_pdf_filenames' => $runtimePlan['input_listing']['selected_non_pdf_filenames'],
        'runtime_task_preflight_reached' => $runtimeTaskPreflight['review_reached'],
        'runtime_task_preflight_order' => $runtimeTaskPreflight['order'],
        'runtime_task_preflight_selected_non_pdf_filenames' => $runtimeTaskPreflight['selected_non_pdf_filenames'],
        'runtime_sidecar_reaches_task_args_before_preflight' => $runtimeTaskPreflight['sidecar_reaches_task_args_before_preflight'],
        'runtime_sidecar_rejected_by_process_single_pdf_filenames' => $runtimeTaskPreflight['sidecar_rejected_by_process_single_pdf_filenames'],
        'runtime_sidecar_rejection_boundary' => $runtimeTaskPreflight['sidecar_rejection_boundary'],
        'runtime_task_preflight_status_by_filename' => $runtimeTaskPreflight['status_by_filename'],
        'runtime_task_preflight_return_boundaries' => $runtimeTaskPreflight['upstream_return_boundary_by_filename'],
        'runtime_metadata_filenames' => $runtimePlan['metadata']['metadata_filenames'],
        'runtime_missing_metadata_filenames' => $runtimePlan['metadata']['missing_metadata_filenames'],
        'runtime_total_processes' => $runtimePlan['worker_pool']['total_processes'],
        'runtime_pool_launchable' => $runtimePlan['worker_pool']['pool_launchable'],
        'runtime_pool_error_boundary' => $runtimePlan['worker_pool']['pool_error_boundary'],
        'runtime_model_handoff_order' => $runtimePlan['model_handoff']['order'],
        'runtime_parent_loads_models' => $runtimePlan['model_handoff']['main_load_all_models'],
        'runtime_parent_share_memory_before_pool' => $runtimePlan['model_handoff']['share_memory_before_pool'],
        'runtime_worker_init_argument' => $runtimePlan['model_handoff']['worker_init_argument'],
        'runtime_model_handoff_executes_python_or_models' => $runtimePlan['model_handoff']['executes_python_or_models'],
        'mps_runtime_uses_worker_model_loading' => $mpsRuntimePlan['model_handoff']['worker_loads_models_when_init_arg_null'],
        'mps_runtime_parent_loads_models' => $mpsRuntimePlan['model_handoff']['main_load_all_models'],
        'mps_runtime_warning_recorded' => str_contains((string) $mpsRuntimePlan['model_handoff']['warning'], 'Cannot use MPS with torch multiprocessing share_memory'),
        'spawn_start_method_reached' => $spawnCollisionPlan['spawn_start_method']['start_method_reached'],
        'spawn_start_method_success' => $spawnCollisionPlan['spawn_start_method']['start_method_success'],
        'spawn_start_method_error_boundary' => $spawnCollisionPlan['spawn_start_method']['error_boundary'],
        'spawn_start_method_blocks_model_handoff' => $spawnCollisionPlan['spawn_start_method']['blocks_model_handoff'],
        'spawn_start_method_blocks_task_args' => $spawnCollisionPlan['spawn_start_method']['blocks_task_args'],
        'spawn_collision_metadata_loaded' => $spawnCollisionPlan['metadata']['metadata_load_success'],
        'spawn_collision_task_args_count' => $spawnCollisionPlan['worker_pool']['task_args_count'],
        'spawn_collision_conversion_summary_reached' => $spawnCollisionPlan['console_summary']['summary_reached'],
        'runtime_conversion_summary_reached' => $runtimePlan['console_summary']['summary_reached'],
        'runtime_conversion_summary_line' => $runtimePlan['console_summary']['message_line'],
        'runtime_conversion_summary_order' => $runtimePlan['console_summary']['emission_order'],
        'runtime_conversion_summary_before_task_args' => $runtimePlan['console_summary']['emitted_before_task_args'],
        'runtime_conversion_summary_before_pool_launch' => $runtimePlan['console_summary']['emitted_before_pool_launch'],
        'output_conflict_creation_blocked' => $outputConflictPlan['paths']['output_folder_creation_blocked'],
        'output_conflict_path_type' => $outputConflictPlan['paths']['output_path_type'],
        'output_conflict_error_class' => $outputConflictPlan['paths']['output_folder_creation_error_class'],
        'output_conflict_metadata_load_reached' => $outputConflictPlan['metadata']['metadata_load_reached'],
        'output_conflict_pool_error_boundary' => $outputConflictPlan['worker_pool']['pool_error_boundary'],
        'output_conflict_conversion_summary_reached' => $outputConflictPlan['console_summary']['summary_reached'],
        'output_conflict_conversion_summary_blocked_by' => $outputConflictPlan['console_summary']['blocked_by'],
        'output_parent_conflict_creation_blocked' => $outputParentConflictPlan['paths']['output_folder_creation_blocked'],
        'output_parent_conflict_path_type' => $outputParentConflictPlan['paths']['output_path_type'],
        'output_parent_conflict_parent_path_type' => $outputParentConflictPlan['paths']['output_folder_parent_conflict_type'],
        'output_parent_conflict_error_boundary' => $outputParentConflictPlan['paths']['output_folder_creation_error_boundary'],
        'output_parent_conflict_error_class' => $outputParentConflictPlan['paths']['output_folder_creation_error_class'],
        'output_parent_conflict_metadata_load_reached' => $outputParentConflictPlan['metadata']['metadata_load_reached'],
        'output_parent_conflict_task_args_count' => $outputParentConflictPlan['worker_pool']['task_args_count'],
        'output_parent_conflict_conversion_summary_reached' => $outputParentConflictPlan['console_summary']['summary_reached'],
        'missing_input_boundary_success' => $missingInputBoundary['success'],
        'missing_input_boundary_error' => $missingInputBoundary['error_boundary'],
        'missing_input_boundary_error_class' => $missingInputBoundary['error_class'],
        'missing_input_boundary_blocks_output_creation' => $missingInputBoundary['paths']['output_folder_creation_reached'] === false,
        'missing_input_boundary_metadata_load_reached' => $missingInputBoundary['metadata']['metadata_load_reached'],
        'missing_input_boundary_task_args_count' => $missingInputBoundary['worker_pool']['task_args_count'],
        'missing_input_boundary_blocked_stages' => $missingInputBoundary['blocked_stages'],
        'file_input_boundary_error_class' => $fileInputBoundary['error_class'],
        'file_input_boundary_path_type' => $fileInputBoundary['paths']['input_path_type'],
        'file_input_boundary_listing_success' => $fileInputBoundary['input_listing']['listing_success'],
        'unreadable_input_boundary_error_class' => $unreadableInputBoundary['error_class'],
        'unreadable_input_boundary_path_type' => $unreadableInputBoundary['paths']['input_path_type'],
        'unreadable_input_boundary_listing_success' => $unreadableInputBoundary['input_listing']['listing_success'],
        'unreadable_input_boundary_blocks_output_creation' => $unreadableInputBoundary['paths']['output_folder_creation_reached'] === false,
        'unreadable_input_boundary_metadata_load_reached' => $unreadableInputBoundary['metadata']['metadata_load_reached'],
        'unreadable_input_boundary_task_args_count' => $unreadableInputBoundary['worker_pool']['task_args_count'],
        'metadata_error_load_reached' => $metadataErrorPlan['metadata']['metadata_load_reached'],
        'metadata_error_load_success' => $metadataErrorPlan['metadata']['metadata_load_success'],
        'metadata_error_boundary' => $metadataErrorPlan['metadata']['metadata_error_boundary'],
        'metadata_error_class' => $metadataErrorPlan['metadata']['metadata_error_class'],
        'metadata_error_selected_filenames' => $metadataErrorPlan['chunking']['selected_filenames'],
        'metadata_error_task_args_count' => $metadataErrorPlan['worker_pool']['task_args_count'],
        'metadata_error_pool_error_boundary' => $metadataErrorPlan['worker_pool']['pool_error_boundary'],
        'metadata_error_conversion_summary_reached' => $metadataErrorPlan['console_summary']['summary_reached'],
        'metadata_error_conversion_summary_blocked_by' => $metadataErrorPlan['console_summary']['blocked_by'],
        'missing_metadata_file_load_reached' => $missingMetadataPlan['metadata']['metadata_load_reached'],
        'missing_metadata_file_error_boundary' => $missingMetadataPlan['metadata']['metadata_error_boundary'],
        'missing_metadata_file_error_class' => $missingMetadataPlan['metadata']['metadata_error_class'],
        'missing_metadata_file_blocks_spawn' => $missingMetadataPlan['spawn_start_method']['start_method_reached'] === false,
        'missing_metadata_file_blocks_model_handoff' => $missingMetadataPlan['model_handoff']['model_handoff_reached'] === false,
        'missing_metadata_file_task_args_count' => $missingMetadataPlan['worker_pool']['task_args_count'],
        'missing_metadata_file_summary_reached' => $missingMetadataPlan['console_summary']['summary_reached'],
        'metadata_shape_json_type' => $metadataShapePlan['metadata']['metadata_json_type'],
        'metadata_shape_load_success' => $metadataShapePlan['metadata']['metadata_load_success'],
        'metadata_shape_get_available' => $metadataShapePlan['metadata']['metadata_get_available'],
        'metadata_shape_error_boundary' => $metadataShapePlan['metadata']['metadata_shape_error_boundary'],
        'metadata_shape_error_class' => $metadataShapePlan['metadata']['metadata_shape_error_class'],
        'metadata_shape_summary_reached' => $metadataShapePlan['console_summary']['summary_reached'],
        'metadata_shape_task_args_count' => $metadataShapePlan['worker_pool']['task_args_count'],
        'metadata_shape_pool_error_boundary' => $metadataShapePlan['worker_pool']['pool_error_boundary'],
        'metadata_value_json_type' => $metadataValuePlan['metadata']['metadata_json_type'],
        'metadata_value_get_available' => $metadataValuePlan['metadata']['metadata_get_available'],
        'metadata_value_types' => $metadataValuePlan['metadata']['metadata_value_types'],
        'metadata_value_truthy_non_mapping_filenames' => $metadataValuePlan['metadata']['metadata_value_review']['truthy_non_mapping_metadata_filenames'],
        'metadata_value_falsy_non_mapping_filenames' => $metadataValuePlan['metadata']['metadata_value_review']['falsy_non_mapping_metadata_filenames'],
        'metadata_value_conversion_error_boundary' => $metadataValuePlan['metadata']['metadata_value_review']['conversion_error_boundary'],
        'metadata_value_blocks_task_args' => $metadataValuePlan['metadata']['metadata_value_review']['blocks_task_args'],
        'metadata_value_blocks_pool_launch' => $metadataValuePlan['metadata']['metadata_value_review']['blocks_pool_launch'],
        'metadata_value_pool_launchable' => $metadataValuePlan['worker_pool']['pool_launchable'],
        'metadata_value_task_args_count' => $metadataValuePlan['worker_pool']['task_args_count'],
        'relative_metadata_file_input' => $relativeMetadataPlan['metadata']['metadata_file_input'],
        'relative_metadata_file' => $relativeMetadataPlan['metadata']['metadata_file'],
        'relative_metadata_abspath_call' => $relativeMetadataPlan['metadata']['metadata_file_abspath_call'],
        'relative_metadata_abspath_order' => $relativeMetadataPlan['metadata']['metadata_file_abspath_order'],
        'relative_metadata_abspath_base' => $relativeMetadataPlan['metadata']['metadata_file_abspath_base'],
        'relative_metadata_process_cwd' => $relativeMetadataPlan['metadata']['metadata_file_process_cwd'],
        'relative_metadata_relative_to_process_cwd' => $relativeMetadataPlan['metadata']['metadata_file_relative_to_process_cwd'],
        'relative_metadata_relative_to_input_folder' => $relativeMetadataPlan['metadata']['metadata_file_relative_to_input_folder'],
        'relative_metadata_relative_to_output_folder' => $relativeMetadataPlan['metadata']['metadata_file_relative_to_output_folder'],
        'relative_metadata_input_folder_candidate_exists' => $relativeMetadataPlan['metadata']['metadata_file_input_folder_candidate_exists'],
        'relative_metadata_output_folder_candidate_exists' => $relativeMetadataPlan['metadata']['metadata_file_output_folder_candidate_exists'],
        'relative_metadata_selected_filenames' => $relativeMetadataPlan['chunking']['selected_filenames'],
        'relative_metadata_selected_metadata_filenames' => $relativeMetadataPlan['metadata']['selected_metadata_filenames'],
        'relative_metadata_missing_metadata_filenames' => $relativeMetadataPlan['metadata']['missing_metadata_filenames'],
        'relative_metadata_loaded_from_process_cwd' => $relativeMetadataPlan['metadata']['metadata_filenames'] === ['alpha.pdf'],
        'relative_metadata_ignored_input_output_decoys' => $relativeMetadataPlan['metadata']['missing_metadata_filenames'] === $relativeMissingMetadataFilenames,
        'empty_metadata_file_arg_value' => $emptyMetadataArgPlan['arguments']['options']['metadata_file'],
        'empty_metadata_file_truthy_for_json_load' => $emptyMetadataArgPlan['semantic_boundaries']['metadata_file_truthy_for_json_load'],
        'empty_metadata_file_skips_json_load' => $emptyMetadataArgPlan['semantic_boundaries']['empty_metadata_file_skips_json_load'],
        'empty_metadata_file_runtime_source' => $emptyMetadataRuntimePlan['metadata']['source'],
        'empty_metadata_file_runtime_metadata_file' => $emptyMetadataRuntimePlan['metadata']['metadata_file'],
        'empty_metadata_file_metadata_filenames' => $emptyMetadataRuntimePlan['metadata']['metadata_filenames'],
        'empty_metadata_file_selected_metadata_filenames' => $emptyMetadataRuntimePlan['metadata']['selected_metadata_filenames'],
        'empty_metadata_file_ignored_file_decoys' => $emptyMetadataRuntimePlan['metadata']['metadata_file'] === null
            && $emptyMetadataRuntimePlan['metadata']['metadata_file_input'] === null,
        'runtime_max_files_limit_active' => $runtimePlan['chunking']['max_files_limit_active'],
        'runtime_zero_max_selected_count' => $runtimePlan['chunking']['selected_count'],
        'negative_max_selected_filenames' => $negativeMaxPlan['chunking']['selected_filenames'],
        'negative_chunk_selected_filenames' => $negativeChunkPlan['chunking']['selected_filenames'],
        'negative_chunk_raw_slice' => [
            $negativeChunkPlan['chunking']['start_index'],
            $negativeChunkPlan['chunking']['end_index'],
        ],
        'negative_chunk_python_slice' => [
            $negativeChunkPlan['chunking']['python_slice_start_index'],
            $negativeChunkPlan['chunking']['python_slice_end_index'],
        ],
        'negative_num_chunks_selected_filenames' => $negativeNumChunksPlan['chunking']['selected_filenames'],
        'negative_num_chunks_raw_slice' => [
            $negativeNumChunksPlan['chunking']['start_index'],
            $negativeNumChunksPlan['chunking']['end_index'],
        ],
        'negative_num_chunks_python_slice' => [
            $negativeNumChunksPlan['chunking']['python_slice_start_index'],
            $negativeNumChunksPlan['chunking']['python_slice_end_index'],
        ],
        'negative_num_chunks_chunk_size' => $negativeNumChunksPlan['chunking']['chunk_size'],
        'negative_num_chunks_summary_line' => $negativeNumChunksPlan['console_summary']['message_line'],
        'negative_num_chunks_task_args_count' => $negativeNumChunksPlan['worker_pool']['task_args_count'],
        'zero_worker_total_processes' => $zeroWorkerPlan['worker_pool']['total_processes'],
        'zero_worker_pool_creation_success' => $zeroWorkerPlan['worker_pool']['pool_creation']['pool_creation_success'],
        'zero_worker_pool_creation_error_boundary' => $zeroWorkerPlan['worker_pool']['pool_creation']['error_boundary'],
        'zero_worker_pool_creation_error_class' => $zeroWorkerPlan['worker_pool']['pool_creation']['error_class'],
        'zero_worker_pool_imap_reached' => $zeroWorkerPlan['worker_pool']['pool_creation']['pool_imap_reached'],
        'negative_worker_total_processes' => $negativeWorkerPlan['worker_pool']['total_processes'],
        'negative_worker_pool_creation_error_boundary' => $negativeWorkerPlan['worker_pool']['pool_creation']['error_boundary'],
        'negative_worker_pool_creation_error_class' => $negativeWorkerPlan['worker_pool']['pool_creation']['error_class'],
        'negative_worker_pool_imap_reached' => $negativeWorkerPlan['worker_pool']['pool_creation']['pool_imap_reached'],
        'runtime_pool_cleanup_reached' => $runtimePlan['worker_pool']['pool_cleanup']['cleanup_reached'],
        'runtime_worker_exit_assignment' => $runtimePlan['worker_pool']['pool_cleanup']['worker_handler_terminate_assignment'],
        'runtime_worker_exit_deletes_model_refs' => $runtimePlan['worker_pool']['pool_cleanup']['worker_exit_deletes_global_model_refs'],
        'runtime_model_list_delete_reached' => $runtimePlan['worker_pool']['pool_cleanup']['model_list_delete_reached'],
        'mps_runtime_cleanup_parent_model_list_loaded' => $mpsRuntimePlan['worker_pool']['pool_cleanup']['parent_model_list_loaded'],
        'zero_worker_cleanup_blocked_by' => $zeroWorkerPlan['worker_pool']['pool_cleanup']['blocked_by'],
        'zero_worker_cleanup_reached' => $zeroWorkerPlan['worker_pool']['pool_cleanup']['cleanup_reached'],
        'runtime_output_folder_creation_required' => $runtimePlan['paths']['output_folder_creation_required'],
        'status_by_filename' => array_map(static fn (array $plan): string => (string) $plan['status'], $plans),
        'skip_reasons' => array_map(static fn (array $plan): ?string => $plan['skip_reason'], $plans),
        'upstream_return_values' => array_map(static fn (array $plan): mixed => $plan['upstream_return_value'], $plans),
        'upstream_return_boundaries' => array_map(static fn (array $plan): string => (string) $plan['upstream_return_boundary'], $plans),
        'unsupported_filetype_returns_zero' => $plans['extension-spoof.pdf']['upstream_return_value'] === 0,
        'non_unsupported_branches_return_none' => $plans['already-imported.pdf']['upstream_return_value'] === null
            && $plans['short-text.pdf']['upstream_return_value'] === null
            && $plans['ready-for-marker.pdf']['upstream_return_value'] === null,
        'zero_min_length_gate_active' => $zeroMinLengthSpoof['min_length_gate_active'],
        'zero_min_length_spoof_status' => $zeroMinLengthSpoof['status'],
        'negative_min_length_gate_active' => $negativeMinLengthSpoof['min_length_gate_active'],
        'negative_min_length_spoof_status' => $negativeMinLengthSpoof['status'],
        'post_conversion_saved_status' => $postConversionConverted['status'],
        'post_conversion_saved_boundary' => $postConversionConverted['conversion_result']['upstream_return_boundary'],
        'post_conversion_saved_writes_markdown' => $postConversionConverted['conversion_result']['save_markdown_writes_markdown'],
        'post_conversion_empty_status' => $postConversionEmpty['status'],
        'post_conversion_empty_stdout' => $postConversionEmpty['conversion_result']['stdout_message_line'],
        'post_conversion_empty_boundary' => $postConversionEmpty['conversion_result']['upstream_return_boundary'],
        'post_conversion_empty_writes_markdown' => $postConversionEmpty['conversion_result']['save_markdown_writes_markdown'],
        'post_conversion_error_status' => $postConversionError['status'],
        'post_conversion_error_boundary' => $postConversionError['conversion_result']['upstream_return_boundary'],
        'post_conversion_error_class' => $postConversionError['conversion_result']['error_class'],
        'post_conversion_error_traceback_available' => $postConversionError['conversion_result']['traceback_available'],
        'post_conversion_save_error_status' => $postConversionSaveError['status'],
        'post_conversion_save_error_boundary' => $postConversionSaveError['conversion_result']['upstream_return_boundary'],
        'post_conversion_save_error_after_conversion' => $postConversionSaveError['conversion_result']['conversion_success'],
        'post_conversion_save_error_reaches_save_markdown' => $postConversionSaveError['conversion_result']['save_markdown_reached'],
        'post_conversion_save_error_writes_markdown' => $postConversionSaveError['conversion_result']['save_markdown_writes_markdown'],
        'post_conversion_save_error_traceback_available' => $postConversionSaveError['conversion_result']['traceback_available'],
        'text_length_error_status' => $textLengthErrorPreflight['status'],
        'text_length_error_stage' => $textLengthErrorPreflight['error_stage'],
        'text_length_error_boundary' => $textLengthErrorPreflight['error_boundary'],
        'text_length_error_class' => $textLengthErrorPreflight['error_class'],
        'text_length_error_return_boundary' => $textLengthErrorPreflight['upstream_return_boundary'],
        'text_length_error_converter_called' => $textLengthErrorConverterCalled,
        'text_length_error_writes_markdown' => is_file($output . DIRECTORY_SEPARATOR . 'text-length-error' . DIRECTORY_SEPARATOR . 'text-length-error.md'),
        'missing_input_error_precedes_metadata_file' => str_contains($missingInputError, 'Batch input folder does not exist')
            && !str_contains($missingInputError, 'metadata file'),
        'invalid_chunk_error_precedes_metadata_file' => str_contains($invalidChunkError, 'Batch chunk count must be at least one')
            && !str_contains($invalidChunkError, 'metadata file'),
        'zero_num_chunks_boundary_success' => $zeroNumChunksBoundary['success'],
        'zero_num_chunks_error_boundary' => $zeroNumChunksBoundary['error_boundary'],
        'zero_num_chunks_error_class' => $zeroNumChunksBoundary['error_class'],
        'zero_num_chunks_upstream_error_message' => $zeroNumChunksBoundary['upstream_error_message'],
        'zero_num_chunks_listing_success' => $zeroNumChunksBoundary['input_listing']['listing_success'],
        'zero_num_chunks_file_basenames' => $zeroNumChunksBoundary['input_listing']['file_basenames'],
        'zero_num_chunks_output_creation_reached' => $zeroNumChunksBoundary['paths']['output_folder_creation_reached'],
        'zero_num_chunks_output_creation_blocked' => $zeroNumChunksBoundary['paths']['output_folder_creation_blocked'],
        'zero_num_chunks_chunking_reached' => $zeroNumChunksBoundary['chunking']['chunking_reached'],
        'zero_num_chunks_chunk_error_message' => $zeroNumChunksBoundary['chunking']['chunk_error_message'],
        'zero_num_chunks_metadata_load_reached' => $zeroNumChunksBoundary['metadata']['metadata_load_reached'],
        'zero_num_chunks_task_args_count' => $zeroNumChunksBoundary['worker_pool']['task_args_count'],
        'zero_num_chunks_summary_reached' => $zeroNumChunksBoundary['console_summary']['summary_reached'],
        'ready_text_length' => $plans['ready-for-marker.pdf']['text_length'],
        'ready_should_invoke_converter' => $plans['ready-for-marker.pdf']['should_invoke_converter'],
        'existing_filetype_checked' => $plans['already-imported.pdf']['filetype_checked'],
        'spoof_text_length_checked' => $plans['extension-spoof.pdf']['text_length_checked'],
        'executes_python_or_models' => $plans['ready-for-marker.pdf']['executes_python_or_models'],
        'executes_multiprocessing' => $plans['ready-for-marker.pdf']['executes_multiprocessing'],
        'runtime_executes_multiprocessing' => $runtimePlan['executes_multiprocessing'],
        'metadata_shape_executes_python_or_models' => $metadataShapePlan['executes_python_or_models'],
        'metadata_shape_executes_multiprocessing' => $metadataShapePlan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plans['ready-for-marker.pdf']['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    if (isset($unreadableInput) && is_dir($unreadableInput)) {
        chmod($unreadableInput, 0700);
    }
    $removeTree($input);
    $removeTree($output);
    $removeTree($blockedOutputRoot);
    $removeTree($relativeMetadataRoot);
}

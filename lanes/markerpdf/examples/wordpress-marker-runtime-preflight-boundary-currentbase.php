<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\OutputWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-preflight-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-preflight-output-' . $runId;
$blockedOutputRoot = sys_get_temp_dir() . '/markerpdf-runtime-preflight-blocked-output-' . $runId;
@mkdir($input, 0777, true);
@mkdir($output, 0777, true);
@mkdir($blockedOutputRoot, 0777, true);

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

try {
    $writePdf($input . DIRECTORY_SEPARATOR . 'already-imported.pdf', 'Already imported WordPress PDF');
    $writePdf($input . DIRECTORY_SEPARATOR . 'short-text.pdf', 'Short text PDF');
    $writePdf($input . DIRECTORY_SEPARATOR . 'ready-for-marker.pdf', 'Ready for Marker conversion PDF');
    file_put_contents($input . DIRECTORY_SEPARATOR . 'extension-spoof.pdf', "PK\x03\x04not really a pdf");
    file_put_contents($input . DIRECTORY_SEPARATOR . 'upload-notes.txt', 'WordPress sidecar notes queued by upstream before per-file preflight.');
    mkdir($input . DIRECTORY_SEPARATOR . 'nested.pdf');

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
    $outputConflictPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $blockedOutput,
        metadataFile: $missingMetadata,
        workers: 8
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
    file_put_contents($valueMetadata, json_encode([
        'already-imported.pdf' => ['title' => 'Already Imported'],
        'ready-for-marker.pdf' => 'English',
        'short-text.pdf' => null,
        'upload-notes.txt' => ['English'],
        'extension-spoof.pdf' => 0,
    ], JSON_THROW_ON_ERROR));
    $metadataValuePlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        metadataFile: $valueMetadata,
        workers: 8,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
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
        || $metadataValuePlan['metadata']['metadata_value_review']['truthy_non_mapping_metadata_filenames'] !== ['ready-for-marker.pdf', 'upload-notes.txt']
        || $metadataValuePlan['metadata']['metadata_value_review']['falsy_non_mapping_metadata_filenames'] !== ['extension-spoof.pdf', 'short-text.pdf']
        || $metadataValuePlan['metadata']['metadata_value_review']['conversion_error_boundary'] !== 'convert-single-pdf-metadata-get-failed'
        || $metadataValuePlan['worker_pool']['pool_launchable'] !== true
        || $metadataValuePlan['worker_pool']['task_args_count'] !== 5
    ) {
        throw new RuntimeException('Expected per-file scalar/list metadata values to pass task tuple construction while recording convert_single_pdf metadata.get risk.');
    }
    if ($runtimePlan['chunking']['max_files_limit_active'] !== false || $runtimePlan['chunking']['selected_count'] !== 5) {
        throw new RuntimeException('Expected --max=0 to behave like upstream convert.py and leave the WordPress queue uncapped.');
    }
    if (
        $runtimePlan['input_listing']['skipped_non_file_basenames'] !== ['nested.pdf']
        || $runtimePlan['input_listing']['extension_filter_active'] !== false
        || $runtimePlan['input_listing']['selected_non_pdf_filenames'] !== ['upload-notes.txt']
    ) {
        throw new RuntimeException('Expected convert.py runtime preflight to filter only non-files and leave regular non-PDF sidecars as task candidates.');
    }
    if ($negativeMaxPlan['chunking']['max_files_limit_active'] !== true || $negativeMaxPlan['chunking']['selected_count'] !== 4) {
        throw new RuntimeException('Expected negative --max to behave like upstream Python slicing and drop the tail of the queue.');
    }
    if (
        $negativeChunkPlan['chunking']['negative_chunk_index_active'] !== true
        || $negativeChunkPlan['chunking']['python_slice_start_index'] !== 0
        || $negativeChunkPlan['chunking']['python_slice_end_index'] !== 2
        || $negativeChunkPlan['chunking']['selected_filenames'] !== ['already-imported.pdf', 'extension-spoof.pdf']
    ) {
        throw new RuntimeException('Expected negative --chunk_idx to follow upstream Python slice normalization before task tuples are built.');
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
    if ($zeroMinLengthSpoof['min_length_gate_active'] !== false || $zeroMinLengthSpoof['filetype_checked'] !== false || $zeroMinLengthSpoof['status'] !== 'ready-for-conversion') {
        throw new RuntimeException('Expected --min_length=0 to leave filetype preflight inactive like upstream convert.py.');
    }
    if ($negativeMinLengthSpoof['min_length_gate_active'] !== true || $negativeMinLengthSpoof['status'] !== 'skipped-unsupported-filetype') {
        throw new RuntimeException('Expected negative --min_length to keep the upstream filetype preflight gate active.');
    }
    if (!str_contains($missingInputError, 'Batch input folder does not exist') || str_contains($missingInputError, 'metadata file')) {
        throw new RuntimeException('Expected missing input folder to be reported before metadata_file loading.');
    }
    if (!str_contains($invalidChunkError, 'Batch chunk count must be at least one') || str_contains($invalidChunkError, 'metadata file')) {
        throw new RuntimeException('Expected invalid chunk count to be reported before metadata_file loading.');
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
        'runtime_skipped_non_file_entries' => $runtimePlan['input_listing']['skipped_non_file_basenames'],
        'runtime_extension_filter_active' => $runtimePlan['input_listing']['extension_filter_active'],
        'runtime_selected_non_pdf_filenames' => $runtimePlan['input_listing']['selected_non_pdf_filenames'],
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
        'metadata_error_load_reached' => $metadataErrorPlan['metadata']['metadata_load_reached'],
        'metadata_error_load_success' => $metadataErrorPlan['metadata']['metadata_load_success'],
        'metadata_error_boundary' => $metadataErrorPlan['metadata']['metadata_error_boundary'],
        'metadata_error_class' => $metadataErrorPlan['metadata']['metadata_error_class'],
        'metadata_error_selected_filenames' => $metadataErrorPlan['chunking']['selected_filenames'],
        'metadata_error_task_args_count' => $metadataErrorPlan['worker_pool']['task_args_count'],
        'metadata_error_pool_error_boundary' => $metadataErrorPlan['worker_pool']['pool_error_boundary'],
        'metadata_error_conversion_summary_reached' => $metadataErrorPlan['console_summary']['summary_reached'],
        'metadata_error_conversion_summary_blocked_by' => $metadataErrorPlan['console_summary']['blocked_by'],
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
        'zero_worker_total_processes' => $zeroWorkerPlan['worker_pool']['total_processes'],
        'zero_worker_pool_creation_success' => $zeroWorkerPlan['worker_pool']['pool_creation']['pool_creation_success'],
        'zero_worker_pool_creation_error_boundary' => $zeroWorkerPlan['worker_pool']['pool_creation']['error_boundary'],
        'zero_worker_pool_creation_error_class' => $zeroWorkerPlan['worker_pool']['pool_creation']['error_class'],
        'zero_worker_pool_imap_reached' => $zeroWorkerPlan['worker_pool']['pool_creation']['pool_imap_reached'],
        'negative_worker_total_processes' => $negativeWorkerPlan['worker_pool']['total_processes'],
        'negative_worker_pool_creation_error_boundary' => $negativeWorkerPlan['worker_pool']['pool_creation']['error_boundary'],
        'negative_worker_pool_creation_error_class' => $negativeWorkerPlan['worker_pool']['pool_creation']['error_class'],
        'negative_worker_pool_imap_reached' => $negativeWorkerPlan['worker_pool']['pool_creation']['pool_imap_reached'],
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
        'missing_input_error_precedes_metadata_file' => str_contains($missingInputError, 'Batch input folder does not exist')
            && !str_contains($missingInputError, 'metadata file'),
        'invalid_chunk_error_precedes_metadata_file' => str_contains($invalidChunkError, 'Batch chunk count must be at least one')
            && !str_contains($invalidChunkError, 'metadata file'),
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
    $removeTree($input);
    $removeTree($output);
    $removeTree($blockedOutputRoot);
}

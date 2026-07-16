<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\ChunkConversionPlanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$planner = new ChunkConversionPlanner();
$plan = $planner->planFromEnvironment(
    '/var/www/html/wp-content/uploads/pdf-import',
    '/var/www/html/wp-content/uploads/marker-output',
    [
        'NUM_DEVICES' => '2',
        'NUM_WORKERS' => '3',
        'METADATA_FILE' => '/var/www/html/wp-content/uploads/pdf-import/metadata.json',
        'MIN_LENGTH' => '0',
    ]
);

if ($plan['min_length'] !== '0' || $plan['optional_flags']['min_length_included'] !== true) {
    throw new RuntimeException('Expected chunk_convert.sh queue preflight to pass non-empty MIN_LENGTH=0 through as a raw shell flag.');
}
if ($plan['optional_flags']['min_length_integer_validation_deferred_to_marker_argparse'] !== true) {
    throw new RuntimeException('Expected MIN_LENGTH integer validation to be deferred to marker/convert.py argparse.');
}
if ($plan['executes_subprocess'] !== false || $plan['executes_python_or_models'] !== false) {
    throw new RuntimeException('Chunk conversion queue smoke must not execute shell subprocesses, Python, or model workers.');
}

$shell = $plan['shell_orchestration'];
if (
    $shell['signal_trap'] !== 'trap \'pkill -P $$\' SIGINT'
    || $shell['jobs_launched_in_background'] !== true
    || $shell['eval_used_for_jobs'] !== true
    || $shell['sleep_between_launches_seconds'] !== 5
    || $shell['wait_after_all_launches'] !== true
    || $shell['native_plan_executes_shell'] !== false
) {
    throw new RuntimeException('Expected chunk_convert.sh shell orchestration to preserve trap/background/eval/sleep/wait review metadata without executing the shell.');
}

$queueItems = array_map(static fn (array $job): array => [
    'hook' => 'markerpdf_convert_chunk',
    'chunk_idx' => $job['chunk_idx'],
    'num_chunks' => $job['num_chunks'],
    'workers' => $job['workers'],
    'cuda_visible_devices' => $job['env']['CUDA_VISIBLE_DEVICES'],
    'min_length' => $job['min_length'],
    'min_length_flag_included' => $job['min_length_flag_included'],
    'shell_launch' => [
        'echo_line' => $job['shell_launch']['echo_line'],
        'eval_call' => $job['shell_launch']['eval_call'],
        'backgrounded' => $job['shell_launch']['backgrounded'],
        'sleep_after_launch_seconds' => $job['shell_launch']['sleep_after_launch_seconds'],
        'raw_shell_command_path_hazard' => $job['shell_launch']['raw_shell_command_path_hazard'],
        'native_plan_executes_shell' => $job['shell_launch']['native_plan_executes_shell'],
    ],
    'argv' => $job['argv'],
], $plan['jobs']);

echo json_encode([
    'scenario' => 'wordpress-markerpdf-chunk-convert-queue',
    'purpose' => 'Plan chunk_convert.py/chunk_convert.sh-style PDF import shards for a WordPress queue, including raw non-empty MIN_LENGTH shell flags and trap/background/eval/wait shell lifecycle metadata, without executing the upstream marker subprocess.',
    'launch_delay_seconds' => $plan['launch_delay_seconds'],
    'shell_orchestration' => [
        'signal_trap' => $shell['signal_trap'],
        'interrupt_cleanup_command' => $shell['interrupt_cleanup_command'],
        'jobs_launched_in_background' => $shell['jobs_launched_in_background'],
        'eval_used_for_jobs' => $shell['eval_used_for_jobs'],
        'sleep_between_launches_seconds' => $shell['sleep_between_launches_seconds'],
        'wait_command' => $shell['wait_command'],
        'wait_after_all_launches' => $shell['wait_after_all_launches'],
        'native_plan_executes_shell' => $shell['native_plan_executes_shell'],
    ],
    'optional_flags' => $plan['optional_flags'],
    'executes_subprocess' => $plan['executes_subprocess'],
    'executes_python_or_models' => $plan['executes_python_or_models'],
    'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    'jobs' => $queueItems,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

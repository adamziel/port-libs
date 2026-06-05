<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\ChunkConversionPlanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$planner = new ChunkConversionPlanner();
$plan = $planner->wrapperRuntimePreflightPlan(
    [
        '/var/www/html/wp-content/uploads/pdf imports; touch /tmp/markerpdf-owned',
        '/var/www/html/wp-content/uploads/marker output',
    ],
    '/opt/marker/chunk_convert.sh'
);

if ($plan['executes_subprocess'] !== false || $plan['executes_python_or_models'] !== false) {
    throw new RuntimeException('Chunk wrapper preflight smoke must not execute subprocesses, Python, or model workers.');
}
if ($plan['shell_boundary']['raw_shell_command_path_hazard'] !== true) {
    throw new RuntimeException('Expected raw chunk_convert.py shell command hazard to be review metadata.');
}
if ($plan['subprocess']['argument_escaping_applied'] !== false || $plan['subprocess']['shell'] !== true) {
    throw new RuntimeException('Expected chunk_convert.py wrapper to preserve shell=True raw command semantics.');
}

echo json_encode([
    'scenario' => 'wordpress-chunk-convert-wrapper-preflight-currentbase',
    'purpose' => 'Review chunk_convert.py wrapper raw shell-command construction for WordPress batch import paths before the shell script validates NUM_DEVICES/NUM_WORKERS, without executing the upstream marker subprocess.',
    'source' => $plan['source'],
    'parse_args_success' => $plan['parse_args']['parse_args_success'],
    'script_path' => $plan['resource_script']['script_path'],
    'subprocess_call' => $plan['subprocess']['call'],
    'raw_command' => $plan['subprocess']['command'],
    'shell_true' => $plan['subprocess']['shell'],
    'check_true' => $plan['subprocess']['check'],
    'argv_list_used' => $plan['subprocess']['argv_list_used'],
    'argument_escaping_applied' => $plan['subprocess']['argument_escaping_applied'],
    'quotes_positionals' => $plan['subprocess']['quotes_positionals'],
    'env_validation_before_subprocess' => $plan['shell_boundary']['env_validation_before_subprocess'],
    'chunk_shell_validates_environment_after_subprocess_launch' => $plan['shell_boundary']['chunk_convert_sh_validates_environment_after_subprocess_launch'],
    'positionals_contain_shell_whitespace' => $plan['shell_boundary']['positionals_contain_shell_whitespace'],
    'positionals_contain_shell_metacharacters' => $plan['shell_boundary']['positionals_contain_shell_metacharacters'],
    'raw_shell_command_path_hazard' => $plan['shell_boundary']['raw_shell_command_path_hazard'],
    'next_stage' => $plan['next_stage'],
    'executes_subprocess' => $plan['executes_subprocess'],
    'executes_python_or_models' => $plan['executes_python_or_models'],
    'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

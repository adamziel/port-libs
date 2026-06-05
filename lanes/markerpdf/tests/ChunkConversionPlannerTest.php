<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\ChunkConversionPlanner;

return [
    'records chunk_convert.py raw shell wrapper boundary before chunk shell validation' => static function (TestRunner $t): void {
        $plan = (new ChunkConversionPlanner())->wrapperRuntimePreflightPlan(
            [
                '/wp/uploads/pdf imports; touch /tmp/markerpdf-owned',
                '/wp/uploads/marker output',
            ],
            '/opt/marker/chunk_convert.sh'
        );

        $t->same('markerpdf.chunk_convert_wrapper_preflight.v1', $plan['schema']);
        $t->contains('chunk_convert.py::main', $plan['source']);
        $t->same([
            '/wp/uploads/pdf imports; touch /tmp/markerpdf-owned',
            '/wp/uploads/marker output',
        ], $plan['argv']);
        $t->same(true, $plan['parse_args']['parse_args_success']);
        $t->same('/opt/marker/chunk_convert.sh', $plan['resource_script']['script_path']);
        $t->same('pkg_resources.resource_filename(__name__, "chunk_convert.sh")', $plan['resource_script']['lookup_call']);
        $t->same('/opt/marker/chunk_convert.sh /wp/uploads/pdf imports; touch /tmp/markerpdf-owned /wp/uploads/marker output', $plan['subprocess']['command']);
        $t->same(true, $plan['subprocess']['shell']);
        $t->same(true, $plan['subprocess']['check']);
        $t->same('subprocess.run(cmd, shell=True, check=True)', $plan['subprocess']['call']);
        $t->same(false, $plan['subprocess']['argv_list_used']);
        $t->same(false, $plan['subprocess']['argument_escaping_applied']);
        $t->same(false, $plan['subprocess']['quotes_positionals']);
        $t->same('/wp/uploads/pdf imports; touch /tmp/markerpdf-owned', $plan['subprocess']['raw_in_folder_fragment']);
        $t->same('/wp/uploads/marker output', $plan['subprocess']['raw_out_folder_fragment']);
        $t->same(false, $plan['shell_boundary']['env_validation_before_subprocess']);
        $t->same(true, $plan['shell_boundary']['chunk_convert_sh_validates_environment_after_subprocess_launch']);
        $t->same(true, $plan['shell_boundary']['positionals_contain_shell_whitespace']);
        $t->same(true, $plan['shell_boundary']['positionals_contain_shell_metacharacters']);
        $t->same(true, $plan['shell_boundary']['raw_shell_command_path_hazard']);
        $t->same('chunk_convert.sh', $plan['next_stage']);
        $t->same(true, $plan['review_only']);
        $t->same(false, $plan['executes_subprocess']);
        $t->same(false, $plan['executes_python_or_models']);
        $t->same(false, $plan['executes_external_pdf_tools']);
    },
    'blocks chunk_convert.py wrapper argparse errors before resource lookup or subprocess launch' => static function (TestRunner $t): void {
        $missing = (new ChunkConversionPlanner())->wrapperRuntimePreflightPlan(['/wp/uploads/pdf-import']);
        $extra = (new ChunkConversionPlanner())->wrapperRuntimePreflightPlan([
            '/wp/uploads/pdf-import',
            '/wp/uploads/marker-output',
            'unexpected',
        ]);

        $t->same('markerpdf.chunk_convert_wrapper_preflight.v1', $missing['schema']);
        $t->same(false, $missing['parse_args']['parse_args_success']);
        $t->same('argparse-system-exit', $missing['parse_args']['error_boundary']);
        $t->same('SystemExit', $missing['parse_args']['error_class']);
        $t->same(['out_folder'], $missing['parse_args']['missing_required_arguments']);
        $t->same(true, $missing['resource_script']['blocked']);
        $t->same(true, $missing['subprocess']['blocked']);
        $t->same(true, $missing['subprocess']['blocks_chunk_shell']);
        $t->same('parse_args', $missing['blocked_by']);
        $t->same(null, $missing['next_stage']);
        $t->same(false, $missing['executes_subprocess']);

        $t->same(false, $extra['parse_args']['parse_args_success']);
        $t->same('unrecognized arguments: unexpected', $extra['parse_args']['error_message']);
        $t->same('unexpected', $extra['parse_args']['error_argument']);
        $t->same(true, $extra['resource_script']['blocked']);
        $t->same(true, $extra['subprocess']['blocked']);
        $t->same(false, $extra['executes_subprocess']);
    },
    'plans chunk_convert.sh marker jobs across CUDA devices' => static function (TestRunner $t): void {
        $plan = (new ChunkConversionPlanner())->planFromEnvironment('/srv/incoming-pdfs', '/srv/marker-output', [
            'NUM_DEVICES' => '3',
            'NUM_WORKERS' => '4',
            'METADATA_FILE' => '/srv/import-meta.json',
            'MIN_LENGTH' => '250',
        ]);

        $t->same(3, $plan['num_devices']);
        $t->same(4, $plan['num_workers']);
        $t->same(5, $plan['launch_delay_seconds']);
        $t->same(3, count($plan['jobs']));
        $t->same('0', $plan['jobs'][0]['env']['CUDA_VISIBLE_DEVICES']);
        $t->same('2', $plan['jobs'][2]['env']['CUDA_VISIBLE_DEVICES']);
        $t->same([
            'marker',
            '/srv/incoming-pdfs',
            '/srv/marker-output',
            '--num_chunks',
            '3',
            '--chunk_idx',
            '2',
            '--workers',
            '4',
            '--metadata_file',
            '/srv/import-meta.json',
            '--min_length',
            '250',
        ], $plan['jobs'][2]['argv']);
        $t->contains("CUDA_VISIBLE_DEVICES='2'", $plan['jobs'][2]['command']);
        $t->contains("'--chunk_idx' '2'", $plan['jobs'][2]['command']);
    },
    'omits optional chunk_convert.sh flags when environment variables are empty' => static function (TestRunner $t): void {
        $plan = (new ChunkConversionPlanner())->planFromEnvironment('/in', '/out', [
            'NUM_DEVICES' => 1,
            'NUM_WORKERS' => 2,
            'METADATA_FILE' => '',
            'MIN_LENGTH' => '',
        ]);

        $t->same([
            'marker',
            '/in',
            '/out',
            '--num_chunks',
            '1',
            '--chunk_idx',
            '0',
            '--workers',
            '2',
        ], $plan['jobs'][0]['argv']);
    },
    'passes non-empty chunk_convert.sh MIN_LENGTH values through before marker argparse' => static function (TestRunner $t): void {
        $planner = new ChunkConversionPlanner();

        $zero = $planner->planFromEnvironment('/in', '/out', [
            'NUM_DEVICES' => '1',
            'NUM_WORKERS' => '2',
            'MIN_LENGTH' => '0',
        ]);
        $negative = $planner->planFromEnvironment('/in', '/out', [
            'NUM_DEVICES' => '1',
            'NUM_WORKERS' => '2',
            'MIN_LENGTH' => '-1',
        ]);
        $nonnumeric = $planner->planFromEnvironment('/in', '/out', [
            'NUM_DEVICES' => '1',
            'NUM_WORKERS' => '2',
            'MIN_LENGTH' => 'short',
        ]);

        $t->same('0', $zero['min_length']);
        $t->same('0', $zero['jobs'][0]['min_length']);
        $t->same(true, $zero['optional_flags']['min_length_included']);
        $t->same(true, $zero['optional_flags']['min_length_integer_validation_deferred_to_marker_argparse']);
        $t->same([
            'marker',
            '/in',
            '/out',
            '--num_chunks',
            '1',
            '--chunk_idx',
            '0',
            '--workers',
            '2',
            '--min_length',
            '0',
        ], $zero['jobs'][0]['argv']);

        $t->same('-1', $negative['min_length']);
        $t->same('-1', $negative['jobs'][0]['min_length']);
        $t->same(true, $negative['jobs'][0]['min_length_flag_included']);
        $t->contains("'--min_length' '-1'", $negative['jobs'][0]['command']);

        $t->same('short', $nonnumeric['min_length']);
        $t->same('short', $nonnumeric['jobs'][0]['min_length']);
        $t->same('chunk_convert.sh [[ -n "$MIN_LENGTH" ]]', $nonnumeric['optional_flags']['min_length_condition']);
        $t->same('convert.py argparse --min_length type=int', $nonnumeric['optional_flags']['min_length_parse_boundary']);
        $t->same(false, $nonnumeric['executes_python_or_models']);
        $t->same(false, $nonnumeric['executes_subprocess']);
    },
    'mirrors chunk_convert.sh validation for required environment and folders' => static function (TestRunner $t): void {
        $planner = new ChunkConversionPlanner();

        $t->throws(InvalidArgumentException::class, static fn (): array => $planner->planFromEnvironment('/in', '/out', [
            'NUM_WORKERS' => 2,
        ]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $planner->planFromEnvironment('/in', '/out', [
            'NUM_DEVICES' => 2,
        ]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $planner->planFromEnvironment('', '/out', [
            'NUM_DEVICES' => 2,
            'NUM_WORKERS' => 2,
        ]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $planner->planFromEnvironment('/in', '', [
            'NUM_DEVICES' => 2,
            'NUM_WORKERS' => 2,
        ]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $planner->planFromEnvironment('/in', '/out', [
            'NUM_DEVICES' => 'two',
            'NUM_WORKERS' => 2,
        ]));
    },
    'produces WordPress queue shards without executing marker subprocesses' => static function (TestRunner $t): void {
        $plan = (new ChunkConversionPlanner())->planDeviceJobs(
            '/wp/uploads/pdf-import',
            '/wp/uploads/marker-output',
            2,
            5,
            '/wp/uploads/pdf-import/metadata.json',
            100
        );

        $queueItems = array_map(static fn (array $job): array => [
            'queue' => 'markerpdf-import',
            'chunk' => $job['chunk_idx'] + 1,
            'chunks' => $job['num_chunks'],
            'workers' => $job['workers'],
            'argv' => $job['argv'],
        ], $plan['jobs']);

        $t->same(2, count($queueItems));
        $t->same(1, $queueItems[0]['chunk']);
        $t->same(2, $queueItems[1]['chunk']);
        $t->same('/wp/uploads/pdf-import/metadata.json', $queueItems[1]['argv'][10]);
        $t->same('100', $queueItems[1]['argv'][12]);
    },
];

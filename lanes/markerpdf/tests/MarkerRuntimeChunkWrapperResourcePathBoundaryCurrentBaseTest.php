<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\ChunkConversionPlanner;

return [
    'records chunk_convert.py resource script path shell boundary before subprocess launch' => static function (TestRunner $t): void {
        $scriptPath = '/opt/wp plugins/marker; touch /tmp/markerpdf-script-owned/chunk_convert.sh';
        $plan = (new ChunkConversionPlanner())->wrapperRuntimePreflightPlan(
            [
                '/wp/uploads/pdf-source',
                '/wp/uploads/marker-output',
            ],
            $scriptPath
        );

        $t->same('markerpdf.chunk_convert_wrapper_preflight.v1', $plan['schema']);
        $t->same(true, $plan['parse_args']['parse_args_success']);
        $t->same($scriptPath, $plan['resource_script']['script_path']);
        $t->same('pkg_resources.resource_filename(__name__, "chunk_convert.sh")', $plan['resource_script']['lookup_call']);
        $t->same($scriptPath . ' /wp/uploads/pdf-source /wp/uploads/marker-output', $plan['subprocess']['command']);
        $t->same($scriptPath, $plan['subprocess']['raw_script_path_fragment']);
        $t->same('/wp/uploads/pdf-source', $plan['subprocess']['raw_in_folder_fragment']);
        $t->same('/wp/uploads/marker-output', $plan['subprocess']['raw_out_folder_fragment']);
        $t->same(true, $plan['subprocess']['shell']);
        $t->same(true, $plan['subprocess']['check']);
        $t->same(false, $plan['subprocess']['argv_list_used']);
        $t->same(false, $plan['subprocess']['argument_escaping_applied']);
        $t->same(false, $plan['subprocess']['quotes_positionals']);
        $t->same(false, $plan['subprocess']['blocked']);

        $boundary = $plan['shell_boundary'];
        $t->same('f"{script_path} {args.in_folder} {args.out_folder}"', $boundary['raw_command_source']);
        $t->same($scriptPath, $boundary['raw_script_path_fragment']);
        $t->same(true, $boundary['resource_script_contains_shell_whitespace']);
        $t->same(true, $boundary['resource_script_contains_shell_metacharacters']);
        $t->same(true, $boundary['positionals_contain_shell_whitespace']);
        $t->same(true, $boundary['positionals_contain_shell_metacharacters']);
        $t->same(true, $boundary['raw_shell_command_path_hazard']);
        $t->same(false, $boundary['env_validation_before_subprocess']);
        $t->same(true, $boundary['chunk_convert_sh_validates_environment_after_subprocess_launch']);
        $t->same(false, $boundary['native_plan_executes_shell']);
        $t->same('chunk_convert.sh', $plan['next_stage']);
        $t->same(true, $plan['review_only']);
        $t->same(false, $plan['executes_subprocess']);
        $t->same(false, $plan['executes_python_or_models']);
        $t->same(false, $plan['executes_external_pdf_tools']);
    },
];

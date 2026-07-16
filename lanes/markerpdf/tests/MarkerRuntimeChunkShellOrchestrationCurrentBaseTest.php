<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\ChunkConversionPlanner;

return [
    'records chunk_convert.sh trap background eval pacing and wait boundaries' => static function (TestRunner $t): void {
        $plan = (new ChunkConversionPlanner())->planDeviceJobs(
            '/wp/uploads/pdf imports; touch /tmp/markerpdf-owned',
            '/wp/uploads/marker output',
            2,
            3,
            '/wp/uploads/pdf imports/metadata file.json',
            '0'
        );

        $shell = $plan['shell_orchestration'];

        $t->same('markerpdf.chunk_convert_shell_orchestration.v1', $shell['schema']);
        $t->contains('chunk_convert.sh trap/background/wait orchestration boundary', $shell['source']);
        $t->same('trap \'pkill -P $$\' SIGINT', $shell['signal_trap']);
        $t->same('SIGINT', $shell['trap_signal']);
        $t->same('pkill -P $$', $shell['interrupt_cleanup_command']);
        $t->same('for (( i=0; i<$NUM_DEVICES; i++ ))', $shell['launch_loop']);
        $t->same(2, $shell['launch_count']);
        $t->same(true, $shell['jobs_launched_in_background']);
        $t->same(true, $shell['eval_used_for_jobs']);
        $t->same(5, $shell['sleep_between_launches_seconds']);
        $t->same('wait', $shell['wait_command']);
        $t->same(true, $shell['wait_after_all_launches']);
        $t->same(false, $shell['native_plan_executes_shell']);
        $t->same(false, $shell['executes_python_or_models']);

        $job = $plan['jobs'][1];
        $launch = $job['shell_launch'];

        $t->same('markerpdf.chunk_convert_job_shell_launch.v1', $launch['schema']);
        $t->same(1, $launch['device_num']);
        $t->same('Running convert.py on GPU 1', $launch['echo_line']);
        $t->same('cmd="CUDA_VISIBLE_DEVICES=$DEVICE_NUM marker $INPUT_FOLDER $OUTPUT_FOLDER --num_chunks $NUM_DEVICES --chunk_idx $DEVICE_NUM --workers $NUM_WORKERS"', $launch['command_assignment_pattern']);
        $t->same('[[ -n "$METADATA_FILE" ]] && cmd="$cmd --metadata_file $METADATA_FILE"', $launch['metadata_file_append_pattern']);
        $t->same('[[ -n "$MIN_LENGTH" ]] && cmd="$cmd --min_length $MIN_LENGTH"', $launch['min_length_append_pattern']);
        $t->same('eval $cmd &', $launch['eval_call']);
        $t->same(true, $launch['eval_used']);
        $t->same(true, $launch['backgrounded']);
        $t->same('&', $launch['background_operator']);
        $t->same(5, $launch['sleep_after_launch_seconds']);
        $t->same(false, $launch['quotes_positionals']);
        $t->same(false, $launch['argument_escaping_applied']);
        $t->same(true, $launch['positionals_contain_shell_whitespace']);
        $t->same(true, $launch['positionals_contain_shell_metacharacters']);
        $t->same(true, $launch['raw_shell_command_path_hazard']);
        $t->same('/wp/uploads/pdf imports; touch /tmp/markerpdf-owned', $launch['raw_input_folder_fragment']);
        $t->same('/wp/uploads/marker output', $launch['raw_output_folder_fragment']);
        $t->same('/wp/uploads/pdf imports/metadata file.json', $launch['raw_metadata_file_fragment']);
        $t->same('0', $launch['raw_min_length_fragment']);
        $t->contains('CUDA_VISIBLE_DEVICES=1 marker /wp/uploads/pdf imports; touch /tmp/markerpdf-owned /wp/uploads/marker output', $launch['raw_command']);
        $t->contains('--metadata_file /wp/uploads/pdf imports/metadata file.json', $launch['raw_command']);
        $t->contains('--min_length 0', $launch['raw_command']);
        $t->same(false, $launch['native_plan_executes_shell']);
        $t->same(false, $plan['executes_subprocess']);
        $t->same(false, $plan['executes_python_or_models']);
        $t->same(false, $plan['executes_external_pdf_tools']);
    },
];

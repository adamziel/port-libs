<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-pool-context-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf runtime pool context folder.');
    }

    return $path;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_link($child) || !is_dir($child)) {
            unlink($child);
        } else {
            $removeTree($child);
        }
    }

    rmdir($path);
};

return [
    'records convert.py Pool context manager boundary before model list deletion' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['first.pdf', 'second.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }

            $batch = new BatchConverter();
            $plan = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 4,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );
            $blocked = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 0,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $context = $plan['worker_pool']['pool_context_manager'];
            $t->same('convert.py with mp.Pool context manager boundary', $context['source']);
            $t->same('after_task_args_wraps_pool_imap_until_before_del_model_lst', $context['order']);
            $t->same(true, $context['context_manager_reached']);
            $t->same(true, $context['context_enter_success']);
            $t->same(null, $context['blocked_by']);
            $t->same('with mp.Pool(processes=total_processes, initializer=worker_init, initargs=(model_lst,)) as pool', $context['context_manager_call']);
            $t->same('pool', $context['pool_variable']);
            $t->same(2, $context['processes']);
            $t->same('model_lst', $context['worker_init_argument']);
            $t->same(true, $context['wraps_pool_imap']);
            $t->same(true, $context['result_drain_inside_context']);
            $t->same(true, $context['worker_handler_override_inside_context']);
            $t->same(true, $context['context_exit_reached']);
            $t->same(true, $context['context_exit_after_worker_handler_override']);
            $t->same(true, $context['model_list_delete_after_context_exit']);
            $t->same(false, $context['executes_python_or_models']);
            $t->same(false, $context['executes_multiprocessing']);
            $t->same(false, $context['executes_external_pdf_tools']);

            $t->same(true, $plan['worker_pool']['pool_result_drain']['cleanup_after_result_drain']);
            $t->same(true, $plan['worker_pool']['pool_cleanup']['cleanup_reached']);
            $t->same(true, $plan['worker_pool']['pool_cleanup']['model_list_delete_reached']);

            $blockedContext = $blocked['worker_pool']['pool_context_manager'];
            $t->same(true, $blockedContext['context_manager_reached']);
            $t->same(false, $blockedContext['context_enter_success']);
            $t->same('pool-process-count-failed', $blockedContext['blocked_by']);
            $t->same(0, $blockedContext['processes']);
            $t->same(false, $blockedContext['wraps_pool_imap']);
            $t->same(false, $blockedContext['result_drain_inside_context']);
            $t->same(false, $blockedContext['worker_handler_override_inside_context']);
            $t->same(false, $blockedContext['context_exit_reached']);
            $t->same(false, $blockedContext['model_list_delete_after_context_exit']);
            $t->same(false, $blockedContext['executes_multiprocessing']);
            $t->same('pool-process-count-failed', $blocked['worker_pool']['pool_creation']['error_boundary']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];

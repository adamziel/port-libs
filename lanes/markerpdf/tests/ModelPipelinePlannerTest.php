<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\ModelPipelinePlanner;

return [
    'plans upstream load_all_models sequence and returned model list order' => static function (TestRunner $t): void {
        $plan = (new ModelPipelinePlanner())->loadAllModelsPlan();

        $t->same(['PYTORCH_ENABLE_MPS_FALLBACK' => '1'], $plan['environment']);
        $t->same(['detection', 'layout', 'order', 'ocr', 'texify', 'table_recognition'], $plan['load_sequence']);
        $t->same(['texify', 'layout', 'order', 'detection', 'ocr', 'table_recognition'], $plan['model_list_order']);
        $t->same('surya.model.detection.model.load_model', $plan['models']['detection']['model_loader']);
        $t->same('surya.model.detection.processor.load_processor', $plan['models']['detection']['processor_loader']);
        $t->same(true, $plan['models']['table_recognition']['processor_attached']);
    },
    'uses upstream texify and layout default checkpoints without explicit device args' => static function (TestRunner $t): void {
        $settings = new MarkerSettings([
            'TORCH_DEVICE' => 'mps',
            'TEXIFY_MODEL_NAME' => 'wordpress/texify-review',
            'LAYOUT_MODEL_CHECKPOINT' => 'wordpress/surya-layout-review',
        ]);
        $plan = (new ModelPipelinePlanner($settings))->loadAllModelsPlan();

        $t->same([
            'checkpoint' => 'wordpress/texify-review',
            'device' => 'mps',
            'dtype' => 'float16',
        ], $plan['models']['texify']['model_arguments']);
        $t->same(['checkpoint' => 'wordpress/surya-layout-review'], $plan['models']['layout']['model_arguments']);
        $t->same(['checkpoint' => 'wordpress/surya-layout-review'], $plan['models']['layout']['processor_arguments']);
        $t->same([], $plan['models']['detection']['model_arguments']);
    },
    'propagates explicit device and dtype like load_all_models and rejects missing dtype there' => static function (TestRunner $t): void {
        $planner = new ModelPipelinePlanner();
        $plan = $planner->loadAllModelsPlan('cuda', 'bfloat16');

        $t->same(['device' => 'cuda', 'dtype' => 'bfloat16'], $plan['models']['detection']['model_arguments']);
        $t->same([
            'checkpoint' => 'vikp/texify',
            'device' => 'cuda',
            'dtype' => 'bfloat16',
        ], $plan['models']['texify']['model_arguments']);
        $t->same(['device' => 'cuda', 'dtype' => 'bfloat16'], $plan['models']['table_recognition']['model_arguments']);
        $t->throws(InvalidArgumentException::class, static fn () => $planner->loadAllModelsPlan('cuda'));
    },
    'maps flush_cuda_memory cuda-only empty cache boundary' => static function (TestRunner $t): void {
        $cpuPlan = (new ModelPipelinePlanner())->flushCudaMemoryPlan();
        $cudaPlan = (new ModelPipelinePlanner(new MarkerSettings(['TORCH_DEVICE' => 'cuda'])))->flushCudaMemoryPlan();

        $t->same('cpu', $cpuPlan['torch_device_model']);
        $t->same(false, $cpuPlan['calls_empty_cache']);
        $t->same(null, $cpuPlan['upstream_call']);
        $t->same('cuda', $cudaPlan['torch_device_model']);
        $t->same(true, $cudaPlan['calls_empty_cache']);
        $t->same('torch.cuda.empty_cache', $cudaPlan['upstream_call']);
    },
];

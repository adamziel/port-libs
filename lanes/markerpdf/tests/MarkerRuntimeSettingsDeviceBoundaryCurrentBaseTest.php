<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;

return [
    'records settings.py computed device and dtype boundaries without probing torch' => static function (TestRunner $t): void {
        $default = (new MarkerSettings())->runtimeDevicePreflightPlan();
        $cuda = (new MarkerSettings())->runtimeDevicePreflightPlan(['TORCH_DEVICE' => 'cuda']);
        $instanceCuda = (new MarkerSettings(['TORCH_DEVICE' => 'cuda']))->runtimeDevicePreflightPlan();
        $uppercaseCuda = (new MarkerSettings())->runtimeDevicePreflightPlan(['TORCH_DEVICE' => 'CUDA']);
        $mps = (new MarkerSettings())->runtimeDevicePreflightPlan(['TORCH_DEVICE' => 'mps']);

        $t->same('markerpdf.settings_runtime_device_preflight.v1', $default['schema']);
        $t->contains('marker/settings.py', $default['source']);
        $t->same('cpu', $default['settings']['TORCH_DEVICE_MODEL']);
        $t->same(false, $default['settings']['CUDA']);
        $t->same('float32', $default['settings']['MODEL_DTYPE']);
        $t->same('float32', $default['settings']['TEXIFY_DTYPE']);
        $t->same(false, $default['computed_fields']['explicit_torch_device_preserved']);
        $t->same('native_cpu_fallback_without_torch_probe', $default['computed_fields']['torch_device_model_source']);
        $t->same(false, $default['computed_fields']['native_torch_backend_probe_executed']);
        $t->same(false, $default['computed_fields']['torch_cuda_is_available_probe_reached']);
        $t->same(false, $default['computed_fields']['torch_mps_is_available_probe_reached']);
        $t->same(false, $default['executes_python_or_models']);
        $t->same(false, $default['executes_torch_backend_probe']);
        $t->same(false, $default['executes_cuda_probe']);
        $t->same(false, $default['executes_mps_probe']);

        $t->same('cuda', $cuda['settings']['TORCH_DEVICE_MODEL']);
        $t->same(true, $cuda['settings']['CUDA']);
        $t->same('bfloat16', $cuda['settings']['MODEL_DTYPE']);
        $t->same('float16', $cuda['settings']['TEXIFY_DTYPE']);
        $t->same(true, $cuda['computed_fields']['explicit_torch_device_preserved']);
        $t->same('explicit_TORCH_DEVICE', $cuda['computed_fields']['torch_device_model_source']);
        $t->same('cuda', $instanceCuda['settings']['TORCH_DEVICE_MODEL']);
        $t->same('bfloat16', $instanceCuda['settings']['MODEL_DTYPE']);

        $t->same('CUDA', $uppercaseCuda['settings']['TORCH_DEVICE_MODEL']);
        $t->same(false, $uppercaseCuda['settings']['CUDA']);
        $t->same('float32', $uppercaseCuda['settings']['MODEL_DTYPE']);
        $t->same('float16', $uppercaseCuda['settings']['TEXIFY_DTYPE']);
        $t->same(true, $uppercaseCuda['computed_fields']['cuda_membership_is_case_sensitive']);
        $t->same('torch.bfloat16 if TORCH_DEVICE_MODEL == "cuda" else torch.float32', $uppercaseCuda['computed_fields']['model_dtype_expression']);

        $t->same('mps', $mps['settings']['TORCH_DEVICE_MODEL']);
        $t->same(false, $mps['settings']['CUDA']);
        $t->same('float32', $mps['settings']['MODEL_DTYPE']);
        $t->same('float16', $mps['settings']['TEXIFY_DTYPE']);
        $t->same('settings.TORCH_DEVICE == "mps" or settings.TORCH_DEVICE_MODEL == "mps"', $mps['runtime_consumers']['convert_py_mps_branch_expression']);
    },
    'records settings environment coercion extra ignore and local env read boundary' => static function (TestRunner $t): void {
        $plan = (new MarkerSettings())->runtimeDevicePreflightPlan([
            'PDFTEXT_CPU_WORKERS' => '2',
            'OCR_ALL_PAGES' => 'yes',
            'EXTRACT_IMAGES' => 'off',
            'PAGINATE_OUTPUT' => 'on',
            'UNKNOWN_MARKER_SETTING' => 'ignored',
        ]);

        $t->same(2, $plan['settings']['PDFTEXT_CPU_WORKERS']);
        $t->same(true, $plan['settings']['OCR_ALL_PAGES']);
        $t->same(false, $plan['settings']['EXTRACT_IMAGES']);
        $t->same(true, $plan['settings']['PAGINATE_OUTPUT']);
        $t->same(['PDFTEXT_CPU_WORKERS', 'OCR_ALL_PAGES', 'EXTRACT_IMAGES', 'PAGINATE_OUTPUT', 'UNKNOWN_MARKER_SETTING'], $plan['environment_review']['provided_environment_keys']);
        $t->same(['PDFTEXT_CPU_WORKERS', 'OCR_ALL_PAGES', 'EXTRACT_IMAGES', 'PAGINATE_OUTPUT'], $plan['environment_review']['known_environment_keys']);
        $t->same(['UNKNOWN_MARKER_SETTING'], $plan['environment_review']['ignored_environment_keys']);
        $t->same('ignore', $plan['environment_review']['extra_policy']);
        $t->same('local.env', $plan['environment_review']['upstream_env_file_name']);
        $t->same(false, $plan['environment_review']['native_reads_env_file']);
        $t->same(false, $plan['environment_review']['native_reads_process_environment']);
        $t->same(true, $plan['environment_review']['explicit_environment_argument_only']);
        $t->same(true, $plan['environment_review']['extra_unknown_settings_ignored']);
        $t->same(false, $plan['executes_python_or_models']);
        $t->same(false, $plan['executes_streamlit']);
        $t->same(false, $plan['executes_fastapi']);
        $t->same(false, $plan['executes_external_pdf_tools']);
    },
];

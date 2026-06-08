<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$settings = new MarkerSettings();
$uppercaseMps = $settings->runtimeDevicePreflightPlan([
    'TORCH_DEVICE' => 'MPS',
    'PDFTEXT_CPU_WORKERS' => '1',
    'OCR_ALL_PAGES' => 'no',
    'UNKNOWN_MARKER_SETTING' => 'ignored',
]);
$cuda = $settings->runtimeDevicePreflightPlan([
    'TORCH_DEVICE' => 'cuda',
    'PDFTEXT_CPU_WORKERS' => '1',
]);

if ($uppercaseMps['executes_python_or_models'] !== false
    || $uppercaseMps['executes_torch_backend_probe'] !== false
    || $uppercaseMps['executes_multiprocessing'] !== false
    || $uppercaseMps['executes_external_pdf_tools'] !== false
) {
    throw new RuntimeException('Runtime settings preflight must stay review-only for WordPress imports.');
}

if ($uppercaseMps['settings']['TORCH_DEVICE_MODEL'] !== 'MPS'
    || $uppercaseMps['settings']['CUDA'] !== false
    || $uppercaseMps['settings']['MODEL_DTYPE'] !== 'float32'
    || $uppercaseMps['settings']['TEXIFY_DTYPE'] !== 'float16'
) {
    throw new RuntimeException('Uppercase MPS settings boundary did not preserve upstream computed-field behavior.');
}

if ($cuda['settings']['TORCH_DEVICE_MODEL'] !== 'cuda'
    || $cuda['settings']['CUDA'] !== true
    || $cuda['settings']['MODEL_DTYPE'] !== 'bfloat16'
) {
    throw new RuntimeException('CUDA settings boundary did not preserve upstream computed dtype behavior.');
}

if ($uppercaseMps['environment_review']['ignored_environment_keys'] !== ['UNKNOWN_MARKER_SETTING']
    || $uppercaseMps['environment_review']['native_reads_env_file'] !== false
) {
    throw new RuntimeException('Expected unknown settings to be ignored without reading local.env.');
}

echo json_encode([
    'scenario' => 'wordpress-marker-runtime-settings-device-boundary-currentbase',
    'source' => $uppercaseMps['source'],
    'uppercase_mps_device_model' => $uppercaseMps['settings']['TORCH_DEVICE_MODEL'],
    'uppercase_mps_cuda' => $uppercaseMps['settings']['CUDA'],
    'uppercase_mps_model_dtype' => $uppercaseMps['settings']['MODEL_DTYPE'],
    'uppercase_mps_texify_dtype' => $uppercaseMps['settings']['TEXIFY_DTYPE'],
    'cuda_device_model' => $cuda['settings']['TORCH_DEVICE_MODEL'],
    'cuda_model_dtype' => $cuda['settings']['MODEL_DTYPE'],
    'native_torch_backend_probe_executed' => $uppercaseMps['computed_fields']['native_torch_backend_probe_executed'],
    'ignored_environment_keys' => $uppercaseMps['environment_review']['ignored_environment_keys'],
    'native_reads_env_file' => $uppercaseMps['environment_review']['native_reads_env_file'],
    'executes_python_or_models' => $uppercaseMps['executes_python_or_models'],
    'executes_torch_backend_probe' => $uppercaseMps['executes_torch_backend_probe'],
    'executes_multiprocessing' => $uppercaseMps['executes_multiprocessing'],
    'executes_external_pdf_tools' => $uppercaseMps['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

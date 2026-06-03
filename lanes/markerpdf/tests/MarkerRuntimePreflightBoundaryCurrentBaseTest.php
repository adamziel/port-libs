<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OutputWriter;
use PortLibs\MarkerPDF\SingleDocumentConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-preflight-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf runtime preflight folder.');
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
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            unlink($child);
        }
    }

    rmdir($path);
};

return [
    'records convert_single runtime preflight before model loading without executing models' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $output = $makeTempDir();
        try {
            (new OutputWriter())->saveMarkdown(
                $output,
                'annual.report.pdf',
                '<!-- wp:paragraph --><p>Existing import.</p><!-- /wp:paragraph -->',
                [],
                ['title' => 'Existing Import']
            );

            $filename = '/wp/uploads/2026/annual.report.pdf';
            $plan = (new SingleDocumentConverter())->runtimePreflightPlan(
                $filename,
                $output,
                maxPages: 3,
                startPage: 1,
                languages: 'English, Spanish,de',
                batchMultiplier: 4
            );

            $t->same('markerpdf.convert_single_runtime_preflight.v1', $plan['schema']);
            $t->contains('convert_single.py::main', $plan['source']);
            $t->contains('load_all_models', $plan['source']);
            $t->same('annual.report.pdf', $plan['filename']);
            $t->same($filename, $plan['filepath']);
            $t->same($output, $plan['output_folder']);
            $t->same($output . DIRECTORY_SEPARATOR . 'annual.report', $plan['subfolder']);
            $t->same($output . DIRECTORY_SEPARATOR . 'annual.report' . DIRECTORY_SEPARATOR . 'annual.report.md', $plan['markdown_path']);
            $t->same(['PYTORCH_ENABLE_MPS_FALLBACK' => '1'], $plan['environment']);
            $t->same([
                'configure_logging',
                'parse_args',
                'parse_langs',
                'load_all_models',
                'convert_single_pdf',
                'save_markdown',
                'print_saved_folder',
                'print_total_time',
            ], $plan['preflight_order']);

            $t->same(3, $plan['options']['max_pages']);
            $t->same(1, $plan['options']['start_page']);
            $t->same(['English', ' Spanish', 'de'], $plan['options']['langs']);
            $t->same(4, $plan['options']['batch_multiplier']);
            $t->same('load_all_models', $plan['model_boundary']['load_function']);
            $t->same(true, $plan['model_boundary']['loads_all_models_before_conversion']);
            $t->same(true, $plan['model_boundary']['passes_model_list_to_convert_single_pdf']);
            $t->same(false, $plan['model_boundary']['native_plan_loads_models']);
            $t->same(true, $plan['model_boundary']['upstream_model_execution_required']);
            $t->same('convert_single_pdf', $plan['conversion_call']['function']);
            $t->same($filename, $plan['conversion_call']['receives_filename']);
            $t->same([
                'max_pages' => 3,
                'langs' => ['English', ' Spanish', 'de'],
                'batch_multiplier' => 4,
                'start_page' => 1,
            ], $plan['conversion_call']['receives_options']);
            $t->same(null, $plan['conversion_call']['metadata_argument_source']);
            $t->contains('settings.OCR_ALL_PAGES', $plan['conversion_call']['ocr_all_pages_argument_source']);

            $outputPolicy = $plan['output_policy'];
            $t->same('save_markdown', $outputPolicy['function']);
            $t->same(true, $outputPolicy['uses_basename_after_conversion']);
            $t->same(true, $outputPolicy['existing_markdown']);
            $t->same(false, $outputPolicy['skips_existing_markdown']);
            $t->same(false, $outputPolicy['min_length_preflight']);
            $t->same(false, $outputPolicy['filetype_preflight_before_model_load']);
            $t->same(true, $outputPolicy['saves_empty_output']);
            $t->same('Saved markdown to the {subfolder_path} folder', $outputPolicy['saved_folder_message']);
            $t->same('Total time: ', $outputPolicy['elapsed_message_prefix']);
            $t->same(true, $plan['review_only']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_streamlit']);
            $t->same(false, $plan['executes_fastapi']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($output);
        }
    },
    'keeps convert_single defaults distinct from batch process preflight gates' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $output = $makeTempDir();
        try {
            $plan = (new SingleDocumentConverter())->runtimePreflightPlan(
                '/wp/uploads/editor-checklist.pdf',
                $output
            );

            $t->same(['max_pages' => null, 'start_page' => null, 'langs' => null, 'batch_multiplier' => 2], $plan['options']);
            $t->same(false, $plan['output_policy']['existing_markdown']);
            $t->same(false, $plan['output_policy']['skips_existing_markdown']);
            $t->same(false, $plan['output_policy']['min_length_preflight']);
            $t->same(false, $plan['output_policy']['filetype_preflight_before_model_load']);
            $t->same(true, $plan['output_policy']['saves_empty_output']);
            $t->same(false, $plan['executes_python_or_models']);

            $single = new SingleDocumentConverter();
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $single->runtimePreflightPlan('', $output)
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $single->runtimePreflightPlan('/wp/uploads/report.pdf', '')
            );
        } finally {
            $removeTree($output);
        }
    },
];

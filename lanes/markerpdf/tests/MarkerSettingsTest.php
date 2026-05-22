<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;

return [
    'exposes marker upstream default settings needed by the native pipeline' => static function (TestRunner $t): void {
        $settings = new MarkerSettings();

        $t->same(96, $settings->get('IMAGE_DPI'));
        $t->same(true, $settings->extractImages());
        $t->same(false, $settings->paginateOutput());
        $t->same(true, $settings->get('FLATTEN_PDF'));
        $t->same('English', $settings->get('DEFAULT_LANG'));
        $t->same(['Page-footer', 'Page-header', 'Picture'], $settings->badSpanTypes());
        $t->same("------------------------------------------------\n\n", $settings->pageSeparator());
        $t->same('Text', $settings->get('DEFAULT_BLOCK_TYPE'));
        $t->same(dirname(__DIR__), $settings->get('BASE_DIR'));
        $t->same(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'debug_data', $settings->get('DEBUG_DATA_FOLDER'));
        $t->same(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'fonts', $settings->get('FONT_DIR'));
    },
    'maps supported filetypes from marker settings' => static function (TestRunner $t): void {
        $settings = new MarkerSettings();

        $t->true($settings->supportsFiletype('application/pdf'));
        $t->same('pdf', $settings->extensionForFiletype('application/pdf'));
        $t->true(!$settings->supportsFiletype('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        $t->same(null, $settings->extensionForFiletype('image/png'));
    },
    'computes native torch dtype helpers with upstream cpu fallback semantics' => static function (TestRunner $t): void {
        $cpu = new MarkerSettings();
        $cuda = new MarkerSettings(['TORCH_DEVICE' => 'cuda']);
        $mps = new MarkerSettings(['TORCH_DEVICE' => 'mps']);

        $t->same('cpu', $cpu->torchDeviceModel());
        $t->same(false, $cpu->cuda());
        $t->same('float32', $cpu->modelDtype());
        $t->same('float32', $cpu->texifyDtype());
        $t->same(true, $cuda->cuda());
        $t->same('bfloat16', $cuda->modelDtype());
        $t->same('float16', $cuda->texifyDtype());
        $t->same('float32', $mps->modelDtype());
        $t->same('float16', $mps->texifyDtype());
    },
    'coerces environment-style overrides and ignores unknown settings like pydantic extra ignore' => static function (TestRunner $t): void {
        $settings = MarkerSettings::fromEnvironment([
            'EXTRACT_IMAGES' => 'false',
            'PAGINATE_OUTPUT' => '1',
            'IMAGE_DPI' => '144',
            'HEADING_MERGE_THRESHOLD' => '0.33',
            'BAD_SPAN_TYPES' => 'Caption,Footnote',
            'UNKNOWN_MARKER_SETTING' => 'ignored',
        ]);

        $t->same(false, $settings->extractImages());
        $t->same(true, $settings->paginateOutput());
        $t->same(144, $settings->get('IMAGE_DPI'));
        $t->same(0.33, $settings->get('HEADING_MERGE_THRESHOLD'));
        $t->same(['Caption', 'Footnote'], $settings->badSpanTypes());
        $t->throws(InvalidArgumentException::class, static fn () => $settings->get('UNKNOWN_MARKER_SETTING'));
    },
    'validates the upstream OCR engine literal boundary' => static function (TestRunner $t): void {
        $t->same('surya', (new MarkerSettings())->get('OCR_ENGINE'));
        $t->same('ocrmypdf', (new MarkerSettings(['OCR_ENGINE' => 'ocrmypdf']))->get('OCR_ENGINE'));
        $t->throws(InvalidArgumentException::class, static fn () => new MarkerSettings(['OCR_ENGINE' => 'unknown']));
    },
    'drives a WordPress PDF import preflight without Python model dependencies' => static function (TestRunner $t): void {
        $settings = MarkerSettings::fromEnvironment([
            'EXTRACT_IMAGES' => 'off',
            'PAGINATE_OUTPUT' => 'on',
            'OCR_ALL_PAGES' => 'yes',
        ]);

        $t->true($settings->supportsFiletype('application/pdf'));
        $t->same(false, $settings->extractImages());
        $t->same(true, $settings->paginateOutput());
        $t->same(true, $settings->get('OCR_ALL_PAGES'));
        $t->same('cpu', $settings->torchDeviceModel());
    },
];

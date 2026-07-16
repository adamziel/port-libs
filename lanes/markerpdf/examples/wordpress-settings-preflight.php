<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$settings = MarkerSettings::fromEnvironment([
    'EXTRACT_IMAGES' => 'off',
    'PAGINATE_OUTPUT' => 'on',
    'OCR_ALL_PAGES' => 'no',
    'IMAGE_DPI' => '144',
]);

echo json_encode([
    'scenario' => 'wordpress-pdf-import-settings-preflight',
    'accepts_pdf' => $settings->supportsFiletype('application/pdf'),
    'rejects_docx' => !$settings->supportsFiletype('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    'extension' => $settings->extensionForFiletype('application/pdf'),
    'extract_images' => $settings->extractImages(),
    'paginate_output' => $settings->paginateOutput(),
    'torch_device_model' => $settings->torchDeviceModel(),
    'page_separator' => $settings->pageSeparator(),
    'bad_span_types' => $settings->badSpanTypes(),
    'image_dpi' => $settings->get('IMAGE_DPI'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

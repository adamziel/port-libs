<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\SingleDocumentConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-single-trailing-basename-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerPDF single trailing-basename test folder.');
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
    'preserves convert_single.py empty os.path.basename output for trailing separator paths in preflight' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $output = $makeTempDir();
        try {
            file_put_contents($output . DIRECTORY_SEPARATOR . '.md', 'previous trailing basename import');

            $plan = (new SingleDocumentConverter())->runtimePreflightPlan(
                '/wp-content/uploads/2026/editorial-checklist.pdf/',
                $output,
                maxPages: 1,
                startPage: 0,
                languages: 'English'
            );

            $t->same('markerpdf.convert_single_runtime_preflight.v1', $plan['schema']);
            $t->same('', $plan['filename']);
            $t->same('/wp-content/uploads/2026/editorial-checklist.pdf/', $plan['filepath']);
            $t->same($output . DIRECTORY_SEPARATOR, $plan['subfolder']);
            $t->same($output . DIRECTORY_SEPARATOR . '.md', $plan['markdown_path']);
            $t->same('os.path.basename(fname) after convert_single_pdf returns', $plan['output_policy']['basename_source']);
            $t->same(true, $plan['output_policy']['empty_basename_after_trailing_separator']);
            $t->same(true, $plan['output_policy']['existing_markdown']);
            $t->same(false, $plan['output_policy']['skips_existing_markdown']);
            $t->same('/wp-content/uploads/2026/editorial-checklist.pdf/', $plan['conversion_call']['receives_filename']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($output);
        }
    },
    'saves supplied single-document conversions with upstream trailing-separator basename semantics' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $output = $makeTempDir();
        try {
            $seenFilename = null;
            $result = (new SingleDocumentConverter())->convert(
                '/wp-content/uploads/2026/editorial-checklist.pdf/',
                $output,
                static function (string $filename, array $options) use (&$seenFilename): array {
                    $seenFilename = $filename;

                    return [
                        'text' => "<!-- wp:paragraph -->\n<p>Trailing separator import boundary.</p>\n<!-- /wp:paragraph -->",
                        'images' => ['0_image_0.png' => 'PNG-BYTES'],
                        'metadata' => [
                            'scenario' => 'wordpress-markerpdf-single-trailing-basename',
                            'max_pages' => $options['max_pages'],
                            'langs' => $options['langs'],
                        ],
                    ];
                },
                maxPages: 1,
                languages: 'English'
            );

            $t->same('/wp-content/uploads/2026/editorial-checklist.pdf/', $seenFilename);
            $t->same('converted', $result['status']);
            $t->same('', $result['filename']);
            $t->same($output . DIRECTORY_SEPARATOR, $result['output_folder']);
            $t->same($output . DIRECTORY_SEPARATOR . '.md', $result['markdown']);
            $t->same(['0_image_0.png'], $result['images']);
            $t->same(['English'], $result['options']['langs']);
            $t->true(is_file($output . DIRECTORY_SEPARATOR . '.md'));
            $t->true(is_file($output . DIRECTORY_SEPARATOR . '_meta.json'));
            $t->true(is_file($output . DIRECTORY_SEPARATOR . '0_image_0.png'));
            $t->contains('<!-- wp:paragraph -->', (string) file_get_contents($output . DIRECTORY_SEPARATOR . '.md'));
            $t->contains(
                '"scenario": "wordpress-markerpdf-single-trailing-basename"',
                (string) file_get_contents($output . DIRECTORY_SEPARATOR . '_meta.json')
            );
        } finally {
            $removeTree($output);
        }
    },
];

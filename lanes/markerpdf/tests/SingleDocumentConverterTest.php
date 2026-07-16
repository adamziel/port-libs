<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\SingleDocumentConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-single-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf single-convert test folder.');
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
    'parses convert_single.py comma language argument without trimming' => static function (TestRunner $t): void {
        $single = new SingleDocumentConverter();

        $t->same(null, $single->parseLanguages(null));
        $t->same(null, $single->parseLanguages(''));
        $t->same(['English', ' Spanish', 'de'], $single->parseLanguages('English, Spanish,de'));
    },
    'passes upstream single-document options to a supplied native converter and saves artifacts' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $output = $makeTempDir();
        try {
            $seen = [];
            $single = new SingleDocumentConverter();
            $result = $single->convert(
                '/tmp/uploads/annual.report.pdf',
                $output,
                static function (string $filename, array $options) use (&$seen): array {
                    $seen = ['filename' => $filename, 'options' => $options];

                    return [
                        '# Imported Report',
                        ['0_image_0.png' => 'PNG-BYTES'],
                        ['pages' => 2, 'langs' => $options['langs']],
                    ];
                },
                maxPages: 2,
                startPage: 1,
                languages: 'English,Spanish',
                batchMultiplier: 3
            );

            $t->same('/tmp/uploads/annual.report.pdf', $seen['filename']);
            $t->same(['English', 'Spanish'], $seen['options']['langs']);
            $t->same(2, $seen['options']['max_pages']);
            $t->same(1, $seen['options']['start_page']);
            $t->same(3, $seen['options']['batch_multiplier']);
            $t->same('converted', $result['status']);
            $t->same('annual.report.pdf', $result['filename']);
            $t->same(['0_image_0.png'], $result['images']);
            $t->true(is_float($result['elapsed_seconds']));
            $t->true(is_file($result['markdown']));
            $t->contains('# Imported Report', (string) file_get_contents($result['markdown']));
            $t->contains('"langs": [', (string) file_get_contents(dirname($result['markdown']) . DIRECTORY_SEPARATOR . 'annual.report_meta.json'));
            $t->same('PNG-BYTES', file_get_contents(dirname($result['markdown']) . DIRECTORY_SEPARATOR . '0_image_0.png'));
        } finally {
            $removeTree($output);
        }
    },
    'saves empty single-document output like convert_single.py instead of applying batch skips' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $output = $makeTempDir();
        try {
            $single = new SingleDocumentConverter();
            $result = $single->convert(
                '/tmp/uploads/blank.pdf',
                $output,
                static fn (): array => ['full_text' => '', 'images' => [], 'out_metadata' => ['empty' => true]]
            );

            $t->same('converted', $result['status']);
            $t->true(is_file($result['markdown']));
            $t->same('', file_get_contents($result['markdown']));
            $t->contains('"empty": true', (string) file_get_contents(dirname($result['markdown']) . DIRECTORY_SEPARATOR . 'blank_meta.json'));
        } finally {
            $removeTree($output);
        }
    },
    'renders a WordPress single-upload import artifact with convert_single.py options' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $output = $makeTempDir();
        try {
            $single = new SingleDocumentConverter();
            $result = $single->convert(
                '/tmp/uploads/editorial-checklist.pdf',
                $output,
                static fn (string $filename, array $options): array => [
                    'text' => "<!-- wp:paragraph -->\n<p>Imported " . htmlspecialchars(basename($filename), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " for editorial review.</p>\n<!-- /wp:paragraph -->",
                    'images' => [],
                    'metadata' => [
                        'scenario' => 'wordpress-markerpdf-single-convert',
                        'start_page' => $options['start_page'],
                        'max_pages' => $options['max_pages'],
                        'langs' => $options['langs'],
                    ],
                ],
                maxPages: 1,
                startPage: 0,
                languages: 'English'
            );

            $markdown = (string) file_get_contents($result['markdown']);
            $metadata = (string) file_get_contents(dirname($result['markdown']) . DIRECTORY_SEPARATOR . 'editorial-checklist_meta.json');

            $t->contains('<!-- wp:paragraph -->', $markdown);
            $t->contains('editorial-checklist.pdf', $markdown);
            $t->contains('"scenario": "wordpress-markerpdf-single-convert"', $metadata);
            $t->contains('"start_page": 0', $metadata);
            $t->same(['English'], $result['options']['langs']);
        } finally {
            $removeTree($output);
        }
    },
    'rejects malformed supplied conversion payloads before writing output' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $output = $makeTempDir();
        try {
            $t->throws(
                InvalidArgumentException::class,
                static fn () => (new SingleDocumentConverter())->convert('/tmp/bad.pdf', $output, static fn (): array => [
                    'text' => 'bad',
                    'images' => 'not-an-array',
                    'metadata' => [],
                ])
            );
        } finally {
            $removeTree($output);
        }
    },
];

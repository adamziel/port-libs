<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OutputWriter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-output-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf output test folder.');
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
    'derives upstream output subfolder and markdown paths from the final extension' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $writer = new OutputWriter();

            $t->same($root . DIRECTORY_SEPARATOR . 'migration.v1', $writer->getSubfolderPath($root, 'migration.v1.pdf'));
            $t->same(
                $root . DIRECTORY_SEPARATOR . 'migration.v1' . DIRECTORY_SEPARATOR . 'migration.v1.md',
                $writer->getMarkdownFilepath($root, 'migration.v1.pdf')
            );
            $t->same($root . DIRECTORY_SEPARATOR . 'README' . DIRECTORY_SEPARATOR . 'README.md', $writer->getMarkdownFilepath($root, 'README'));
        } finally {
            $removeTree($root);
        }
    },
    'saves markdown metadata and image payloads like marker output save_markdown' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $writer = new OutputWriter();
            $subfolder = $writer->saveMarkdown(
                $root,
                'wp-export.pdf',
                "# Imported PDF\n\nClean WordPress blocks.",
                [
                    '0_image_0.png' => "PNG-BYTES",
                    '0_image_1.png' => ['bytes' => "PNG-BYTES-2"],
                ],
                [
                    'pages' => 1,
                    'successful_ocr' => 0,
                    'source' => 'wp-export.pdf',
                ]
            );

            $t->same($root . DIRECTORY_SEPARATOR . 'wp-export', $subfolder);
            $t->true($writer->markdownExists($root, 'wp-export.pdf'));
            $t->same("# Imported PDF\n\nClean WordPress blocks.", file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'wp-export.md'));
            $t->contains('"successful_ocr": 0', (string) file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'wp-export_meta.json'));
            $t->same('PNG-BYTES', file_get_contents($subfolder . DIRECTORY_SEPARATOR . '0_image_0.png'));
            $t->same('PNG-BYTES-2', file_get_contents($subfolder . DIRECTORY_SEPARATOR . '0_image_1.png'));
        } finally {
            $removeTree($root);
        }
    },
    'rejects image payloads without native writable bytes or save support' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $t->throws(
                InvalidArgumentException::class,
                static fn () => (new OutputWriter())->saveMarkdown($root, 'bad.pdf', 'text', ['0_image_0.png' => ['path' => '/tmp/image.png']], [])
            );
        } finally {
            $removeTree($root);
        }
    },
    'persists a WordPress import handoff artifact with markdown images and review metadata' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $writer = new OutputWriter();
            $markdown = <<<'MD'
<!-- wp:paragraph -->
<p>Imported PDF media summary.</p>
<!-- /wp:paragraph -->

<!-- wp:image -->
<figure class="wp-block-image"><img src="3_image_0.png" alt="3_image_0.png"/></figure>
<!-- /wp:image -->
MD;
            $subfolder = $writer->saveMarkdown(
                $root,
                'wordpress-media-import.pdf',
                $markdown,
                ['3_image_0.png' => "PNG"],
                [
                    'scenario' => 'wordpress-pdf-output-artifact',
                    'pages' => 4,
                    'images' => ['3_image_0.png'],
                ]
            );

            $t->true($writer->markdownExists($root, 'wordpress-media-import.pdf'));
            $t->contains('<!-- wp:image -->', (string) file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'wordpress-media-import.md'));
            $t->contains('"scenario": "wordpress-pdf-output-artifact"', (string) file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'wordpress-media-import_meta.json'));
        } finally {
            $removeTree($root);
        }
    },
    'sanitizes current-base image artifact filenames and rewrites markdown metadata references' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $writer = new OutputWriter();
            $unsafeImage = '../WP-cover?.jpeg';
            $markdown = <<<'MD'
<!-- wp:image -->
<figure class="wp-block-image"><img src="../WP-cover?.jpeg" alt="../WP-cover?.jpeg"/></figure>
<!-- /wp:image -->

![../WP-cover?.jpeg](../WP-cover?.jpeg)
MD;

            $subfolder = $writer->saveMarkdown(
                $root,
                'wordpress-sanitized-media.pdf',
                $markdown,
                [
                    $unsafeImage => 'PNG-TRAVERSAL',
                    '0_image_0.png' => 'PNG-UPSTREAM',
                ],
                [
                    'scenario' => 'wordpress-pdf-output-artifact-sanitize-images',
                    'images' => [$unsafeImage, '0_image_0.png'],
                    'image_map' => [
                        $unsafeImage => ['filename' => $unsafeImage],
                    ],
                ]
            );

            $markdownOut = (string) file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'wordpress-sanitized-media.md');
            $metadataOut = (string) file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'wordpress-sanitized-media_meta.json');

            $t->same('PNG-TRAVERSAL', file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'WP-cover.png'));
            $t->same('PNG-UPSTREAM', file_get_contents($subfolder . DIRECTORY_SEPARATOR . '0_image_0.png'));
            $t->true(!is_file($root . DIRECTORY_SEPARATOR . 'WP-cover?.jpeg'));
            $t->contains('<img src="WP-cover.png" alt="WP-cover.png"', $markdownOut);
            $t->contains('![WP-cover.png](WP-cover.png)', $markdownOut);
            $t->true(!str_contains($markdownOut, $unsafeImage));
            $t->contains('"WP-cover.png"', $metadataOut);
            $t->true(!str_contains($metadataOut, $unsafeImage));
        } finally {
            $removeTree($root);
        }
    },
    'deduplicates colliding sanitized image artifact filenames before saving' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $subfolder = (new OutputWriter())->saveMarkdown(
                $root,
                'wordpress-colliding-media.pdf',
                'Image collision import.',
                [
                    '../cover.png' => 'PNG-FIRST',
                    'cover?.jpg' => 'PNG-SECOND',
                    'cover.png' => 'PNG-THIRD',
                ],
                []
            );

            $t->same('PNG-FIRST', file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'cover.png'));
            $t->same('PNG-SECOND', file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'cover_2.png'));
            $t->same('PNG-THIRD', file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'cover_3.png'));
            $t->true(!is_file($root . DIRECTORY_SEPARATOR . 'cover.png'));
        } finally {
            $removeTree($root);
        }
    },
];

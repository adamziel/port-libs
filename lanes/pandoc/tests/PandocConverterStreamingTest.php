<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;

return [
    'streams EPUB WordPress block markup without changing its output' => static function (TestRunner $t): void {
        $path = dirname(__DIR__, 3) . '/pandoc-showcase/samples/epub-gutenberg-ulysses-ulysses.epub';
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read EPUB streaming fixture');
        }

        $expected = PandocConverter::convert($bytes, 'epub', 'wordpress');
        $chunks = [];
        PandocConverter::convertToSink($bytes, 'epub', 'wordpress', static function (string $chunk) use (&$chunks): void {
            $chunks[] = $chunk;
        });

        $t->same($expected, implode('', $chunks));
        $t->true(count($chunks) > 64, 'EPUB sink should emit incrementally rather than retain one final string');
    },

    'uses the generic sink path when EPUB metadata review is requested' => static function (TestRunner $t): void {
        $path = dirname(__DIR__, 3) . '/pandoc-showcase/samples/epub-features-features.epub';
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read EPUB metadata streaming fixture');
        }

        $options = ['writerOptions' => ['includeMetadata' => true]];
        $expected = PandocConverter::convert($bytes, 'epub', 'wordpress', $options);
        $actual = '';
        PandocConverter::convertToSink($bytes, 'epub', 'wordpress', static function (string $chunk) use (&$actual): void {
            $actual .= $chunk;
        }, $options);

        $t->same($expected, $actual);
    },

    'streams EPUB file inputs without changing WordPress block markup' => static function (TestRunner $t): void {
        $path = dirname(__DIR__, 3) . '/pandoc-showcase/samples/epub-gutenberg-ulysses-ulysses.epub';
        $expected = PandocConverter::convertFile($path, 'epub', 'wordpress');
        $actual = '';
        PandocConverter::convertFileToSink($path, 'epub', 'wordpress', static function (string $chunk) use (&$actual): void {
            $actual .= $chunk;
        });

        $t->same($expected, $actual);
    },
];

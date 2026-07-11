<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;

$tests = [];

$tests['keeps file-backed conversion byte-identical to byte-backed conversion'] =
    static function (TestRunner $t): void {
        $temporaryFiles = [];
        $writeTemporaryFile = static function (string $suffix, string $contents) use (&$temporaryFiles): string {
            $path = tempnam(sys_get_temp_dir(), 'pandoc-file-input-');
            if (!is_string($path)) {
                throw new RuntimeException('Unable to create temporary input file.');
            }
            $renamed = $path . $suffix;
            if (!rename($path, $renamed) || file_put_contents($renamed, $contents) === false) {
                throw new RuntimeException('Unable to write temporary input file.');
            }
            $temporaryFiles[] = $renamed;

            return $renamed;
        };

        try {
            $cases = [
                [
                    'format' => 'html',
                    'path' => $writeTemporaryFile('.html', '<!doctype html><html><body><h1>Title</h1><p>A <strong>stable</strong> paragraph.</p></body></html>'),
                ],
                [
                    'format' => 'csv',
                    'path' => $writeTemporaryFile('.csv', "name,description\nalpha,\"one, two\"\nbeta,three\n"),
                ],
                [
                    'format' => 'latex',
                    'path' => $writeTemporaryFile('.tex', "\\documentclass{article}\n\\begin{document}\n\\section{Title}\nA \\emph{stable} paragraph.\n\\end{document}\n"),
                ],
                [
                    'format' => 'odt',
                    'path' => dirname(__DIR__, 3) . '/pandoc-showcase/samples/odt-oasis-opendocument-schema-OpenDocument-v1.3-os-part3-schema.odt',
                ],
            ];

            foreach ($cases as $case) {
                $bytes = file_get_contents($case['path']);
                if (!is_string($bytes)) {
                    throw new RuntimeException("Unable to read {$case['path']}");
                }
                $fromBytes = PandocConverter::convert($bytes, $case['format'], 'wordpress');
                $fromFile = PandocConverter::convertFile($case['path'], $case['format'], 'wordpress');

                $t->same($fromBytes, $fromFile, "{$case['format']} file conversion must preserve WordPress blocks");
            }
        } finally {
            foreach ($temporaryFiles as $path) {
                @unlink($path);
            }
        }
    };

$tests['streams regular oversized delimited text without changing its ast or rendered output'] =
    static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-file-input-streaming-');
        if (!is_string($path)) {
            throw new RuntimeException('Unable to create temporary delimited text input.');
        }
        $csvPath = $path . '.csv';
        if (!rename($path, $csvPath)) {
            throw new RuntimeException('Unable to name temporary delimited text input.');
        }

        $input = "name,description,count\n" . str_repeat("example,regular unquoted row,42\n", 1250);
        if (file_put_contents($csvPath, $input) === false) {
            throw new RuntimeException('Unable to write temporary delimited text input.');
        }

        try {
            $options = ['sourcePath' => $csvPath];
            $fromBytes = PandocConverter::read($input, 'csv', $options);
            $fromFile = PandocConverter::readFile($csvPath, 'csv', $options);

            $byteAst = serialize($fromBytes);
            $fileAst = serialize($fromFile);
            if ($byteAst !== $fileAst) {
                throw new RuntimeException('Streamed CSV changed the complete AST.');
            }
            $t->same(hash('sha256', $byteAst), hash('sha256', $fileAst), 'streamed CSV must preserve the complete AST');
            foreach (['wordpress', 'html', 'markdown', 'native', 'plain'] as $format) {
                $t->same(
                    PandocConverter::write($fromBytes, $format),
                    PandocConverter::write($fromFile, $format),
                    "streamed CSV must preserve {$format} output"
                );
            }
        } finally {
            @unlink($csvPath);
        }
    };

$tests['keeps no-media file conversion on the file-backed import path'] =
    static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-file-input-no-media-');
        if (!is_string($path)) {
            throw new RuntimeException('Unable to create temporary no-media input.');
        }
        $csvPath = $path . '.csv';
        if (!rename($path, $csvPath)) {
            throw new RuntimeException('Unable to name temporary no-media input.');
        }

        $input = "name,description,count\n" . str_repeat("example,regular unquoted row,42\n", 1250);
        if (file_put_contents($csvPath, $input) === false) {
            throw new RuntimeException('Unable to write temporary no-media input.');
        }

        try {
            $expected = PandocConverter::convertFile($csvPath, 'csv', 'wordpress');
            $result = PandocConverter::convertFileWithMedia($csvPath, 'csv', 'wordpress');

            $t->same($expected, $result['output']);
            $t->same([], $result['media']);
            $t->same([], $result['diagnostics']);
        } finally {
            @unlink($csvPath);
        }
    };

return $tests;

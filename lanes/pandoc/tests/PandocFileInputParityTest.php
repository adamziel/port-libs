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

return $tests;

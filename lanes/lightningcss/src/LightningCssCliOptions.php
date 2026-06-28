<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class LightningCssCliOptions
{
    /**
     * @param list<string> $inputFiles
     * @return array{inputs:list<string>, outputs:array<string, string|null>, outputFile:?string, outputDir:?string}
     */
    public static function planOutputs(
        array $inputFiles,
        ?string $outputFile = null,
        ?string $outputDir = null,
        bool $browserslist = false,
        ?string $targets = null
    ): array {
        if ($browserslist && $targets !== null && trim($targets) !== '') {
            throw new \InvalidArgumentException("The argument '--targets <TARGETS>' cannot be used with '--browserslist'");
        }

        if ($inputFiles === []) {
            throw new \InvalidArgumentException('At least one input file is required.');
        }

        if (count($inputFiles) > 1 && $outputFile !== null && trim($outputFile) !== '') {
            throw new \InvalidArgumentException('Cannot use --output-file with multiple input files.');
        }

        if (count($inputFiles) > 1 && ($outputDir === null || trim($outputDir) === '')) {
            throw new \InvalidArgumentException('Multiple input files require --output-dir.');
        }

        $outputs = [];
        foreach ($inputFiles as $inputFile) {
            $inputFile = (string) $inputFile;
            if ($outputDir !== null && trim($outputDir) !== '') {
                $outputs[$inputFile] = rtrim($outputDir, "/\\") . DIRECTORY_SEPARATOR . basename($inputFile);
            } elseif ($outputFile !== null && trim($outputFile) !== '') {
                $outputs[$inputFile] = $outputFile;
            } else {
                $outputs[$inputFile] = null;
            }
        }

        return [
            'inputs' => array_values(array_map('strval', $inputFiles)),
            'outputs' => $outputs,
            'outputFile' => $outputFile,
            'outputDir' => $outputDir,
        ];
    }

    public static function cssModulesJsonOutputPath(?string $cssModulesOption, ?string $outputFile): ?string
    {
        if ($cssModulesOption !== null && trim($cssModulesOption) !== '') {
            return $cssModulesOption;
        }

        if ($outputFile === null || trim($outputFile) === '') {
            return null;
        }

        $directory = dirname($outputFile);
        $stem = pathinfo(basename($outputFile), PATHINFO_FILENAME);
        $json = $stem . '.json';

        return $directory === '.' ? $json : $directory . DIRECTORY_SEPARATOR . $json;
    }
}

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

    public static function writeOutputFile(string $outputFile, string $contents): void
    {
        $directory = dirname($outputFile);
        if ($directory !== '.' && !is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create output directory: {$directory}");
        }

        if (file_put_contents($outputFile, $contents) === false) {
            throw new \RuntimeException("Unable to write output file: {$outputFile}");
        }
    }

    /**
     * @param array<string, string> $environment
     * @param ?callable(string): bool $isFile
     * @param ?callable(string): string $readFile
     * @return array{source:string, environment:string, queries:list<string>}
     */
    public static function resolveBrowserslistConfig(
        string $currentDirectory,
        array $environment = [],
        ?callable $isFile = null,
        ?callable $readFile = null
    ): array {
        $isFile ??= static fn (string $path): bool => is_file($path);
        $readFile ??= static function (string $path): string {
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new \RuntimeException("Unable to read browserslist config: {$path}");
            }

            return $contents;
        };

        $envName = self::browserslistEnvironment($environment);
        $direct = trim((string) ($environment['BROWSERSLIST'] ?? ''));
        if ($direct !== '') {
            return [
                'source' => 'env:BROWSERSLIST',
                'environment' => $envName,
                'queries' => self::parseBrowserslistQueryLines($direct),
            ];
        }

        $configPath = trim((string) ($environment['BROWSERSLIST_CONFIG'] ?? ''));
        if ($configPath !== '') {
            return self::readBrowserslistConfigFile($configPath, $envName, $readFile($configPath));
        }

        foreach (self::candidateConfigPaths($currentDirectory) as $path) {
            if (!$isFile($path)) {
                continue;
            }

            return self::readBrowserslistConfigFile($path, $envName, $readFile($path));
        }

        return [
            'source' => 'defaults',
            'environment' => $envName,
            'queries' => ['defaults'],
        ];
    }

    /**
     * @param list<string> $queries
     * @return array<string, int|string>
     */
    public static function targetsForBrowserslistQueries(array $queries): array
    {
        $targets = [];
        foreach ($queries as $query) {
            $normalized = strtolower(trim($query));
            if ($normalized === 'safari 4') {
                $targets['safari'] = 4;
            } elseif ($normalized === 'last 1 chrome version' || $normalized === 'defaults') {
                $targets['chrome'] = 120;
            }
        }

        return $targets;
    }

    /**
     * @param array<string, string> $environment
     */
    private static function browserslistEnvironment(array $environment): string
    {
        $browserslistEnv = trim((string) ($environment['BROWSERSLIST_ENV'] ?? ''));
        if ($browserslistEnv !== '') {
            return $browserslistEnv;
        }

        $nodeEnv = trim((string) ($environment['NODE_ENV'] ?? ''));
        if ($nodeEnv !== '') {
            return $nodeEnv;
        }

        return 'production';
    }

    /**
     * @return list<string>
     */
    private static function candidateConfigPaths(string $currentDirectory): array
    {
        $directory = rtrim($currentDirectory, "/\\");
        if ($directory === '') {
            $directory = DIRECTORY_SEPARATOR;
        }

        $paths = [];
        while (true) {
            $paths[] = $directory . DIRECTORY_SEPARATOR . 'browserslist';
            $paths[] = $directory . DIRECTORY_SEPARATOR . '.browserslistrc';
            $paths[] = $directory . DIRECTORY_SEPARATOR . 'package.json';

            $parent = dirname($directory);
            if ($parent === $directory) {
                break;
            }
            $directory = $parent;
        }

        return $paths;
    }

    /**
     * @return array{source:string, environment:string, queries:list<string>}
     */
    private static function readBrowserslistConfigFile(string $path, string $environment, string $contents): array
    {
        $queries = basename($path) === 'package.json'
            ? self::parseBrowserslistPackageJson($contents, $environment)
            : self::parseBrowserslistConfig($contents, $environment);

        return [
            'source' => $path,
            'environment' => $environment,
            'queries' => $queries === [] ? ['defaults'] : $queries,
        ];
    }

    /**
     * @return list<string>
     */
    private static function parseBrowserslistConfig(string $contents, string $environment): array
    {
        $sections = ['defaults' => []];
        $current = 'defaults';

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim(preg_replace('/\s+#.*$/', '', $line) ?? $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^\[([^\]]+)\]$/', $line, $matches)) {
                $current = trim($matches[1]);
                $sections[$current] ??= [];
                continue;
            }

            $sections[$current][] = $line;
        }

        return $sections[$environment] ?? $sections['defaults'];
    }

    /**
     * @return list<string>
     */
    private static function parseBrowserslistPackageJson(string $contents, string $environment): array
    {
        try {
            $package = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($package) || !array_key_exists('browserslist', $package)) {
            return [];
        }

        $config = $package['browserslist'];
        if (is_string($config)) {
            return self::parseBrowserslistQueryLines($config);
        }

        if (self::isStringList($config)) {
            return array_values($config);
        }

        if (is_array($config)) {
            $selected = $config[$environment] ?? $config['defaults'] ?? $config['production'] ?? [];
            if (is_string($selected)) {
                return self::parseBrowserslistQueryLines($selected);
            }
            if (self::isStringList($selected)) {
                return array_values($selected);
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function parseBrowserslistQueryLines(string $contents): array
    {
        $queries = [];
        foreach (preg_split('/[\r\n,]+/', $contents) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '#')) {
                $queries[] = $line;
            }
        }

        return $queries;
    }

    /**
     * @param mixed $value
     */
    private static function isStringList(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return array_is_list($value);
    }
}

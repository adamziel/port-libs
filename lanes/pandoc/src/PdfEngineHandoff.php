<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PdfEngineHandoff
{
    /**
     * @var array<string, array{family:string, intermediate:string, extension:string, defaultArgs:list<string>}>
     */
    private const ENGINE_PROFILES = [
        'pdflatex' => ['family' => 'latex', 'intermediate' => 'latex', 'extension' => 'tex', 'defaultArgs' => ['-halt-on-error', '-interaction=nonstopmode']],
        'lualatex' => ['family' => 'latex', 'intermediate' => 'latex', 'extension' => 'tex', 'defaultArgs' => ['-halt-on-error', '-interaction=nonstopmode']],
        'xelatex' => ['family' => 'latex', 'intermediate' => 'latex', 'extension' => 'tex', 'defaultArgs' => ['-halt-on-error', '-interaction=nonstopmode']],
        'latexmk' => ['family' => 'latex', 'intermediate' => 'latex', 'extension' => 'tex', 'defaultArgs' => ['-pdf', '-halt-on-error', '-interaction=nonstopmode']],
        'tectonic' => ['family' => 'latex', 'intermediate' => 'latex', 'extension' => 'tex', 'defaultArgs' => []],
        'context' => ['family' => 'context', 'intermediate' => 'context', 'extension' => 'tex', 'defaultArgs' => []],
        'pdfroff' => ['family' => 'roff', 'intermediate' => 'ms', 'extension' => 'ms', 'defaultArgs' => []],
        'wkhtmltopdf' => ['family' => 'html', 'intermediate' => 'html5', 'extension' => 'html', 'defaultArgs' => []],
        'weasyprint' => ['family' => 'html', 'intermediate' => 'html5', 'extension' => 'html', 'defaultArgs' => []],
        'prince' => ['family' => 'html', 'intermediate' => 'html5', 'extension' => 'html', 'defaultArgs' => []],
        'pagedjs-cli' => ['family' => 'html', 'intermediate' => 'html5', 'extension' => 'html', 'defaultArgs' => []],
        'typst' => ['family' => 'typst', 'intermediate' => 'typst', 'extension' => 'typ', 'defaultArgs' => []],
    ];

    /**
     * @param array{
     *     engine?: string,
     *     outputPath?: string,
     *     output?: string,
     *     sourcePath?: string,
     *     source?: string,
     *     engineOptions?: list<string>|string,
     *     templatePath?: string,
     *     includeInHeader?: list<string>|string,
     *     resourcePaths?: list<string>|string,
     *     variables?: array<string, mixed>
     * } $options
     * @return array{
     *     kind: string,
     *     target: string,
     *     willExecute: bool,
     *     engineProgram: string,
     *     engine: string,
     *     engineFamily: string,
     *     intermediateFormat: string,
     *     sourceFile: string,
     *     outputFile: string,
     *     argv: list<string>,
     *     engineOptions: list<string>,
     *     sourceBytes: string|null,
     *     sourceSha256: string|null,
     *     templateFile: string|null,
     *     includeInHeaderFiles: list<string>,
     *     resourcePaths: list<string>,
     *     templateVariables: array<string, mixed>,
     *     writerArguments: list<string>,
     *     sourceArtifacts: list<string>,
     *     engineLogFile: string|null,
     *     expectedEngineArtifacts: list<string>,
     *     metadata: array<string, mixed>,
     *     diagnostics: list<string>
     * }
     */
    public function plan(AstNode $document, array $options = []): array
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('PDF engine handoff expects a document node');
        }

        $engineProgram = $this->requireString($options['engine'] ?? 'pdflatex', 'PDF engine');
        $engine = $this->engineName($engineProgram);
        if (!isset(self::ENGINE_PROFILES[$engine])) {
            throw new \InvalidArgumentException('Unsupported PDF engine: ' . $engineProgram);
        }

        $profile = self::ENGINE_PROFILES[$engine];
        $outputFile = $this->normalizeRelativePath((string) ($options['outputPath'] ?? $options['output'] ?? 'document.pdf'), 'PDF output path');
        if (!preg_match('/\.pdf\z/i', $outputFile)) {
            throw new \InvalidArgumentException('PDF output path must end with .pdf');
        }

        $sourceFile = isset($options['sourcePath'])
            ? $this->normalizeRelativePath($this->requireString($options['sourcePath'], 'PDF source path'), 'PDF source path')
            : $this->deriveSourcePath($outputFile, $profile['extension']);
        $engineOptions = $this->normalizeStringList($options['engineOptions'] ?? []);
        $sourceBytes = array_key_exists('source', $options)
            ? $this->requireString($options['source'], 'PDF intermediate source')
            : $this->renderIntermediateSource($document, $profile['intermediate']);
        $templateFile = array_key_exists('templatePath', $options)
            ? $this->normalizeRelativePath($this->requireString($options['templatePath'], 'PDF template path'), 'PDF template path')
            : null;
        $includeInHeaderFiles = $this->normalizeRelativePathList($options['includeInHeader'] ?? [], 'PDF include-in-header path');
        $resourcePaths = $this->normalizeRelativePathList($options['resourcePaths'] ?? [], 'PDF resource path');
        $metadata = $this->metadataSummary($document);
        $variables = array_key_exists('variables', $options)
            ? $this->normalizeVariables($options['variables'])
            : [];
        $templateVariables = array_replace($metadata, $variables);
        $writerArguments = $this->writerArgumentsFor($templateFile, $includeInHeaderFiles, $resourcePaths, $variables);
        $sourceArtifacts = array_values(array_filter(
            array_merge([$templateFile], $includeInHeaderFiles),
            static fn (?string $path): bool => $path !== null && $path !== ''
        ));
        $expectedEngineArtifacts = $this->expectedEngineArtifactsFor($engine, $profile['family'], $sourceFile);
        $engineLogFile = $this->firstEngineLogFile($expectedEngineArtifacts);

        $diagnostics = ['pdf-engine-not-executed'];
        if ($sourceBytes === null) {
            $diagnostics[] = 'intermediate-writer-pending:' . $profile['intermediate'];
        } elseif (array_key_exists('source', $options)) {
            $diagnostics[] = 'intermediate-source-supplied';
        } else {
            $diagnostics[] = 'intermediate-source-rendered:' . $profile['intermediate'];
        }
        if ($templateFile !== null) {
            $diagnostics[] = 'pdf-template-supplied';
        }
        if ($includeInHeaderFiles !== []) {
            $diagnostics[] = 'pdf-include-in-header:' . count($includeInHeaderFiles);
        }
        if ($resourcePaths !== []) {
            $diagnostics[] = 'pdf-resource-paths:' . count($resourcePaths);
        }
        if ($variables !== []) {
            $diagnostics[] = 'pdf-template-variables:' . count($this->flattenVariableArguments($variables));
        }
        if ($engineLogFile !== null) {
            $diagnostics[] = 'pdf-engine-log:' . $engineLogFile;
        }
        if ($expectedEngineArtifacts !== []) {
            $diagnostics[] = 'pdf-engine-artifacts:' . count($expectedEngineArtifacts);
        }

        return [
            'kind' => 'pandoc-pdf-engine-handoff',
            'target' => 'pdf',
            'willExecute' => false,
            'engineProgram' => $engineProgram,
            'engine' => $engine,
            'engineFamily' => $profile['family'],
            'intermediateFormat' => $profile['intermediate'],
            'sourceFile' => $sourceFile,
            'outputFile' => $outputFile,
            'argv' => $this->argvFor($engineProgram, $engine, $profile, $sourceFile, $outputFile, $engineOptions),
            'engineOptions' => $engineOptions,
            'sourceBytes' => $sourceBytes,
            'sourceSha256' => $sourceBytes === null ? null : hash('sha256', $sourceBytes),
            'templateFile' => $templateFile,
            'includeInHeaderFiles' => $includeInHeaderFiles,
            'resourcePaths' => $resourcePaths,
            'templateVariables' => $templateVariables,
            'writerArguments' => $writerArguments,
            'sourceArtifacts' => $sourceArtifacts,
            'engineLogFile' => $engineLogFile,
            'expectedEngineArtifacts' => $expectedEngineArtifacts,
            'metadata' => $metadata,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $plan
     * @param array{exitCode?: int, stdout?: string, stderr?: string, files?: array<string, string>} $result
     * @return array{
     *     ok: bool,
     *     status: string,
     *     reason: string|null,
     *     engine: string,
     *     outputFile: string,
     *     exitCode: int,
     *     bytes: int,
     *     sourceSha256: string|null,
     *     sourceArtifactsSha256: array<string, string>,
     *     producedArtifactsSha256: array<string, string>,
     *     engineLogFiles: list<string>,
     *     engineWarnings: list<string>,
     *     engineErrors: list<string>,
     *     rerunNeeded: bool,
     *     declaredOutputFile: string|null,
     *     declaredOutputPages: int|null,
     *     declaredOutputBytes: int|null,
     *     pdfTrailerComplete: bool,
     *     pdfSha256: string|null,
     *     stdout: string,
     *     stderr: string,
     *     diagnostics: list<string>
     * }
     */
    public function fakeRun(array $plan, array $result = []): array
    {
        $sourceFile = $this->requirePlanString($plan, 'sourceFile');
        $outputFile = $this->requirePlanString($plan, 'outputFile');
        $engine = $this->requirePlanString($plan, 'engine');
        $exitCode = (int) ($result['exitCode'] ?? 0);
        $files = $this->normalizeFileMap($result['files'] ?? []);
        $planDiagnostics = $plan['diagnostics'] ?? [];
        if (!is_array($planDiagnostics)) {
            $planDiagnostics = [];
        }
        $diagnostics = array_values(array_filter(
            $planDiagnostics,
            static fn (mixed $value): bool => is_string($value) && $value !== ''
        ));
        $diagnostics[] = 'fake-runner-no-execution';

        $sourceBytes = $plan['sourceBytes'] ?? null;
        $sourceSha256 = null;
        $sourceArtifactsSha256 = [];
        $producedArtifactsSha256 = [];
        $engineLogFiles = [];
        $engineLogTexts = [];
        $status = 'ok';
        $reason = null;

        if (is_string($sourceBytes)) {
            if (!array_key_exists($sourceFile, $files)) {
                $files[$sourceFile] = $sourceBytes;
                $diagnostics[] = 'staged-source-from-plan';
            } elseif ($files[$sourceFile] !== $sourceBytes) {
                $status = 'failed';
                $reason = 'source-mismatch';
            }
        }

        if (array_key_exists($sourceFile, $files)) {
            $sourceSha256 = hash('sha256', $files[$sourceFile]);
        }

        foreach ($this->normalizePlanStringList($plan['sourceArtifacts'] ?? [], 'PDF source artifact') as $artifactPath) {
            if (!array_key_exists($artifactPath, $files)) {
                if ($reason === null) {
                    $status = 'failed';
                    $reason = 'missing-source-artifact';
                }
                $diagnostics[] = 'missing-source-artifact:' . $artifactPath;
                continue;
            }

            $sourceArtifactsSha256[$artifactPath] = hash('sha256', $files[$artifactPath]);
        }
        if ($sourceArtifactsSha256 !== [] && $reason === null) {
            $diagnostics[] = 'source-artifacts-validated:' . count($sourceArtifactsSha256);
        }

        $expectedEngineArtifacts = $this->normalizePlanStringList($plan['expectedEngineArtifacts'] ?? [], 'PDF expected engine artifact');
        $reservedFiles = array_fill_keys(array_merge([$sourceFile, $outputFile], array_keys($sourceArtifactsSha256)), true);
        foreach ($files as $path => $bytes) {
            if (isset($reservedFiles[$path])) {
                continue;
            }
            if (!in_array($path, $expectedEngineArtifacts, true) && !$this->isKnownEngineArtifact($path, $sourceFile)) {
                continue;
            }

            $producedArtifactsSha256[$path] = hash('sha256', $bytes);
            if ($this->isEngineLogPath($path)) {
                $engineLogFiles[] = $path;
                $engineLogTexts[] = $bytes;
            }
        }
        ksort($producedArtifactsSha256);
        sort($engineLogFiles);
        if ($producedArtifactsSha256 !== []) {
            $diagnostics[] = 'produced-engine-artifacts:' . count($producedArtifactsSha256);
        }

        $engineTexts = array_merge(
            [(string) ($result['stdout'] ?? ''), (string) ($result['stderr'] ?? '')],
            $engineLogTexts
        );
        $engineMessages = $this->extractEngineMessages($engineTexts);
        $declaredOutput = $this->extractDeclaredOutput($engineTexts);
        if ($engineMessages['warnings'] !== []) {
            $diagnostics[] = 'engine-log-warnings:' . count($engineMessages['warnings']);
        }
        if ($engineMessages['errors'] !== []) {
            $diagnostics[] = 'engine-log-errors:' . count($engineMessages['errors']);
        }
        if ($engineMessages['rerunNeeded']) {
            $diagnostics[] = 'engine-rerun-needed';
        }
        if ($declaredOutput['file'] !== null) {
            $diagnostics[] = 'engine-output-file:' . $declaredOutput['file'];
        }
        if ($declaredOutput['pages'] !== null) {
            $diagnostics[] = 'engine-output-pages:' . $declaredOutput['pages'];
        }
        if ($declaredOutput['bytes'] !== null) {
            $diagnostics[] = 'engine-output-bytes:' . $declaredOutput['bytes'];
        }

        $pdfBytes = array_key_exists($outputFile, $files) ? $files[$outputFile] : null;
        $pdfTrailerComplete = is_string($pdfBytes) && $this->hasCompletePdfTrailer($pdfBytes);
        if (
            is_string($pdfBytes)
            && str_starts_with($pdfBytes, '%PDF-')
            && $declaredOutput['file'] !== null
            && !$this->declaredOutputFileMatches($declaredOutput['file'], $outputFile)
        ) {
            $diagnostics[] = 'engine-output-file-mismatch:' . $declaredOutput['file'] . ':' . $outputFile;
        }
        if (
            is_string($pdfBytes)
            && str_starts_with($pdfBytes, '%PDF-')
            && $declaredOutput['bytes'] !== null
            && $declaredOutput['bytes'] !== strlen($pdfBytes)
        ) {
            $diagnostics[] = 'engine-output-byte-mismatch:' . $declaredOutput['bytes'] . ':' . strlen($pdfBytes);
        }
        if (is_string($pdfBytes) && str_starts_with($pdfBytes, '%PDF-') && !$pdfTrailerComplete) {
            $diagnostics[] = 'pdf-output-truncated';
        }
        if ($reason === null && $engineMessages['errors'] !== []) {
            $status = 'failed';
            $reason = 'engine-log-error';
        }
        if ($reason === null && $exitCode !== 0) {
            $status = 'failed';
            $reason = 'engine-exit-' . $exitCode;
        }
        if ($reason === null && $pdfBytes === null) {
            $status = 'failed';
            $reason = 'missing-pdf-output';
        }
        if ($reason === null && !str_starts_with((string) $pdfBytes, '%PDF-')) {
            $status = 'failed';
            $reason = 'non-pdf-output';
        }
        if ($reason === null && !$pdfTrailerComplete) {
            $status = 'failed';
            $reason = 'truncated-pdf-output';
        }
        if (
            $reason === null
            && $declaredOutput['file'] !== null
            && !$this->declaredOutputFileMatches($declaredOutput['file'], $outputFile)
        ) {
            $status = 'failed';
            $reason = 'pdf-output-file-mismatch';
        }
        if (
            $reason === null
            && $declaredOutput['bytes'] !== null
            && is_string($pdfBytes)
            && $declaredOutput['bytes'] !== strlen($pdfBytes)
        ) {
            $status = 'failed';
            $reason = 'pdf-output-byte-mismatch';
        }

        return [
            'ok' => $status === 'ok',
            'status' => $status,
            'reason' => $reason,
            'engine' => $engine,
            'outputFile' => $outputFile,
            'exitCode' => $exitCode,
            'bytes' => is_string($pdfBytes) ? strlen($pdfBytes) : 0,
            'sourceSha256' => $sourceSha256,
            'sourceArtifactsSha256' => $sourceArtifactsSha256,
            'producedArtifactsSha256' => $producedArtifactsSha256,
            'engineLogFiles' => $engineLogFiles,
            'engineWarnings' => $engineMessages['warnings'],
            'engineErrors' => $engineMessages['errors'],
            'rerunNeeded' => $engineMessages['rerunNeeded'],
            'declaredOutputFile' => $declaredOutput['file'],
            'declaredOutputPages' => $declaredOutput['pages'],
            'declaredOutputBytes' => $declaredOutput['bytes'],
            'pdfTrailerComplete' => $pdfTrailerComplete,
            'pdfSha256' => is_string($pdfBytes) && str_starts_with($pdfBytes, '%PDF-') ? hash('sha256', $pdfBytes) : null,
            'stdout' => (string) ($result['stdout'] ?? ''),
            'stderr' => (string) ($result['stderr'] ?? ''),
            'diagnostics' => $diagnostics,
        ];
    }

    private function engineName(string $engineProgram): string
    {
        $engineProgram = $this->requireString($engineProgram, 'PDF engine');
        $normalized = str_replace('\\', '/', $engineProgram);
        $basename = strtolower(basename($normalized));

        return preg_replace('/(?:\.exe|\.bat|\.cmd)\z/i', '', $basename) ?? $basename;
    }

    /**
     * @param array{family:string, intermediate:string, extension:string, defaultArgs:list<string>} $profile
     * @param list<string> $engineOptions
     * @return list<string>
     */
    private function argvFor(string $program, string $engine, array $profile, string $sourceFile, string $outputFile, array $engineOptions): array
    {
        $argv = [$program];

        if ($engine === 'typst') {
            return array_merge($argv, ['compile'], $engineOptions, [$sourceFile, $outputFile]);
        }

        if ($engine === 'pdfroff') {
            return array_merge($argv, $engineOptions, ['-o', $outputFile, $sourceFile]);
        }

        if ($engine === 'prince' || $engine === 'pagedjs-cli') {
            return array_merge($argv, $engineOptions, [$sourceFile, '-o', $outputFile]);
        }

        if ($profile['family'] === 'html') {
            return array_merge($argv, $engineOptions, [$sourceFile, $outputFile]);
        }

        return array_merge($argv, $profile['defaultArgs'], $engineOptions, [$sourceFile]);
    }

    /**
     * @param list<string> $includeInHeaderFiles
     * @param list<string> $resourcePaths
     * @param array<string, mixed> $variables
     * @return list<string>
     */
    private function writerArgumentsFor(?string $templateFile, array $includeInHeaderFiles, array $resourcePaths, array $variables): array
    {
        $arguments = [];
        if ($templateFile !== null) {
            $arguments[] = '--template=' . $templateFile;
        }
        foreach ($includeInHeaderFiles as $path) {
            $arguments[] = '--include-in-header=' . $path;
        }
        if ($resourcePaths !== []) {
            $arguments[] = '--resource-path=' . implode(':', $resourcePaths);
        }
        foreach ($this->flattenVariableArguments($variables) as $argument) {
            $arguments[] = '-V';
            $arguments[] = $argument;
        }

        return $arguments;
    }

    /**
     * @param array<string, mixed> $variables
     * @return list<string>
     */
    private function flattenVariableArguments(array $variables, string $prefix = ''): array
    {
        $arguments = [];
        foreach ($variables as $name => $value) {
            $variableName = $prefix === '' ? (string) $name : $prefix . '.' . $name;
            if (is_array($value) && !array_is_list($value)) {
                array_push($arguments, ...$this->flattenVariableArguments($value, $variableName));
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $arguments[] = $variableName . '=' . $this->templateVariableScalar($item, $variableName);
                }
                continue;
            }

            $arguments[] = $variableName . '=' . $this->templateVariableScalar($value, $variableName);
        }

        return $arguments;
    }

    private function templateVariableScalar(mixed $value, string $name): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('PDF template variable ' . $name . ' must be scalar');
        }

        return $value;
    }

    private function renderIntermediateSource(AstNode $document, string $intermediateFormat): ?string
    {
        if ($intermediateFormat === 'latex') {
            return (new LatexWriter())->write($document);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataSummary(AstNode $document): array
    {
        $metadata = $document->attr('meta', $document->attr('metadata', []));
        if (!is_array($metadata)) {
            return [];
        }

        $summary = [];
        foreach (['title', 'author', 'authors', 'date', 'lang', 'papersize', 'geometry', 'documentclass'] as $key) {
            if (array_key_exists($key, $metadata)) {
                $summary[$key] = $this->normalizeMetadataValue($metadata[$key]);
            }
        }

        return $summary;
    }

    private function normalizeMetadataValue(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeMetadataValue($item);
            }

            return $normalized;
        }

        return (string) $value;
    }

    private function deriveSourcePath(string $outputFile, string $extension): string
    {
        $lastSlash = strrpos($outputFile, '/');
        $directory = $lastSlash === false ? '' : substr($outputFile, 0, $lastSlash + 1);
        $basename = $lastSlash === false ? $outputFile : substr($outputFile, $lastSlash + 1);
        $stem = preg_replace('/\.pdf\z/i', '', $basename) ?? 'document';
        if ($stem === '') {
            $stem = 'document';
        }

        return $directory . $stem . '.' . $extension;
    }

    /**
     * @return list<string>
     */
    private function expectedEngineArtifactsFor(string $engine, string $family, string $sourceFile): array
    {
        $stem = $this->sourceStem($sourceFile);
        $extensions = [];

        if ($family === 'latex') {
            $extensions = ['log', 'aux', 'out', 'toc'];
            if ($engine === 'latexmk') {
                $extensions[] = 'fls';
                $extensions[] = 'fdb_latexmk';
            }
        } elseif ($family === 'context') {
            $extensions = ['log', 'tuc'];
        }

        $artifacts = [];
        foreach ($extensions as $extension) {
            $artifacts[] = $stem . '.' . $extension;
        }

        return $artifacts;
    }

    /**
     * @param list<string> $artifacts
     */
    private function firstEngineLogFile(array $artifacts): ?string
    {
        foreach ($artifacts as $artifact) {
            if ($this->isEngineLogPath($artifact)) {
                return $artifact;
            }
        }

        return null;
    }

    private function sourceStem(string $sourceFile): string
    {
        $lastSlash = strrpos($sourceFile, '/');
        $lastDot = strrpos($sourceFile, '.');
        if ($lastDot !== false && ($lastSlash === false || $lastDot > $lastSlash)) {
            return substr($sourceFile, 0, $lastDot);
        }

        return $sourceFile;
    }

    private function isKnownEngineArtifact(string $path, string $sourceFile): bool
    {
        $stem = $this->sourceStem($sourceFile);
        if (!str_starts_with($path, $stem . '.')) {
            return false;
        }

        $extension = substr($path, strlen($stem) + 1);

        return in_array($extension, [
            'aux',
            'bbl',
            'bcf',
            'blg',
            'fdb_latexmk',
            'fls',
            'log',
            'out',
            'run.xml',
            'synctex.gz',
            'toc',
            'tuc',
            'xdv',
        ], true);
    }

    private function isEngineLogPath(string $path): bool
    {
        return preg_match('/\.log\z/i', $path) === 1;
    }

    /**
     * @param list<string> $texts
     * @return array{warnings:list<string>, errors:list<string>, rerunNeeded:bool}
     */
    private function extractEngineMessages(array $texts): array
    {
        $warnings = [];
        $errors = [];
        $rerunNeeded = false;

        foreach ($texts as $text) {
            foreach (preg_split('/\R/u', $text) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if ($this->isEngineErrorLine($line)) {
                    $errors[] = $line;
                    continue;
                }
                if ($this->isEngineWarningLine($line)) {
                    $warnings[] = $line;
                }
                if ($this->isEngineRerunLine($line)) {
                    $rerunNeeded = true;
                }
            }
        }

        return [
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
            'rerunNeeded' => $rerunNeeded,
        ];
    }

    private function isEngineWarningLine(string $line): bool
    {
        return preg_match('/\bwarning\b|\bwarning:/i', $line) === 1
            && preg_match('/\b0\s+warnings?\b/i', $line) !== 1;
    }

    private function isEngineErrorLine(string $line): bool
    {
        return preg_match('/\A! .*\bError:|\A! .*Error\b|\bFatal error\b|\bError:/i', $line) === 1
            && preg_match('/\b0\s+errors?\b/i', $line) !== 1;
    }

    private function isEngineRerunLine(string $line): bool
    {
        return preg_match('/\brerun\b/i', $line) === 1
            && preg_match('/cross-references?|bibliograph|citation|label|file .* changed|rerunfilecheck/i', $line) === 1;
    }

    /**
     * @param list<string> $texts
     * @return array{file:string|null, pages:int|null, bytes:int|null}
     */
    private function extractDeclaredOutput(array $texts): array
    {
        $output = ['file' => null, 'pages' => null, 'bytes' => null];

        foreach ($texts as $text) {
            foreach (preg_split('/\R/u', $text) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (preg_match('/\bOutput written on\s+(.+?)\s+\((\d+)\s+pages?,\s+(\d+)\s+bytes?\)\.?/i', $line, $matches) !== 1) {
                    continue;
                }

                $output = [
                    'file' => $this->normalizeDeclaredOutputFile($matches[1]),
                    'pages' => (int) $matches[2],
                    'bytes' => (int) $matches[3],
                ];
            }
        }

        return $output;
    }

    private function normalizeDeclaredOutputFile(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = trim($path, " \t\n\r\0\x0B'\"`");
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        return $path;
    }

    private function declaredOutputFileMatches(string $declaredOutputFile, string $outputFile): bool
    {
        return $declaredOutputFile === $outputFile
            || basename($declaredOutputFile) === basename($outputFile);
    }

    private function hasCompletePdfTrailer(string $pdfBytes): bool
    {
        return preg_match('/%%EOF\s*\z/s', $pdfBytes) === 1;
    }

    private function normalizeRelativePath(string $path, string $label): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '') {
            throw new \InvalidArgumentException($label . ' must not be empty');
        }
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException($label . ' must not contain NUL bytes');
        }
        if (str_starts_with($path, '/') || preg_match('/\A[A-Za-z]:\//', $path) === 1) {
            throw new \InvalidArgumentException($label . ' must be relative to the handoff workspace');
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new \InvalidArgumentException($label . ' must not contain parent-directory segments');
            }
            $parts[] = $part;
        }

        if ($parts === []) {
            throw new \InvalidArgumentException($label . ' must name a file');
        }

        return implode('/', $parts);
    }

    /**
     * @return list<string>
     */
    private function normalizeRelativePathList(mixed $value, string $label): array
    {
        if (is_string($value)) {
            $value = [$value];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException($label . ' list must contain strings');
        }

        $paths = [];
        foreach ($value as $path) {
            $paths[] = $this->normalizeRelativePath($this->requireString($path, $label), $label);
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('PDF engine options must be strings');
        }

        $strings = [];
        foreach ($value as $item) {
            $strings[] = $this->requireString($item, 'PDF engine option');
        }

        return $strings;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeVariables(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new \InvalidArgumentException('PDF template variables must be a keyed array');
        }

        $variables = [];
        foreach ($value as $name => $item) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException('PDF template variable names must be strings');
            }
            $this->requireTemplateVariableName($name);
            $variables[$name] = $this->normalizeVariableValue($item, $name);
        }

        return $variables;
    }

    private function normalizeVariableValue(mixed $value, string $name): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            if (str_contains($value, "\0")) {
                throw new \InvalidArgumentException('PDF template variable ' . $name . ' must not contain NUL bytes');
            }

            return $value;
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('PDF template variable ' . $name . ' must be scalar or an array');
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $this->requireTemplateVariableName($key);
            }
            $normalized[$key] = $this->normalizeVariableValue($item, $name . '.' . $key);
        }

        return $normalized;
    }

    private function requireTemplateVariableName(string $name): void
    {
        if ($name === '' || preg_match('/\A[A-Za-z0-9_][A-Za-z0-9_.:-]*\z/', $name) !== 1) {
            throw new \InvalidArgumentException('Invalid PDF template variable name: ' . $name);
        }
    }

    /**
     * @param array<string, mixed> $files
     * @return array<string, string>
     */
    private function normalizeFileMap(array $files): array
    {
        $normalized = [];
        foreach ($files as $path => $bytes) {
            $normalized[$this->normalizeRelativePath((string) $path, 'fake runner file path')] = $this->requireFileBytes($bytes);
        }

        return $normalized;
    }

    private function requireFileBytes(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('fake runner file bytes must be a string');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function normalizePlanStringList(mixed $value, string $label): array
    {
        if ($value === null || $value === []) {
            return [];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException($label . ' plan key must be a list of strings');
        }

        $strings = [];
        foreach ($value as $item) {
            $strings[] = $this->normalizeRelativePath($this->requireString($item, $label), $label);
        }

        return $strings;
    }

    private function requireString(mixed $value, string $label): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException($label . ' must be a string');
        }
        if ($value === '') {
            throw new \InvalidArgumentException($label . ' must not be empty');
        }
        if (str_contains($value, "\0")) {
            throw new \InvalidArgumentException($label . ' must not contain NUL bytes');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function requirePlanString(array $plan, string $key): string
    {
        if (!isset($plan[$key]) || !is_string($plan[$key]) || $plan[$key] === '') {
            throw new \InvalidArgumentException('PDF fake runner requires plan key: ' . $key);
        }

        return $plan[$key];
    }
}

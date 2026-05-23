<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class DiffCommandRunner
{
    public const EXIT_SUCCESS = 0;
    public const EXIT_FOUND_CHANGES = 1;
    public const EXIT_BAD_ARGUMENTS = 2;
    private const DEFAULT_DISPLAY_MODE = 'inline';

    public function __construct(
        private readonly TokenDiffer $differ = new TokenDiffer(),
        private readonly InlineDiffRenderer $inlineRenderer = new InlineDiffRenderer(),
        private readonly LanguageCatalog $languageCatalog = new LanguageCatalog(),
        private readonly FileContentDecoder $fileContentDecoder = new FileContentDecoder(),
    ) {
    }

    /**
     * @param array{checkOnly?: bool, exitCode?: bool, printUnchanged?: bool, language?: string, displayLanguage?: string, display?: string, extraInfo?: string, tabWidth?: int|string, contextLines?: int|string, columnWidth?: int|string, width?: int|string, terminalWidth?: int|string, backgroundColor?: string, syntaxHighlight?: bool|string, sortPaths?: bool, stripCr?: bool, ignoreComments?: bool, ignoreTrailingCommas?: bool, byteLimit?: int|string, graphLimit?: int|string, parseErrorLimit?: int|string, useColor?: bool} $options
     * @param array<string, string> $environment
     * @return array{stdout:string, stderr:string, exitCode:int, hasChanges:bool, message:string, language:string}
     */
    public function runTextDiff(string $old, string $new, string $path, string $language, array $options = [], array $environment = []): array
    {
        $parsedOptions = $this->parseCommandOptions($options, $environment);
        if ($parsedOptions['errors'] !== []) {
            return $this->badArgumentResult($parsedOptions['errors'], [
                'message' => 'Invalid arguments.',
                'language' => $language,
            ]);
        }

        $options = $parsedOptions['options'];
        if (($options['display'] ?? self::DEFAULT_DISPLAY_MODE) === 'json') {
            return $this->jsonTextDiffResult($old, $new, $path, $language, $options);
        }

        $analysis = $this->analyzeTextDiff($old, $new, $language, $options);
        if (($options['checkOnly'] ?? false) === true) {
            return $this->checkOnlyResult($analysis, $path, $options);
        }

        $stdout = '';
        if ($analysis['hasChanges']) {
            $rendererOptions = [
                'path' => $path,
                'language' => $analysis['languageOption'],
                'displayLanguage' => $analysis['language'],
                'useColor' => $options['useColor'] ?? false,
            ];
            foreach (['extraInfo', 'tabWidth', 'contextLines', 'stripCr', 'columnWidth', 'backgroundColor', 'syntaxHighlight'] as $key) {
                if (array_key_exists($key, $options)) {
                    $rendererOptions[$key] = $options[$key];
                }
            }

            if (array_key_exists('terminalWidth', $options) && !array_key_exists('columnWidth', $rendererOptions)) {
                $rendererOptions['columnWidth'] = $this->sideBySideColumnWidthForTerminal(
                    (int) $options['terminalWidth'],
                    $old,
                    $new,
                );
            }

            $stdout = $this->renderChangedText($old, $new, $options, $rendererOptions);
        } elseif (($options['printUnchanged'] ?? true) === true) {
            $stdout = $this->statusOutput($path, $analysis['language'], $analysis['message'], $options);
        }

        return [
            'stdout' => $stdout,
            'stderr' => '',
            'exitCode' => $this->exitCodeForChanges($analysis['hasChanges'], (bool) ($options['exitCode'] ?? false)),
            'hasChanges' => $analysis['hasChanges'],
            'message' => $analysis['message'],
            'language' => $analysis['language'],
        ];
    }

    /**
     * @param array{exitCode?: bool, printUnchanged?: bool, language?: string, displayLanguage?: string, display?: string, extraInfo?: string, tabWidth?: int|string, contextLines?: int|string, columnWidth?: int|string, width?: int|string, terminalWidth?: int|string, backgroundColor?: string, syntaxHighlight?: bool|string, sortPaths?: bool, stripCr?: bool, ignoreComments?: bool, ignoreTrailingCommas?: bool, byteLimit?: int|string, graphLimit?: int|string, parseErrorLimit?: int|string, useColor?: bool} $options
     * @param array<string, string> $environment
     * @return array{stdout:string, stderr:string, exitCode:int, hasChanges:bool, message:string, language:string}
     */
    public function runCheckOnly(string $old, string $new, string $path, string $language, array $options = [], array $environment = []): array
    {
        $options['checkOnly'] = true;

        return $this->runTextDiff($old, $new, $path, $language, $options, $environment);
    }

    public function exitCodeForChanges(bool $hasReportableChanges, bool $setExitCode): int
    {
        return $setExitCode && $hasReportableChanges ? self::EXIT_FOUND_CHANGES : self::EXIT_SUCCESS;
    }

    /**
     * @param array{display?: string, tabWidth?: int|string, contextLines?: int|string, columnWidth?: int|string, width?: int|string, terminalWidth?: int|string, backgroundColor?: string, syntaxHighlight?: bool|string} $options
     * @param array<string, string> $environment
     * @return array{options:array<string, mixed>, errors:list<string>}
     */
    public function parseDisplayOptions(array $options = [], array $environment = []): array
    {
        $parsed = $options;
        $errors = [];

        foreach ([
            ['optionKey' => 'contextLines', 'environmentKey' => 'DFT_CONTEXT', 'label' => '--context'],
            ['optionKey' => 'tabWidth', 'environmentKey' => 'DFT_TAB_WIDTH', 'label' => '--tab-width'],
        ] as $spec) {
            $optionKey = $spec['optionKey'];
            $value = null;
            $source = null;

            if (array_key_exists($optionKey, $parsed)) {
                $value = $parsed[$optionKey];
                $source = $spec['label'];
            } elseif (array_key_exists($spec['environmentKey'], $environment)) {
                $value = $environment[$spec['environmentKey']];
                $source = $spec['environmentKey'];
            }

            if ($source === null) {
                continue;
            }

            $integer = $this->parseNonNegativeInteger($value);
            if ($integer === null) {
                $errors[] = "Invalid value '{$this->stringifyOptionValue($value)}' for {$source}: expected a non-negative integer.";
                continue;
            }

            $parsed[$optionKey] = $integer;
        }

        $widthValue = null;
        $widthSource = null;
        $widthTarget = null;
        if (array_key_exists('columnWidth', $parsed)) {
            $widthValue = $parsed['columnWidth'];
            $widthSource = '--width';
            $widthTarget = 'columnWidth';
        } elseif (array_key_exists('terminalWidth', $parsed)) {
            $widthValue = $parsed['terminalWidth'];
            $widthSource = '--width';
            $widthTarget = 'terminalWidth';
        } elseif (array_key_exists('width', $parsed)) {
            $widthValue = $parsed['width'];
            $widthSource = '--width';
            $widthTarget = 'terminalWidth';
        } elseif (array_key_exists('DFT_WIDTH', $environment)) {
            $widthValue = $environment['DFT_WIDTH'];
            $widthSource = 'DFT_WIDTH';
            $widthTarget = 'terminalWidth';
        }

        if ($widthSource !== null && $widthTarget !== null) {
            $integer = $this->parseNonNegativeInteger($widthValue);
            if ($integer === null) {
                $errors[] = "Invalid value '{$this->stringifyOptionValue($widthValue)}' for {$widthSource}: expected a non-negative integer.";
            } else {
                unset($parsed['width']);
                $parsed[$widthTarget] = $integer;
            }
        }

        $displaySource = null;
        if (array_key_exists('display', $parsed)) {
            $display = $parsed['display'];
            $displaySource = '--display';
        } elseif (array_key_exists('DFT_DISPLAY', $environment)) {
            $display = $environment['DFT_DISPLAY'];
            $displaySource = 'DFT_DISPLAY';
        } else {
            $display = self::DEFAULT_DISPLAY_MODE;
        }

        if (!is_string($display) || !in_array($display, ['inline', 'side-by-side', 'side-by-side-show-both', 'json'], true)) {
            $errors[] = "Invalid value '{$this->stringifyOptionValue($display)}' for " . ($displaySource ?? 'DFT_DISPLAY') . ': expected inline, side-by-side, side-by-side-show-both, or json.';
        } elseif ($display === 'json' && !array_key_exists('DFT_UNSTABLE', $environment)) {
            $errors[] = 'JSON output is an unstable feature and its format may change in future. To enable JSON output, set the environment variable DFT_UNSTABLE=yes.';
        } else {
            $parsed['display'] = $display;
        }

        $backgroundSource = null;
        $background = null;
        if (array_key_exists('backgroundColor', $parsed)) {
            $backgroundSource = '--background';
            $background = $parsed['backgroundColor'];
        } elseif (array_key_exists('DFT_BACKGROUND', $environment)) {
            $backgroundSource = 'DFT_BACKGROUND';
            $background = $environment['DFT_BACKGROUND'];
        }

        if ($backgroundSource !== null) {
            if (!is_string($background) || !in_array($background, ['dark', 'light'], true)) {
                $errors[] = "Invalid value '{$this->stringifyOptionValue($background)}' for {$backgroundSource}: expected dark or light.";
            } else {
                $parsed['backgroundColor'] = $background;
            }
        }

        $syntaxSource = null;
        $syntaxHighlight = null;
        if (array_key_exists('syntaxHighlight', $parsed)) {
            $syntaxSource = '--syntax-highlight';
            $syntaxHighlight = $parsed['syntaxHighlight'];
        } elseif (array_key_exists('DFT_SYNTAX_HIGHLIGHT', $environment)) {
            $syntaxSource = 'DFT_SYNTAX_HIGHLIGHT';
            $syntaxHighlight = $environment['DFT_SYNTAX_HIGHLIGHT'];
        }

        if ($syntaxSource !== null) {
            $parsedSyntaxHighlight = is_bool($syntaxHighlight)
                ? $syntaxHighlight
                : $this->parseOnOffFlag($syntaxHighlight);
            if ($parsedSyntaxHighlight === null) {
                $errors[] = "Invalid value '{$this->stringifyOptionValue($syntaxHighlight)}' for {$syntaxSource}: expected on or off.";
            } else {
                $parsed['syntaxHighlight'] = $parsedSyntaxHighlight;
            }
        }

        return ['options' => $parsed, 'errors' => $errors];
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, string> $environment
     * @return array{options:array<string, mixed>, errors:list<string>}
     */
    public function parseCommandOptions(array $options = [], array $environment = []): array
    {
        $parsedDisplay = $this->parseDisplayOptions($options, $environment);
        $parsed = $parsedDisplay['options'];
        $errors = $parsedDisplay['errors'];

        foreach ([
            ['optionKey' => 'checkOnly', 'environmentKey' => 'DFT_CHECK_ONLY'],
            ['optionKey' => 'exitCode', 'environmentKey' => 'DFT_EXIT_CODE'],
            ['optionKey' => 'ignoreComments', 'environmentKey' => 'DFT_IGNORE_COMMENTS'],
            ['optionKey' => 'sortPaths', 'environmentKey' => 'DFT_SORT_PATHS'],
        ] as $spec) {
            if (array_key_exists($spec['optionKey'], $parsed) || !array_key_exists($spec['environmentKey'], $environment)) {
                continue;
            }

            $value = $this->parseBooleanEnvironmentFlag($environment[$spec['environmentKey']]);
            if ($value === null) {
                $errors[] = $this->invalidBooleanEnvironmentValue($environment[$spec['environmentKey']], $spec['environmentKey']);
                continue;
            }

            $parsed[$spec['optionKey']] = $value;
        }

        foreach ([
            ['optionKey' => 'byteLimit', 'environmentKey' => 'DFT_BYTE_LIMIT', 'label' => '--byte-limit'],
            ['optionKey' => 'graphLimit', 'environmentKey' => 'DFT_GRAPH_LIMIT', 'label' => '--graph-limit'],
            ['optionKey' => 'parseErrorLimit', 'environmentKey' => 'DFT_PARSE_ERROR_LIMIT', 'label' => '--parse-error-limit'],
        ] as $spec) {
            $optionKey = $spec['optionKey'];
            $value = null;
            $source = null;

            if (array_key_exists($optionKey, $parsed)) {
                $value = $parsed[$optionKey];
                $source = $spec['label'];
            } elseif (array_key_exists($spec['environmentKey'], $environment)) {
                $value = $environment[$spec['environmentKey']];
                $source = $spec['environmentKey'];
            }

            if ($source === null) {
                continue;
            }

            $integer = $this->parseNonNegativeInteger($value);
            if ($integer === null) {
                $errors[] = "Invalid value '{$this->stringifyOptionValue($value)}' for {$source}: expected a non-negative integer.";
                continue;
            }

            $parsed[$optionKey] = $integer;
        }

        if (!array_key_exists('printUnchanged', $parsed) && array_key_exists('DFT_SKIP_UNCHANGED', $environment)) {
            $skipUnchanged = $this->parseBooleanEnvironmentFlag($environment['DFT_SKIP_UNCHANGED']);
            if ($skipUnchanged === null) {
                $errors[] = $this->invalidBooleanEnvironmentValue($environment['DFT_SKIP_UNCHANGED'], 'DFT_SKIP_UNCHANGED');
            } else {
                $parsed['printUnchanged'] = !$skipUnchanged;
            }
        }

        if (!array_key_exists('stripCr', $parsed) && array_key_exists('DFT_STRIP_CR', $environment)) {
            $stripCr = strtolower(trim($environment['DFT_STRIP_CR']));
            if (!in_array($stripCr, ['on', 'off'], true)) {
                $errors[] = "Invalid value '{$this->stringifyOptionValue($environment['DFT_STRIP_CR'])}' for DFT_STRIP_CR: expected on or off.";
            } else {
                $parsed['stripCr'] = $stripCr === 'on';
            }
        }

        if (!array_key_exists('useColor', $parsed) && array_key_exists('DFT_COLOR', $environment)) {
            $color = strtolower(trim($environment['DFT_COLOR']));
            if (!in_array($color, ['always', 'auto', 'never'], true)) {
                $errors[] = "Invalid value '{$this->stringifyOptionValue($environment['DFT_COLOR'])}' for DFT_COLOR: expected always, auto, or never.";
            } else {
                $parsed['useColor'] = match ($color) {
                    'always' => true,
                    'auto' => array_key_exists('GIT_PAGER_IN_USE', $environment)
                        && (string) $environment['GIT_PAGER_IN_USE'] !== '',
                    default => false,
                };
            }
        }

        return ['options' => $parsed, 'errors' => $errors];
    }

    /**
     * @param list<string> $rawOverrides
     * @param array{useColor?: bool} $options
     * @param array<string, string> $environment
     * @return array{stdout:string, stderr:string, exitCode:int}
     */
    public function runListLanguages(array $rawOverrides = [], array $options = [], array $environment = []): array
    {
        $parsedOptions = $this->parseCommandOptions($options, $environment);
        if ($parsedOptions['errors'] !== []) {
            return [
                'stdout' => '',
                'stderr' => implode("\n", $parsedOptions['errors']) . "\n",
                'exitCode' => self::EXIT_BAD_ARGUMENTS,
            ];
        }
        $options = $parsedOptions['options'];

        $parsed = $this->parseLanguageOverrides($rawOverrides, $environment);
        if ($parsed['errors'] !== []) {
            return [
                'stdout' => '',
                'stderr' => implode("\n", $parsed['errors']) . "\n",
                'exitCode' => self::EXIT_BAD_ARGUMENTS,
            ];
        }

        return [
            'stdout' => $this->languageCatalog->renderListLanguages(
                $parsed['rows'],
                (bool) ($options['useColor'] ?? false),
            ),
            'stderr' => '',
            'exitCode' => self::EXIT_SUCCESS,
        ];
    }

    /**
     * @param list<string> $rawOverrides
     * @param array<string, string> $environment
     * @return array{rows:list<array{name:string, option:string, globs:list<string>, override:bool}>, errors:list<string>}
     */
    public function parseLanguageOverrides(array $rawOverrides = [], array $environment = []): array
    {
        return $this->languageCatalog->parseLanguageOverrides(
            $this->collectLanguageOverrideInputs($rawOverrides, $environment),
        );
    }

    /**
     * @param list<string> $rawOverrides
     * @param array<string, string> $environment
     * @return array{globs:list<string>, errors:list<string>}
     */
    public function parseBinaryOverrides(array $rawOverrides = [], array $environment = []): array
    {
        $globs = [];
        $errors = [];

        foreach ($this->collectBinaryOverrideInputs($rawOverrides, $environment) as $glob) {
            if (!$this->isValidGlob($glob)) {
                $errors[] = "Invalid glob syntax '{$glob}'";
                $errors[] = 'Glob parsing error: ' . $this->globParsingError($glob);
                continue;
            }

            $globs[] = $glob;
        }

        return ['globs' => $globs, 'errors' => $errors];
    }

    /**
     * @param array{languageOverrides?: list<string>, binaryOverrides?: list<string>, ignoreComments?: bool, ignoreTrailingCommas?: bool, language?: string, byteLimit?: int|string, graphLimit?: int|string, parseErrorLimit?: int|string, stripCr?: bool, forceBinary?: bool, exitCode?: bool} $options
     * @param array<string, string> $environment
     * @return array{stdout:string, stderr:string, exitCode:int, hasChanges:bool, file:array<string, mixed>|null}
     */
    public function runJsonFileBytesDiff(string $oldBytes, string $newBytes, string $path, string $language, array $options = [], array $environment = []): array
    {
        $parsedOptions = $this->parseCommandOptions($options, $environment);
        if ($parsedOptions['errors'] !== []) {
            return $this->badArgumentResult($parsedOptions['errors'], ['file' => null]);
        }
        $options = $parsedOptions['options'];

        $parsed = $this->parseBinaryOverrides($options['binaryOverrides'] ?? [], $environment);
        if ($parsed['errors'] !== []) {
            return $this->badArgumentResult($parsed['errors'], ['file' => null]);
        }

        $rawLanguageOverrides = $this->collectLanguageOverrideInputs($options['languageOverrides'] ?? [], $environment);
        $parsedLanguageOverrides = $this->languageCatalog->parseLanguageOverrides($rawLanguageOverrides);
        if ($parsedLanguageOverrides['errors'] !== []) {
            return $this->badArgumentResult($parsedLanguageOverrides['errors'], ['file' => null]);
        }

        $fileOptions = $options;
        $fileOptions['binaryOverrides'] = $parsed['globs'];
        if ($rawLanguageOverrides !== []) {
            $languageGuess = $this->languageCatalog->languageForPath(
                $path,
                $this->sourceForLanguageGuess($oldBytes, $newBytes, $path, $parsed['globs']),
                $rawLanguageOverrides,
            );
            $language = $languageGuess['display'];
            $fileOptions['language'] = $languageGuess['option'];
        }

        $file = (new JsonDiffRenderer($this->differ))->fileBytesDiff($oldBytes, $newBytes, $path, $language, $fileOptions);
        $hasChanges = ($file['status'] ?? 'unchanged') !== 'unchanged';

        return [
            'stdout' => $this->encodeJson($file),
            'stderr' => '',
            'exitCode' => $this->exitCodeForChanges($hasChanges, (bool) ($options['exitCode'] ?? false)),
            'hasChanges' => $hasChanges,
            'file' => $file,
        ];
    }

    /**
     * @param array{printUnchanged?: bool, sortPaths?: bool, languageOverrides?: list<string>, binaryOverrides?: list<string>, fileOptions?: array<string, mixed>, exitCode?: bool, backgroundColor?: string, syntaxHighlight?: bool|string} $options
     * @param array<string, string> $environment
     * @return array{stdout:string, stderr:string, exitCode:int, hasChanges:bool, files:list<array<string, mixed>>}
     */
    public function runJsonDirectoryDiff(string $lhsDirectory, string $rhsDirectory, array $options = [], array $environment = []): array
    {
        $parsedOptions = $this->parseCommandOptions($options, $environment);
        if ($parsedOptions['errors'] !== []) {
            return $this->badArgumentResult($parsedOptions['errors'], ['files' => []]);
        }
        $options = $parsedOptions['options'];

        $parsed = $this->parseBinaryOverrides($options['binaryOverrides'] ?? [], $environment);
        if ($parsed['errors'] !== []) {
            return $this->badArgumentResult($parsed['errors'], ['files' => []]);
        }

        $rawLanguageOverrides = $this->collectLanguageOverrideInputs($options['languageOverrides'] ?? [], $environment);
        $parsedLanguageOverrides = $this->languageCatalog->parseLanguageOverrides($rawLanguageOverrides);
        if ($parsedLanguageOverrides['errors'] !== []) {
            return $this->badArgumentResult($parsedLanguageOverrides['errors'], ['files' => []]);
        }

        $directoryOptions = $options;
        unset($directoryOptions['exitCode']);
        if (!array_key_exists('printUnchanged', $directoryOptions)) {
            $directoryOptions['printUnchanged'] = true;
        }
        $directoryOptions['binaryOverrides'] = $parsed['globs'];
        $directoryOptions['languageOverrides'] = $rawLanguageOverrides;
        $directoryOptions['fileOptions'] = $this->directoryFileOptionsWithCommandLimits($directoryOptions);
        $files = (new DirectoryDiffer(
            new JsonDiffRenderer($this->differ),
            $this->languageCatalog,
        ))->diffDirectories($lhsDirectory, $rhsDirectory, $directoryOptions);
        $hasChanges = $this->filesHaveChanges($files);

        return [
            'stdout' => $this->encodeJson($files),
            'stderr' => '',
            'exitCode' => $this->exitCodeForChanges($hasChanges, (bool) ($options['exitCode'] ?? false)),
            'hasChanges' => $hasChanges,
            'files' => $files,
        ];
    }

    /**
     * @param array<string, mixed> $analysis
     * @param array{exitCode?: bool, printUnchanged?: bool, extraInfo?: string, useColor?: bool} $options
     * @return array{stdout:string, stderr:string, exitCode:int, hasChanges:bool, message:string, language:string}
     */
    private function checkOnlyResult(array $analysis, string $path, array $options): array
    {
        $stdout = '';
        if ($analysis['hasChanges'] || ($options['printUnchanged'] ?? true) === true) {
            $stdout = $this->statusOutput($path, $analysis['language'], $analysis['message'], $options);
        }

        return [
            'stdout' => $stdout,
            'stderr' => '',
            'exitCode' => $this->exitCodeForChanges($analysis['hasChanges'], (bool) ($options['exitCode'] ?? false)),
            'hasChanges' => $analysis['hasChanges'],
            'message' => $analysis['message'],
            'language' => $analysis['language'],
        ];
    }

    /**
     * @param list<string> $rawOverrides
     * @param array<string, string> $environment
     * @return list<string>
     */
    private function collectLanguageOverrideInputs(array $rawOverrides, array $environment): array
    {
        $inputs = array_values($rawOverrides);
        if (array_key_exists('DFT_OVERRIDE', $environment)) {
            $inputs[] = $environment['DFT_OVERRIDE'];
        }

        for ($index = 1; $index <= 9; $index++) {
            $key = 'DFT_OVERRIDE_' . $index;
            if (array_key_exists($key, $environment)) {
                $inputs[] = $environment[$key];
            }
        }

        return $inputs;
    }

    /**
     * @param list<string> $rawOverrides
     * @param array<string, string> $environment
     * @return list<string>
     */
    private function collectBinaryOverrideInputs(array $rawOverrides, array $environment): array
    {
        $inputs = array_values($rawOverrides);
        if (array_key_exists('DFT_OVERRIDE_BINARY', $environment)) {
            $inputs[] = $environment['DFT_OVERRIDE_BINARY'];
        }

        for ($index = 1; $index <= 9; $index++) {
            $key = 'DFT_OVERRIDE_BINARY_' . $index;
            if (array_key_exists($key, $environment)) {
                $inputs[] = $environment[$key];
            }
        }

        return $inputs;
    }

    /**
     * @param list<string> $binaryOverrideGlobs
     */
    private function sourceForLanguageGuess(string $oldBytes, string $newBytes, string $path, array $binaryOverrideGlobs): string
    {
        $bytes = $newBytes !== '' ? $newBytes : $oldBytes;

        return $this->fileContentDecoder->guessTextContent($bytes, $path, $binaryOverrideGlobs) ?? '';
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function badArgumentResult(array $errors, array $extra): array
    {
        return [
            'stdout' => '',
            'stderr' => implode("\n", $errors) . "\n",
            'exitCode' => self::EXIT_BAD_ARGUMENTS,
            'hasChanges' => false,
        ] + $extra;
    }

    private function isValidGlob(string $glob): bool
    {
        return substr_count($glob, '[') === substr_count($glob, ']');
    }

    private function globParsingError(string $glob): string
    {
        if (substr_count($glob, '[') !== substr_count($glob, ']')) {
            return 'unclosed character class';
        }

        return 'unsupported glob syntax';
    }

    /**
     * @param list<array<string, mixed>> $files
     */
    private function filesHaveChanges(array $files): bool
    {
        foreach ($files as $file) {
            if (($file['status'] ?? 'unchanged') !== 'unchanged') {
                return true;
            }
        }

        return false;
    }

    private function encodeJson(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function directoryFileOptionsWithCommandLimits(array $options): array
    {
        $fileOptions = isset($options['fileOptions']) && is_array($options['fileOptions'])
            ? $options['fileOptions']
            : [];

        foreach (['byteLimit', 'graphLimit', 'parseErrorLimit'] as $key) {
            if (array_key_exists($key, $options) && !array_key_exists($key, $fileOptions)) {
                $fileOptions[$key] = $options[$key];
            }
        }

        return $fileOptions;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $rendererOptions
     */
    private function renderChangedText(string $old, string $new, array $options, array $rendererOptions): string
    {
        return match ($options['display'] ?? self::DEFAULT_DISPLAY_MODE) {
            'side-by-side' => (new SideBySideDiffRenderer($this->differ))->renderTextDiff($old, $new, $rendererOptions),
            'side-by-side-show-both' => (new SideBySideDiffRenderer($this->differ))->renderTextDiff($old, $new, $rendererOptions + ['showBoth' => true]),
            default => $this->inlineRenderer->renderTextDiff($old, $new, $rendererOptions),
        };
    }

    /**
     * @param array{exitCode?: bool, language?: string, displayLanguage?: string, stripCr?: bool, ignoreComments?: bool, ignoreTrailingCommas?: bool, byteLimit?: int, graphLimit?: int, parseErrorLimit?: int} $options
     * @return array{stdout:string, stderr:string, exitCode:int, hasChanges:bool, message:string, language:string}
     */
    private function jsonTextDiffResult(string $old, string $new, string $path, string $language, array $options): array
    {
        $languageOption = (string) ($options['language'] ?? $this->languageOption($language));
        $fileOptions = $options;
        $fileOptions['language'] = $languageOption;
        $displayLanguage = (string) ($options['displayLanguage'] ?? $this->displayLanguageName($languageOption));
        $file = (new JsonDiffRenderer($this->differ))->fileDiff($old, $new, $path, $displayLanguage, $fileOptions);
        $hasChanges = ($file['status'] ?? 'unchanged') !== 'unchanged';
        $plainText = $this->isPlainTextLanguage($languageOption);
        $syntacticStatus = !$plainText && !str_starts_with((string) ($file['language'] ?? ''), 'Text (');

        return [
            'stdout' => $this->encodeJson($file),
            'stderr' => '',
            'exitCode' => $this->exitCodeForChanges($hasChanges, (bool) ($options['exitCode'] ?? false)),
            'hasChanges' => $hasChanges,
            'message' => $this->statusMessage($hasChanges, $syntacticStatus),
            'language' => (string) ($file['language'] ?? $displayLanguage),
        ];
    }

    private function sideBySideColumnWidthForTerminal(int $terminalWidth, string $old, string $new): int
    {
        $lineNumberWidth = max(
            1,
            strlen((string) max($this->displayLineCount($old), $this->displayLineCount($new))),
        );
        $lineNumberColumns = $lineNumberWidth + 1;
        $spacerWidth = 2;
        $available = max(1, $terminalWidth - $spacerWidth - ($lineNumberColumns * 2));

        return max(1, intdiv($available, 2));
    }

    private function displayLineCount(string $source): int
    {
        $lines = explode("\n", $source);
        if (count($lines) > 1 && $lines[array_key_last($lines)] === '') {
            array_pop($lines);
        }

        return max(1, count($lines));
    }

    private function parseNonNegativeInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function parseBooleanEnvironmentFlag(string $value): ?bool
    {
        return match (strtolower(trim($value))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => null,
        };
    }

    private function parseOnOffFlag(mixed $value): ?bool
    {
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            'on' => true,
            'off' => false,
            default => null,
        };
    }

    private function invalidBooleanEnvironmentValue(string $value, string $environmentKey): string
    {
        return "Invalid value '{$this->stringifyOptionValue($value)}' for {$environmentKey}: expected true/false, 1/0, yes/no, or on/off.";
    }

    private function stringifyOptionValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return get_debug_type($value);
    }

    /**
     * @param array{language?: string, displayLanguage?: string, stripCr?: bool, ignoreComments?: bool, ignoreTrailingCommas?: bool, byteLimit?: int, graphLimit?: int, parseErrorLimit?: int} $options
     * @return array{hasChanges:bool, syntacticStatus:bool, message:string, language:string, languageOption:string}
     */
    private function analyzeTextDiff(string $old, string $new, string $language, array $options): array
    {
        $languageOption = (string) ($options['language'] ?? $this->languageOption($language));
        $diffOptions = $options;
        $diffOptions['language'] = $languageOption;
        $old = $this->differ->normalizeTextForDiff($old, $diffOptions);
        $new = $this->differ->normalizeTextForDiff($new, $diffOptions);

        $plainText = $this->isPlainTextLanguage($languageOption);
        $fallbackReason = $plainText || $old === $new
            ? null
            : $this->differ->textFallbackReason($old, $new, $diffOptions, $this->displayLanguageName($languageOption));
        $syntacticStatus = !$plainText && $fallbackReason === null;
        $hasChanges = $syntacticStatus
            ? $this->differ->hasChanges($old, $new, $diffOptions)
            : $old !== $new;
        $displayLanguage = $fallbackReason === null
            ? (string) ($options['displayLanguage'] ?? $this->displayLanguageName($languageOption))
            : 'Text (' . $fallbackReason . ')';

        return [
            'hasChanges' => $hasChanges,
            'syntacticStatus' => $syntacticStatus,
            'message' => $this->statusMessage($hasChanges, $syntacticStatus),
            'language' => $displayLanguage,
            'languageOption' => $languageOption,
        ];
    }

    /**
     * @param array{extraInfo?: string, useColor?: bool} $options
     */
    private function statusOutput(string $path, string $language, string $message, array $options): string
    {
        return $this->inlineRenderer->formatHeader(
            $path,
            $language,
            1,
            1,
            isset($options['extraInfo']) ? (string) $options['extraInfo'] : null,
            (bool) ($options['useColor'] ?? false),
        ) . "\n" . $message . "\n\n";
    }

    private function statusMessage(bool $hasChanges, bool $syntacticStatus): string
    {
        if ($syntacticStatus) {
            return $hasChanges ? 'Has syntactic changes.' : 'No syntactic changes.';
        }

        return $hasChanges ? 'Has changes.' : 'No changes.';
    }

    private function languageOption(string $language): string
    {
        return match (strtolower($language)) {
            'c++' => 'cpp',
            'c#' => 'csharp',
            'emacs lisp' => 'elisp',
            'javascript' => 'javascript',
            'make' => 'makefile',
            'plain text' => 'text',
            'typescript' => 'typescript',
            default => strtolower($language),
        };
    }

    private function displayLanguageName(string $language): string
    {
        return match (strtolower($language)) {
            'c++', 'cpp' => 'C++',
            'c#', 'csharp' => 'C#',
            'css' => 'CSS',
            'html' => 'HTML',
            'javascript', 'js' => 'JavaScript',
            'json' => 'JSON',
            'php', 'hack' => 'PHP',
            'python', 'py' => 'Python',
            'rust', 'rs' => 'Rust',
            'scss' => 'SCSS',
            'text', 'plain', 'plain-text', 'plaintext' => 'Text',
            'typescript', 'ts' => 'TypeScript',
            'tsx' => 'TSX',
            'xml' => 'XML',
            'yaml', 'yml' => 'YAML',
            default => $language === '' ? 'Text' : ucfirst($language),
        };
    }

    private function isPlainTextLanguage(string $language): bool
    {
        return in_array(strtolower($language), ['plain', 'plain-text', 'plaintext', 'text'], true);
    }
}

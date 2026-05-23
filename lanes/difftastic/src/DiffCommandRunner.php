<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class DiffCommandRunner
{
    public const EXIT_SUCCESS = 0;
    public const EXIT_FOUND_CHANGES = 1;
    public const EXIT_BAD_ARGUMENTS = 2;

    public function __construct(
        private readonly TokenDiffer $differ = new TokenDiffer(),
        private readonly InlineDiffRenderer $inlineRenderer = new InlineDiffRenderer(),
    ) {
    }

    /**
     * @param array{checkOnly?: bool, exitCode?: bool, printUnchanged?: bool, language?: string, displayLanguage?: string, extraInfo?: string, tabWidth?: int, contextLines?: int, stripCr?: bool, ignoreComments?: bool, ignoreTrailingCommas?: bool, byteLimit?: int, graphLimit?: int, parseErrorLimit?: int, useColor?: bool} $options
     * @return array{stdout:string, exitCode:int, hasChanges:bool, message:string, language:string}
     */
    public function runTextDiff(string $old, string $new, string $path, string $language, array $options = []): array
    {
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
            foreach (['extraInfo', 'tabWidth', 'contextLines', 'stripCr'] as $key) {
                if (array_key_exists($key, $options)) {
                    $rendererOptions[$key] = $options[$key];
                }
            }

            $stdout = $this->inlineRenderer->renderTextDiff($old, $new, $rendererOptions);
        } elseif (($options['printUnchanged'] ?? true) === true) {
            $stdout = $this->statusOutput($path, $analysis['language'], $analysis['message'], $options);
        }

        return [
            'stdout' => $stdout,
            'exitCode' => $this->exitCodeForChanges($analysis['hasChanges'], (bool) ($options['exitCode'] ?? false)),
            'hasChanges' => $analysis['hasChanges'],
            'message' => $analysis['message'],
            'language' => $analysis['language'],
        ];
    }

    /**
     * @param array{exitCode?: bool, printUnchanged?: bool, language?: string, displayLanguage?: string, extraInfo?: string, stripCr?: bool, ignoreComments?: bool, ignoreTrailingCommas?: bool, byteLimit?: int, graphLimit?: int, parseErrorLimit?: int, useColor?: bool} $options
     * @return array{stdout:string, exitCode:int, hasChanges:bool, message:string, language:string}
     */
    public function runCheckOnly(string $old, string $new, string $path, string $language, array $options = []): array
    {
        $options['checkOnly'] = true;

        return $this->runTextDiff($old, $new, $path, $language, $options);
    }

    public function exitCodeForChanges(bool $hasReportableChanges, bool $setExitCode): int
    {
        return $setExitCode && $hasReportableChanges ? self::EXIT_FOUND_CHANGES : self::EXIT_SUCCESS;
    }

    /**
     * @param array<string, mixed> $analysis
     * @param array{exitCode?: bool, printUnchanged?: bool, extraInfo?: string, useColor?: bool} $options
     * @return array{stdout:string, exitCode:int, hasChanges:bool, message:string, language:string}
     */
    private function checkOnlyResult(array $analysis, string $path, array $options): array
    {
        $stdout = '';
        if ($analysis['hasChanges'] || ($options['printUnchanged'] ?? true) === true) {
            $stdout = $this->statusOutput($path, $analysis['language'], $analysis['message'], $options);
        }

        return [
            'stdout' => $stdout,
            'exitCode' => $this->exitCodeForChanges($analysis['hasChanges'], (bool) ($options['exitCode'] ?? false)),
            'hasChanges' => $analysis['hasChanges'],
            'message' => $analysis['message'],
            'language' => $analysis['language'],
        ];
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

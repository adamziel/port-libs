<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

use InvalidArgumentException;

final class GitExternalDiffMetadata
{
    public function __construct(
        public readonly string $displayPath,
        public readonly ?string $extraInfo,
    ) {
    }

    /**
     * Parse the argument layouts Git passes to GIT_EXTERNAL_DIFF.
     *
     * @param list<string> $arguments
     */
    public static function fromArguments(array $arguments): self
    {
        return match (count($arguments)) {
            7 => self::fromSevenArgumentDiff($arguments),
            9 => self::fromRenameDiff($arguments),
            default => throw new InvalidArgumentException(
                'Expected 7 or 9 Git external diff arguments, got ' . count($arguments) . '.',
            ),
        };
    }

    /**
     * Parse the ordinary two-path CLI layout and select the display path
     * using upstream `build_display_path` semantics.
     *
     * @param list<string> $arguments
     */
    public static function fromPathArguments(array $arguments, ?string $temporaryDirectory = null): self
    {
        if (count($arguments) !== 2) {
            throw new InvalidArgumentException(
                'Expected 2 path arguments, got ' . count($arguments) . '.',
            );
        }

        return new self(self::buildDisplayPath($arguments[0], $arguments[1], $temporaryDirectory), null);
    }

    /**
     * Return the message Difftastic prints when Git invokes it for an
     * unmerged single path, or null for a normal conflict-marker diff.
     *
     * @param list<string> $arguments
     * @param array<string, string|null>|null $environment
     */
    public static function unmergedPathMessage(array $arguments, ?array $environment = null): ?string
    {
        if (count($arguments) !== 1) {
            return null;
        }

        $environment ??= $_ENV + $_SERVER;
        foreach (['GIT_EXEC_PATH', 'GIT_CONFIG_PARAMETERS', 'GIT_DIFF_PATH_TOTAL'] as $name) {
            if (array_key_exists($name, $environment) && (string) $environment[$name] !== '') {
                return 'Unmerged path: ' . $arguments[0] . "\n";
            }
        }

        return null;
    }

    /**
     * @param array{extraInfo?: string|null, path?: string} $options
     * @return array<string, mixed>
     */
    public function applyToOptions(array $options): array
    {
        $existingExtraInfo = isset($options['extraInfo']) ? (string) $options['extraInfo'] : '';
        $options['path'] = $this->displayPath;

        if ($this->extraInfo !== null && $this->extraInfo !== '') {
            $options['extraInfo'] = $existingExtraInfo === ''
                ? $this->extraInfo
                : $this->extraInfo . "\n" . $existingExtraInfo;
        }

        return $options;
    }

    /**
     * @param list<string> $arguments
     */
    private static function fromSevenArgumentDiff(array $arguments): self
    {
        [$displayPath, , , $lhsMode, , , $rhsMode] = $arguments;

        return new self(
            $displayPath,
            self::permissionChangeInfo($lhsMode, $rhsMode),
        );
    }

    /**
     * @param list<string> $arguments
     */
    private static function fromRenameDiff(array $arguments): self
    {
        [$oldName, , , $lhsMode, , , $rhsMode, $newName] = $arguments;
        $extraInfo = 'Renamed from ' . $oldName . ' to ' . $newName;
        $permissionInfo = self::permissionChangeInfo($lhsMode, $rhsMode);
        if ($permissionInfo !== null) {
            $extraInfo .= "\n" . $permissionInfo;
        }

        return new self($newName, $extraInfo);
    }

    private static function buildDisplayPath(string $lhsPath, string $rhsPath, ?string $temporaryDirectory): string
    {
        $lhsKind = self::pathArgumentKind($lhsPath);
        $rhsKind = self::pathArgumentKind($rhsPath);

        if ($lhsKind === 'named' && $rhsKind === 'named') {
            if (self::isGitTemporaryFile($lhsPath, $temporaryDirectory ?? sys_get_temp_dir())) {
                return $rhsPath;
            }

            $commonSuffix = self::commonPathSuffix($lhsPath, $rhsPath);
            if ($commonSuffix !== null) {
                return $commonSuffix;
            }

            return self::hasPathExtension($rhsPath) ? $rhsPath : $lhsPath;
        }

        if ($lhsKind === 'named') {
            return $lhsPath;
        }
        if ($rhsKind === 'named') {
            return $rhsPath;
        }
        if ($lhsKind === 'dev-null' || $rhsKind === 'dev-null') {
            return '/dev/null';
        }

        return '-';
    }

    private static function pathArgumentKind(string $path): string
    {
        return match ($path) {
            '/dev/null' => 'dev-null',
            '-' => 'stdin',
            default => 'named',
        };
    }

    private static function commonPathSuffix(string $lhsPath, string $rhsPath): ?string
    {
        $lhsComponents = array_reverse(self::pathComponents($lhsPath));
        $rhsComponents = array_reverse(self::pathComponents($rhsPath));
        $common = [];
        $count = min(count($lhsComponents), count($rhsComponents));

        for ($index = 0; $index < $count; $index++) {
            if ($lhsComponents[$index] !== $rhsComponents[$index]) {
                break;
            }

            $common[] = $lhsComponents[$index];
        }

        if ($common === []) {
            return null;
        }

        return implode(DIRECTORY_SEPARATOR, array_reverse($common));
    }

    /**
     * @return list<string>
     */
    private static function pathComponents(string $path): array
    {
        $components = preg_split('~[\\\\/]+~', $path);
        if ($components === false) {
            return [$path];
        }

        return array_values(array_filter($components, static fn (string $component): bool => $component !== ''));
    }

    private static function hasPathExtension(string $path): bool
    {
        return pathinfo($path, PATHINFO_EXTENSION) !== '';
    }

    private static function isGitTemporaryFile(string $path, string $temporaryDirectory): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $temporaryDirectory = rtrim(str_replace('\\', '/', $temporaryDirectory), '/');
        if ($temporaryDirectory === '' || !str_starts_with($path, $temporaryDirectory . '/')) {
            return false;
        }

        $relative = substr($path, strlen($temporaryDirectory) + 1);
        $components = self::pathComponents($relative);

        return count($components) === 2 && str_starts_with($components[0], 'git-blob-');
    }

    private static function permissionChangeInfo(string $lhsMode, string $rhsMode): ?string
    {
        $lhsMode = self::normalizeMode($lhsMode);
        $rhsMode = self::normalizeMode($rhsMode);
        if ($lhsMode === null || $rhsMode === null || $lhsMode === $rhsMode) {
            return null;
        }

        return 'File permissions changed from ' . $lhsMode . ' to ' . $rhsMode . '.';
    }

    private static function normalizeMode(string $mode): ?string
    {
        return $mode === '.' ? null : $mode;
    }
}

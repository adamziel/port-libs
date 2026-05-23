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

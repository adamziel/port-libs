<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ExternalMergeDriver
{
    public function __construct(
        public readonly string $name,
        public readonly string $command = '',
        public readonly ?string $recursive = null,
    ) {
    }

    /**
     * @param list<self> $drivers
     * @return list<self>
     */
    public static function sorted(array $drivers): array
    {
        foreach ($drivers as $driver) {
            if (!$driver instanceof self) {
                throw new \InvalidArgumentException('External merge drivers must be ExternalMergeDriver instances');
            }
        }

        usort($drivers, static fn (self $left, self $right): int => $left->name <=> $right->name);

        return $drivers;
    }

    /**
     * @param list<self> $drivers
     */
    public static function byName(array $drivers, string $name): ?self
    {
        $drivers = self::sorted($drivers);
        $low = 0;
        $high = count($drivers) - 1;

        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            $compare = $drivers[$middle]->name <=> $name;
            if ($compare === 0) {
                return $drivers[$middle];
            }
            if ($compare < 0) {
                $low = $middle + 1;
            } else {
                $high = $middle - 1;
            }
        }

        return null;
    }

    /**
     * @param list<self> $drivers
     */
    public static function select(
        string $attributeState,
        ?string $attributeValue = null,
        array $drivers = [],
        ?string $defaultDriver = null,
        bool $isVirtualAncestor = false,
    ): MergeDriverChoice {
        $choice = match ($attributeState) {
            BuiltinDriver::ATTRIBUTE_SET => MergeDriverChoice::builtin(BuiltinDriver::TEXT),
            BuiltinDriver::ATTRIBUTE_UNSET => MergeDriverChoice::builtin(BuiltinDriver::BINARY),
            BuiltinDriver::ATTRIBUTE_VALUE => self::selectNamed($attributeValue, $drivers),
            BuiltinDriver::ATTRIBUTE_UNSPECIFIED => self::selectNamed($defaultDriver, $drivers),
            default => throw new \InvalidArgumentException("Unknown merge attribute state: {$attributeState}"),
        };

        if ($isVirtualAncestor
            && $choice->driver !== null
            && $choice->driver->recursive !== null) {
            $choice = self::selectNamed($choice->driver->recursive, $drivers);

            return new MergeDriverChoice(
                $choice->kind,
                $choice->name,
                $choice->builtin,
                $choice->driver,
                BlobMerge::PICK_OURS,
            );
        }

        return $choice;
    }

    public function prepareCommand(
        ?string $ancestor,
        ?string $current,
        ?string $other,
        string $relativePath,
        ?string $ancestorLabel = null,
        ?string $currentLabel = null,
        ?string $otherLabel = null,
        int $markerSize = 7,
        ?string $worktreeDir = null,
        ?string $gitDir = null,
        int $largeFileThresholdBytes = 0,
    ): ExternalMergeDriverCommand {
        if ($markerSize < 1 || $markerSize > 255) {
            throw new \InvalidArgumentException('External merge driver marker size must fit in a non-zero byte');
        }
        if ($largeFileThresholdBytes < 0) {
            throw new \InvalidArgumentException('External merge driver large file threshold must not be negative');
        }

        $directory = $worktreeDir ?? $gitDir ?? getcwd();
        if ($directory === false || $directory === null || !is_dir($directory)) {
            throw new \InvalidArgumentException('External merge driver temp directory does not exist');
        }

        $ancestor = self::resourceBytes($ancestor, 'CommonAncestorOrBase', $largeFileThresholdBytes);
        $current = self::resourceBytes($current, 'CurrentOrOurs', $largeFileThresholdBytes);
        $other = self::resourceBytes($other, 'OtherOrTheirs', $largeFileThresholdBytes);

        $ancestorPath = self::writeTemp($directory, $ancestor);
        $currentPath = self::writeTemp($directory, $current);
        $otherPath = self::writeTemp($directory, $other);
        $temporaryPaths = [$ancestorPath, $currentPath, $otherPath];

        return new ExternalMergeDriverCommand(
            self::expandCommandTemplate(
                $this->command,
                $ancestorPath,
                $currentPath,
                $otherPath,
                $markerSize,
                $relativePath,
                $ancestorLabel,
                $currentLabel,
                $otherLabel,
            ),
            $ancestorPath,
            $currentPath,
            $otherPath,
            $temporaryPaths,
        );
    }

    public static function expandCommandTemplate(
        string $template,
        string $ancestorPath,
        string $currentPath,
        string $otherPath,
        int $markerSize,
        string $relativePath,
        ?string $ancestorLabel,
        ?string $currentLabel,
        ?string $otherLabel,
    ): string {
        $expanded = '';
        $length = strlen($template);

        for ($i = 0; $i < $length; $i++) {
            $char = $template[$i];
            if ($char !== '%') {
                $expanded .= $char;
                continue;
            }

            if ($i + 1 >= $length) {
                $expanded .= '%';
                continue;
            }

            $next = $template[++$i];
            $expanded .= match ($next) {
                'O' => $ancestorPath,
                'A' => $currentPath,
                'B' => $otherPath,
                'L' => (string) $markerSize,
                'P' => self::singleQuote($relativePath),
                'S' => self::singleQuote($ancestorLabel ?? ''),
                'X' => self::singleQuote($currentLabel ?? ''),
                'Y' => self::singleQuote($otherLabel ?? ''),
                default => '%' . $next,
            };
        }

        return $expanded;
    }

    /**
     * @param list<self> $drivers
     */
    private static function selectNamed(?string $name, array $drivers): MergeDriverChoice
    {
        if ($name !== null) {
            $driver = self::byName($drivers, $name);
            if ($driver !== null) {
                return MergeDriverChoice::external($driver);
            }

            $builtin = BuiltinDriver::byName($name);
            if ($builtin !== null) {
                return MergeDriverChoice::builtin($builtin);
            }
        }

        return MergeDriverChoice::builtin(BuiltinDriver::TEXT);
    }

    private static function resourceBytes(?string $contents, string $kind, int $largeFileThresholdBytes): string
    {
        if ($contents === null) {
            return '';
        }
        if ($largeFileThresholdBytes > 0 && strlen($contents) > $largeFileThresholdBytes) {
            throw new \RuntimeException("The resource of kind {$kind} was too large to be processed");
        }

        return $contents;
    }

    private static function writeTemp(string $directory, string $contents): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $path = self::joinPath($directory, 'gix-merge-' . bin2hex(random_bytes(8)));
            $previousUmask = umask(0077);
            try {
                $handle = @fopen($path, 'xb');
            } finally {
                umask($previousUmask);
            }
            if ($handle === false) {
                if (file_exists($path)) {
                    continue;
                }
                throw new \RuntimeException('Could not create external merge driver tempfile');
            }

            try {
                self::writeAll($handle, $contents);
            } catch (\Throwable $throwable) {
                fclose($handle);
                unlink($path);
                throw $throwable;
            }

            if (!fclose($handle)) {
                unlink($path);
                throw new \RuntimeException('Could not write external merge driver tempfile');
            }

            return $path;
        }

        throw new \RuntimeException('Could not create external merge driver tempfile');
    }

    /**
     * @param resource $handle
     */
    private static function writeAll($handle, string $contents): void
    {
        $offset = 0;
        $length = strlen($contents);
        while ($offset < $length) {
            $written = fwrite($handle, substr($contents, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException('Could not write external merge driver tempfile');
            }
            $offset += $written;
        }
    }

    private static function joinPath(string $directory, string $basename): string
    {
        if ($directory === '' || str_ends_with($directory, '/') || str_ends_with($directory, '\\')) {
            return $directory . $basename;
        }

        return $directory . DIRECTORY_SEPARATOR . $basename;
    }

    private static function singleQuote(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }
}

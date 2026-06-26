<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ZipOpcPackage
{
    private const DEFAULT_MAX_ENTRY_BYTES = 16_777_216;
    private const DEFAULT_MAX_TOTAL_READ_BYTES = 67_108_864;
    private const DEFAULT_MAX_ENTRY_COUNT = 10_000;

    private bool $closed = false;
    private int $readBytes = 0;

    private function __construct(
        private readonly \ZipArchive $zip,
        private readonly string $path,
        private readonly string $label
    ) {
    }

    public function __destruct()
    {
        $this->close();
    }

    public static function open(string $path, string $label): self
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException($label . ' analysis needs PHP ZipArchive, which is unavailable in this runtime.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException("Unable to open {$label} package '{$path}'.");
        }

        return new self($zip, $path, $label);
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->zip->close();
        $this->closed = true;
    }

    public function zipArchive(): \ZipArchive
    {
        return $this->zip;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function read(string $path, ?int $maxBytes = null): ?string
    {
        $path = self::normalizePath($path);
        if ($path === '') {
            return null;
        }

        $limit = $maxBytes ?? self::DEFAULT_MAX_ENTRY_BYTES;
        $stat = $this->zip->statName($path);
        if (is_array($stat)) {
            $size = $stat['size'] ?? null;
            if (is_int($size) && $size > $limit) {
                throw new \RuntimeException("{$this->label} package entry '{$path}' exceeds the {$limit} byte read limit.");
            }
        }

        $bytes = $this->zip->getFromName($path);
        if (!is_string($bytes)) {
            return null;
        }

        $length = strlen($bytes);
        if ($length > $limit) {
            throw new \RuntimeException("{$this->label} package entry '{$path}' exceeds the {$limit} byte read limit.");
        }

        $this->readBytes += $length;
        if ($this->readBytes > self::DEFAULT_MAX_TOTAL_READ_BYTES) {
            throw new \RuntimeException("{$this->label} package exceeded the " . self::DEFAULT_MAX_TOTAL_READ_BYTES . ' byte aggregate read limit.');
        }

        return $bytes;
    }

    public function requireRead(string $path, string $message, ?int $maxBytes = null): string
    {
        $bytes = $this->read($path, $maxBytes);
        if ($bytes === null) {
            throw new \InvalidArgumentException($message);
        }

        return $bytes;
    }

    public function exists(string $path): bool
    {
        $path = self::normalizePath($path);

        return $path !== '' && $this->zip->locateName($path) !== false;
    }

    public function firstEntryName(): ?string
    {
        if ($this->zip->numFiles <= 0) {
            return null;
        }

        $name = $this->zip->getNameIndex(0);

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @return list<string>
     */
    public function entryNames(?int $maxEntries = null): array
    {
        $limit = $maxEntries ?? self::DEFAULT_MAX_ENTRY_COUNT;
        if ($this->zip->numFiles > $limit) {
            throw new \RuntimeException("{$this->label} package has {$this->zip->numFiles} entries, exceeding the {$limit} entry limit.");
        }

        $entries = [];
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $name = $this->zip->getNameIndex($i);
            if (!is_string($name) || $name === '') {
                $stat = $this->zip->statIndex($i);
                $name = is_array($stat) ? (string) ($stat['name'] ?? '') : '';
            }
            if ($name !== '') {
                $entries[] = $name;
            }
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function stat(string $path): ?array
    {
        $stat = $this->zip->statName(self::normalizePath($path));

        return is_array($stat) ? $stat : null;
    }

    /**
     * @return array{name: string, extraBytes: int}|null
     */
    public function firstLocalFileHeader(): ?array
    {
        return self::firstLocalFileHeaderFromPath($this->path);
    }

    /**
     * @return array{name: string, extraBytes: int}|null
     */
    public static function firstLocalFileHeaderFromPath(string $path): ?array
    {
        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) {
            return null;
        }

        try {
            $header = fread($handle, 30);
            if (!is_string($header) || strlen($header) < 30) {
                return null;
            }

            $fields = unpack(
                'Vsignature/vversionNeeded/vflags/vcompressionMethod/vmodTime/vmodDate/Vcrc32/VcompressedSize/VuncompressedSize/vfileNameLength/vextraFieldLength',
                $header
            );
            if (!is_array($fields) || ($fields['signature'] ?? null) !== 0x04034b50) {
                return null;
            }

            $nameLength = (int) ($fields['fileNameLength'] ?? 0);
            $extraLength = (int) ($fields['extraFieldLength'] ?? 0);
            if ($nameLength <= 0 || $nameLength > 65535 || $extraLength < 0 || $extraLength > 65535) {
                return null;
            }

            $name = fread($handle, $nameLength);
            if (!is_string($name) || strlen($name) !== $nameLength) {
                return null;
            }

            return [
                'name' => $name,
                'extraBytes' => $extraLength,
            ];
        } finally {
            fclose($handle);
        }
    }

    public static function dirname(string $path): string
    {
        $dir = str_replace('\\', '/', dirname($path));

        return $dir === '.' ? '' : $dir;
    }

    public static function normalizePath(string $path): string
    {
        return self::normalizePathInternal($path, true) ?? '';
    }

    public static function normalizePathStrict(string $path): string
    {
        $normalized = self::normalizePathInternal($path, false);
        if ($normalized === null) {
            throw new \InvalidArgumentException("Package path '{$path}' escapes above the package root.");
        }

        return $normalized;
    }

    private static function normalizePathInternal(string $path, bool $allowAboveRoot): ?string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    if (!$allowAboveRoot) {
                        return null;
                    }
                    continue;
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }
}

<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ZipOpcPackage
{
    private bool $closed = false;

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

    public function read(string $path): ?string
    {
        $bytes = $this->zip->getFromName(self::normalizePath($path));

        return is_string($bytes) ? $bytes : null;
    }

    public function requireRead(string $path, string $message): string
    {
        $bytes = $this->read($path);
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
    public function entryNames(): array
    {
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
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }
}

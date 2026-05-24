<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class ProtocolValidation
{
    public const MAX_MESSAGE_LEN = 500 * 1000 * 1000;
    public const MAX_REQUEST_SIZE = 2 * BlockList::MAX_BLOCK_SIZE;

    /**
     * @param list<FileInfo> $files
     */
    public static function checkIndexConsistency(array $files): void
    {
        foreach ($files as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException('Expected only FileInfo instances');
            }

            try {
                self::checkFileInfoConsistency($file);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException(sprintf('"%s": %s', $file->name, $e->getMessage()), 0, $e);
            }
        }
    }

    public static function checkFileInfoConsistency(FileInfo $file): void
    {
        self::checkFilename($file->name);

        if ($file->deleted && $file->blocks !== []) {
            throw new \InvalidArgumentException('deleted file with non-empty block list');
        }

        if ($file->type === FileInfo::TYPE_DIRECTORY && $file->blocks !== []) {
            throw new \InvalidArgumentException('directory with non-empty block list');
        }

        if (!$file->deleted && !$file->isInvalid() && $file->type === FileInfo::TYPE_FILE && $file->blocks === []) {
            throw new \InvalidArgumentException('file with empty block list');
        }
    }

    public static function checkRequest(Request $request): void
    {
        self::checkFilename($request->name);

        if ($request->size <= 0) {
            throw new \InvalidArgumentException(sprintf('request size %d too small', $request->size));
        }

        if ($request->size > self::MAX_REQUEST_SIZE) {
            throw new \InvalidArgumentException(sprintf('request size %d exceeds maximum allowed', $request->size));
        }
    }

    public static function checkFilename(string $name): void
    {
        $cleaned = self::cleanWireName($name);
        if ($cleaned !== $name) {
            throw new \InvalidArgumentException('filename not in canonical format');
        }

        if ($name === '' || $name === '.' || $name === '..' || str_starts_with($name, '/') || str_starts_with($name, '../')) {
            throw new \InvalidArgumentException('filename is invalid');
        }
    }

    public static function isValidFilename(string $name): bool
    {
        try {
            self::checkFilename($name);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public static function normalizeWireName(string $name, string $directorySeparator = DIRECTORY_SEPARATOR): string
    {
        if ($directorySeparator !== '' && $directorySeparator !== '/') {
            $name = str_replace($directorySeparator, '/', $name);
        }

        return self::normalizeNfc($name);
    }

    private static function cleanWireName(string $name): string
    {
        if ($name === '') {
            return '.';
        }

        $rooted = str_starts_with($name, '/');
        $segments = explode('/', $name);
        $out = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                $last = $out === [] ? null : $out[array_key_last($out)];
                if ($last !== null && $last !== '..') {
                    array_pop($out);
                } elseif (!$rooted) {
                    $out[] = '..';
                }
                continue;
            }

            $out[] = $segment;
        }

        $cleaned = ($rooted ? '/' : '') . implode('/', $out);

        if ($cleaned === '') {
            return $rooted ? '/' : '.';
        }

        return $cleaned;
    }

    private static function normalizeNfc(string $value): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                return $normalized;
            }
        }

        return self::normalizeCommonLatinCombiningMarks($value);
    }

    private static function normalizeCommonLatinCombiningMarks(string $value): string
    {
        $map = [
            'A' . "\u{0301}" => "\u{00c1}",
            'E' . "\u{0301}" => "\u{00c9}",
            'I' . "\u{0301}" => "\u{00cd}",
            'O' . "\u{0301}" => "\u{00d3}",
            'U' . "\u{0301}" => "\u{00da}",
            'a' . "\u{0301}" => "\u{00e1}",
            'e' . "\u{0301}" => "\u{00e9}",
            'i' . "\u{0301}" => "\u{00ed}",
            'o' . "\u{0301}" => "\u{00f3}",
            'u' . "\u{0301}" => "\u{00fa}",
        ];

        return strtr($value, $map);
    }
}

<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitFilter
{
    public const ATTR_BINARY = 'binary';
    public const ATTR_TEXT = 'text';
    public const ATTR_TEXT_INPUT = 'text-input';
    public const ATTR_TEXT_CRLF = 'text-crlf';
    public const ATTR_TEXT_AUTO = 'text-auto';
    public const ATTR_TEXT_AUTO_CRLF = 'text-auto-crlf';
    public const ATTR_TEXT_AUTO_INPUT = 'text-auto-input';

    public const MODE_LF = 'lf';
    public const MODE_CRLF = 'crlf';

    public const AUTO_CRLF_INPUT = 'input';
    public const AUTO_CRLF_ENABLED = 'enabled';
    public const AUTO_CRLF_DISABLED = 'disabled';

    public const ROUND_TRIP_SKIP = 'skip';
    public const ROUND_TRIP_FAIL = 'fail';
    public const ROUND_TRIP_WARN = 'warn';

    public const ENCODING_UTF8 = 'UTF-8';
    public const ENCODING_UTF16BE = 'UTF-16BE';
    public const ENCODING_UTF16LE = 'UTF-16LE';
    public const ENCODING_WINDOWS_1252 = 'windows-1252';
    public const ENCODING_SHIFT_JIS = 'Shift_JIS';

    public const DRIVER_CLEAN = 'clean';
    public const DRIVER_SMUDGE = 'smudge';

    public const PIPELINE_UNCHANGED = 'unchanged';
    public const PIPELINE_BUFFER = 'buffer';
    public const PIPELINE_STREAM = 'stream';

    private function __construct()
    {
    }

    public static function identUndo(string $src, ?string &$buffer = null): bool
    {
        $offset = 0;
        $out = '';
        $changed = false;

        while (($range = self::identUndoRange($src, $offset)) !== null) {
            $changed = true;
            [$start, $end] = $range;
            $out .= substr($src, $offset, $start - $offset);
            $out .= '$Id$';
            $offset = $end;
        }

        if (!$changed) {
            return false;
        }

        $buffer = $out . substr($src, $offset);
        return true;
    }

    public static function identApply(string $src, string $objectHash = GitHash::SHA1, ?string &$buffer = null): bool
    {
        $offset = 0;
        $out = '';
        $id = null;

        while (($position = strpos($src, '$Id$', $offset)) !== false) {
            if ($id === null) {
                $id = self::computeBlobHash($src, $objectHash);
            }

            $out .= substr($src, $offset, $position - $offset + 3);
            $out .= ': ' . $id . '$';
            $offset = $position + 4;
        }

        if ($id === null) {
            return false;
        }

        $buffer = $out . substr($src, $offset);
        return true;
    }

    /**
     * @param callable(?string&): (?bool) $indexObject
     * @param array{roundTripCheck?: string|null, path?: string, config?: array{autoCrlf?: string, eol?: ?string}} $options
     */
    public static function convertToGit(
        string $src,
        string $digest,
        ?string &$buffer,
        ?callable $indexObject = null,
        array $options = []
    ): bool {
        self::assertAttributesDigest($digest);
        if ($digest === self::ATTR_BINARY || $src === '') {
            return false;
        }

        $stats = self::eolStats($src);
        $convertCrlfToLf = $stats['crlf'] > 0;

        if (self::isAutoText($digest)) {
            if (self::statsAreBinary($stats)) {
                return false;
            }

            if ($indexObject !== null) {
                $hasObject = $indexObject($buffer);
                if ($hasObject !== null && $hasObject !== false && $buffer !== null && str_contains($buffer, "\r")) {
                    $indexStats = self::eolStats($buffer);
                    if (!self::statsAreBinary($indexStats) && $indexStats['crlf'] > 0) {
                        $convertCrlfToLf = false;
                    }
                }
            }
        }

        $roundTripCheck = $options['roundTripCheck'] ?? null;
        if ($roundTripCheck !== null && $roundTripCheck !== self::ROUND_TRIP_SKIP) {
            $config = $options['config'] ?? [];
            $path = $options['path'] ?? '';
            $newStats = $stats;

            if ($convertCrlfToLf) {
                $newStats['lone_lf'] += $newStats['crlf'];
                $newStats['crlf'] = 0;
            }

            if (self::willConvertLfToCrlf($newStats, $digest, $config)) {
                $newStats['crlf'] += $newStats['lone_lf'];
                $newStats['lone_lf'] = 0;
            }

            if ($stats['crlf'] > 0 && $newStats['crlf'] === 0) {
                self::handleRoundTrip($roundTripCheck, 'CRLF would be replaced by LF', $path);
            } elseif ($stats['lone_lf'] > 0 && $newStats['lone_lf'] === 0) {
                self::handleRoundTrip($roundTripCheck, 'LF would be replaced by CRLF', $path);
            }
        }

        if (!$convertCrlfToLf) {
            return false;
        }

        if ($stats['lone_cr'] === 0) {
            $buffer = str_replace("\r", '', $src);
            return true;
        }

        $out = '';
        $length = strlen($src);
        for ($i = 0; $i < $length; $i++) {
            if ($src[$i] === "\r" && $i + 1 < $length && $src[$i + 1] === "\n") {
                continue;
            }
            $out .= $src[$i];
        }

        $buffer = $out;
        return true;
    }

    /**
     * @param array{autoCrlf?: string, eol?: ?string} $config
     */
    public static function convertToWorktree(
        string $src,
        string $digest,
        ?string &$buffer,
        array $config = []
    ): bool {
        self::assertAttributesDigest($digest);
        if ($src === '' || self::digestToEol($digest, $config) !== self::MODE_CRLF) {
            return false;
        }

        $stats = self::eolStats($src);
        if (!self::willConvertLfToCrlf($stats, $digest, $config)) {
            return false;
        }

        $out = '';
        $length = strlen($src);
        for ($i = 0; $i < $length; $i++) {
            if ($src[$i] === "\r") {
                if ($i + 1 < $length && $src[$i + 1] === "\n") {
                    $out .= "\r\n";
                    $i++;
                    continue;
                }
                $out .= "\r";
                continue;
            }

            if ($src[$i] === "\n") {
                $out .= "\r\n";
                continue;
            }

            $out .= $src[$i];
        }

        $buffer = $out;
        return true;
    }

    public static function worktreeEncodingForLabel(string $label): string
    {
        $lookup = $label === 'latin-1' ? 'ISO-8859-1' : $label;
        $normalized = strtoupper(str_replace('_', '-', $lookup));

        return match ($normalized) {
            'UTF8', 'UTF-8' => self::ENCODING_UTF8,
            'UTF-16BE' => self::ENCODING_UTF16BE,
            'UTF-16LE' => self::ENCODING_UTF16LE,
            'ISO-8859-1', 'ISO8859-1', 'WINDOWS-1252', 'CP1252' => self::ENCODING_WINDOWS_1252,
            'SHIFT-JIS', 'SHIFT-JISX0213', 'SJIS', 'MS-KANJI', 'WINDOWS-31J', 'CP932' => self::ENCODING_SHIFT_JIS,
            default => throw new \InvalidArgumentException("An encoding named '{$lookup}' is not known"),
        };
    }

    public static function encodeToGit(
        string $src,
        string $srcEncoding,
        ?string &$buffer,
        string $roundTrip = self::ROUND_TRIP_SKIP
    ): void {
        $encoding = self::normalizeEncodingName($srcEncoding);
        $phpEncoding = self::phpEncodingName($encoding);

        if (!mb_check_encoding($src, $phpEncoding)) {
            throw new \RuntimeException("The input was malformed and could not be decoded as '{$encoding}'");
        }

        $utf8 = mb_convert_encoding($src, self::ENCODING_UTF8, $phpEncoding);
        if ($utf8 === false) {
            throw new \RuntimeException("The input was malformed and could not be decoded as '{$encoding}'");
        }

        if ($roundTrip === self::ROUND_TRIP_FAIL) {
            $reencoded = mb_convert_encoding($utf8, $phpEncoding, self::ENCODING_UTF8);
            if ($reencoded !== $src) {
                throw new \RuntimeException("Encoding from '{$encoding}' to 'UTF-8' and back is not the same");
            }
        }

        $buffer = $utf8;
    }

    public static function encodeToWorktree(string $srcUtf8, string $worktreeEncoding, ?string &$buffer): void
    {
        $encoding = self::normalizeEncodingName($worktreeEncoding);
        $phpEncoding = self::phpEncodingName($encoding);

        if (!mb_check_encoding($srcUtf8, self::ENCODING_UTF8)) {
            throw new \RuntimeException('Input was not UTF-8 encoded');
        }

        $encoded = mb_convert_encoding($srcUtf8, $phpEncoding, self::ENCODING_UTF8);
        if ($encoded === false) {
            throw new \RuntimeException("Unable to encode input as '{$encoding}'");
        }

        $decoded = mb_convert_encoding($encoded, self::ENCODING_UTF8, $phpEncoding);
        if ($decoded !== $srcUtf8) {
            throw new \RuntimeException("Encoding from 'UTF-8' to '{$encoding}' and back is not the same");
        }

        $buffer = $encoded;
    }

    /**
     * @param callable(string, string, string): string|null $driver
     */
    public static function applyDriver(
        string $src,
        string $operation,
        ?callable $driver,
        string $path = '',
        bool $required = true
    ): ?string {
        self::assertDriverOperation($operation);
        if ($driver === null) {
            return null;
        }

        try {
            $result = $driver($src, $operation, $path);
        } catch (\Throwable $throwable) {
            if (!$required) {
                return '';
            }

            throw new \RuntimeException("Filter driver {$operation} failed", 0, $throwable);
        }

        if (!is_string($result)) {
            throw new \RuntimeException("Filter driver {$operation} must return a string");
        }

        return $result;
    }

    /**
     * @param array{
     *     ident?: bool,
     *     digest?: string|null,
     *     encoding?: string|null,
     *     path?: string,
     *     config?: array{autoCrlf?: string, eol?: ?string},
     *     roundTripCheck?: string|null
     * } $attributes
     * @param callable(string, string, string): string|null $cleanDriver
     * @param callable(?string&): (?bool) $indexObject
     * @return array{changed: bool, data: string, storage: string}
     */
    public static function convertPipelineToGit(
        string $src,
        array $attributes = [],
        ?callable $cleanDriver = null,
        ?callable $indexObject = null
    ): array {
        $path = $attributes['path'] ?? '';
        $config = $attributes['config'] ?? [];
        $digest = self::digestFromPipelineAttributes($attributes, $config);
        $applyIdent = (bool) ($attributes['ident'] ?? false);
        $encoding = $attributes['encoding'] ?? null;

        $wouldConvertEol = self::wouldConvertCrlfToLf($digest, $config);
        $needsInMemory = $applyIdent || $encoding !== null || $wouldConvertEol;
        $inMemory = false;
        $current = $src;

        $driverOutput = self::applyDriver($current, self::DRIVER_CLEAN, $cleanDriver, $path);
        if ($driverOutput !== null) {
            $current = $driverOutput;
            if (!$needsInMemory) {
                return [
                    'changed' => true,
                    'data' => $current,
                    'storage' => self::PIPELINE_STREAM,
                ];
            }
            $inMemory = true;
        }

        if (!$inMemory && $needsInMemory) {
            $inMemory = true;
        }

        if ($encoding !== null) {
            $buffer = '';
            self::encodeToGit($current, $encoding, $buffer);
            $current = $buffer;
        }

        $buffer = '';
        if (self::convertToGit($current, $digest, $buffer, $indexObject, [
            'roundTripCheck' => $attributes['roundTripCheck'] ?? self::ROUND_TRIP_SKIP,
            'path' => $path,
            'config' => $config,
        ])) {
            $current = $buffer;
        }

        if ($applyIdent && self::identUndo($current, $buffer)) {
            $current = $buffer;
        }

        if ($inMemory) {
            return [
                'changed' => true,
                'data' => $current,
                'storage' => self::PIPELINE_BUFFER,
            ];
        }

        return [
            'changed' => false,
            'data' => $src,
            'storage' => self::PIPELINE_STREAM,
        ];
    }

    /**
     * @param array{
     *     ident?: bool,
     *     digest?: string|null,
     *     encoding?: string|null,
     *     objectHash?: string,
     *     path?: string,
     *     config?: array{autoCrlf?: string, eol?: ?string}
     * } $attributes
     * @param callable(string, string, string): string|null $smudgeDriver
     * @return array{changed: bool, data: string, storage: string}
     */
    public static function convertPipelineToWorktree(
        string $src,
        array $attributes = [],
        ?callable $smudgeDriver = null
    ): array {
        $path = $attributes['path'] ?? '';
        $config = $attributes['config'] ?? [];
        $digest = self::digestFromPipelineAttributes($attributes, $config);
        $current = $src;
        $changed = false;
        $buffer = '';

        if (($attributes['ident'] ?? false) && self::identApply($current, $attributes['objectHash'] ?? GitHash::SHA1, $buffer)) {
            $current = $buffer;
            $changed = true;
        }

        if (self::convertToWorktree($current, $digest, $buffer, $config)) {
            $current = $buffer;
            $changed = true;
        }

        $encoding = $attributes['encoding'] ?? null;
        if ($encoding !== null) {
            self::encodeToWorktree($current, $encoding, $buffer);
            $current = $buffer;
            $changed = true;
        }

        $driverOutput = self::applyDriver($current, self::DRIVER_SMUDGE, $smudgeDriver, $path);
        if ($driverOutput !== null) {
            return [
                'changed' => true,
                'data' => $driverOutput,
                'storage' => self::PIPELINE_STREAM,
            ];
        }

        if ($changed) {
            return [
                'changed' => true,
                'data' => $current,
                'storage' => self::PIPELINE_BUFFER,
            ];
        }

        return [
            'changed' => false,
            'data' => $src,
            'storage' => self::PIPELINE_UNCHANGED,
        ];
    }

    /**
     * @return ?array{0: int, 1: int}
     */
    private static function identUndoRange(string $src, int $searchOffset): ?array
    {
        $length = strlen($src);
        while ($searchOffset < $length) {
            $start = strpos($src, '$Id:', $searchOffset);
            if ($start === false) {
                return null;
            }

            $cursor = $start + 4;
            $dollar = strpos($src, '$', $cursor);
            $newline = strpos($src, "\n", $cursor);

            if ($dollar === false && $newline === false) {
                return null;
            }

            if ($newline !== false && ($dollar === false || $newline < $dollar)) {
                $searchOffset = $newline + 1;
                continue;
            }

            return [$start, $dollar + 1];
        }

        return null;
    }

    private static function computeBlobHash(string $src, string $objectHash): string
    {
        $payload = 'blob ' . strlen($src) . "\0" . $src;

        return match ($objectHash) {
            GitHash::SHA1 => hash('sha1', $payload),
            GitHash::SHA256 => hash('sha256', $payload),
            default => throw new \InvalidArgumentException("Unsupported object hash: {$objectHash}"),
        };
    }

    /**
     * @return array{null: int, lone_cr: int, lone_lf: int, crlf: int, printable: int, non_printable: int}
     */
    private static function eolStats(string $bytes): array
    {
        $stats = [
            'null' => 0,
            'lone_cr' => 0,
            'lone_lf' => 0,
            'crlf' => 0,
            'printable' => 0,
            'non_printable' => 0,
        ];
        $length = strlen($bytes);

        for ($i = 0; $i < $length; $i++) {
            $byte = ord($bytes[$i]);
            if ($byte === 13) {
                if ($i + 1 < $length && $bytes[$i + 1] === "\n") {
                    $stats['crlf']++;
                    $i++;
                } else {
                    $stats['lone_cr']++;
                }
                continue;
            }

            if ($byte === 10) {
                $stats['lone_lf']++;
                continue;
            }

            if ($byte === 127) {
                $stats['non_printable']++;
            } elseif ($byte < 32) {
                if ($byte === 8 || $byte === 9 || $byte === 27 || $byte === 12) {
                    $stats['printable']++;
                } else {
                    if ($byte === 0) {
                        $stats['null']++;
                    }
                    $stats['non_printable']++;
                }
            } else {
                $stats['printable']++;
            }
        }

        return $stats;
    }

    /**
     * @param array{null: int, lone_cr: int, lone_lf: int, crlf: int, printable: int, non_printable: int} $stats
     */
    private static function statsAreBinary(array $stats): bool
    {
        return $stats['lone_cr'] > 0
            || $stats['null'] > 0
            || (($stats['printable'] >> 7) < $stats['non_printable']);
    }

    /**
     * @param array{autoCrlf?: string, eol?: ?string} $config
     */
    private static function digestToEol(string $digest, array $config): ?string
    {
        return match ($digest) {
            self::ATTR_BINARY => null,
            self::ATTR_TEXT_INPUT, self::ATTR_TEXT_AUTO_INPUT => self::MODE_LF,
            self::ATTR_TEXT_CRLF, self::ATTR_TEXT_AUTO_CRLF => self::MODE_CRLF,
            self::ATTR_TEXT, self::ATTR_TEXT_AUTO => self::configToEol($config),
            default => throw new \InvalidArgumentException("Unknown attributes digest: {$digest}"),
        };
    }

    /**
     * @param array{autoCrlf?: string, eol?: ?string} $config
     */
    private static function configToEol(array $config): string
    {
        return match ($config['autoCrlf'] ?? self::AUTO_CRLF_DISABLED) {
            self::AUTO_CRLF_ENABLED => self::MODE_CRLF,
            self::AUTO_CRLF_INPUT => self::MODE_LF,
            self::AUTO_CRLF_DISABLED => $config['eol'] ?? self::nativeEolMode(),
            default => throw new \InvalidArgumentException("Unknown auto CRLF mode: {$config['autoCrlf']}"),
        };
    }

    /**
     * @param array{null: int, lone_cr: int, lone_lf: int, crlf: int, printable: int, non_printable: int} $stats
     * @param array{autoCrlf?: string, eol?: ?string} $config
     */
    private static function willConvertLfToCrlf(array $stats, string $digest, array $config): bool
    {
        if (self::digestToEol($digest, $config) !== self::MODE_CRLF) {
            return false;
        }

        if ($stats['lone_lf'] === 0) {
            return false;
        }

        if (self::isAutoText($digest)) {
            if (self::statsAreBinary($stats)) {
                return false;
            }

            if ($stats['lone_cr'] > 0 || $stats['crlf'] > 0) {
                return false;
            }
        }

        return true;
    }

    private static function isAutoText(string $digest): bool
    {
        return $digest === self::ATTR_TEXT_AUTO
            || $digest === self::ATTR_TEXT_AUTO_CRLF
            || $digest === self::ATTR_TEXT_AUTO_INPUT;
    }

    private static function handleRoundTrip(string $roundTripCheck, string $message, string $path): void
    {
        if ($roundTripCheck === self::ROUND_TRIP_FAIL) {
            throw new \RuntimeException("{$message} in '{$path}'");
        }
    }

    private static function nativeEolMode(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? self::MODE_CRLF : self::MODE_LF;
    }

    private static function assertAttributesDigest(string $digest): void
    {
        match ($digest) {
            self::ATTR_BINARY,
            self::ATTR_TEXT,
            self::ATTR_TEXT_INPUT,
            self::ATTR_TEXT_CRLF,
            self::ATTR_TEXT_AUTO,
            self::ATTR_TEXT_AUTO_CRLF,
            self::ATTR_TEXT_AUTO_INPUT => null,
            default => throw new \InvalidArgumentException("Unknown attributes digest: {$digest}"),
        };
    }

    private static function assertDriverOperation(string $operation): void
    {
        match ($operation) {
            self::DRIVER_CLEAN,
            self::DRIVER_SMUDGE => null,
            default => throw new \InvalidArgumentException("Unknown filter driver operation: {$operation}"),
        };
    }

    /**
     * @param array{digest?: string|null} $attributes
     * @param array{autoCrlf?: string, eol?: ?string} $config
     */
    private static function digestFromPipelineAttributes(array $attributes, array $config): string
    {
        $digest = $attributes['digest'] ?? null;
        if ($digest !== null) {
            self::assertAttributesDigest($digest);
            return $digest;
        }

        return match ($config['autoCrlf'] ?? self::AUTO_CRLF_DISABLED) {
            self::AUTO_CRLF_ENABLED => self::ATTR_TEXT_AUTO_CRLF,
            self::AUTO_CRLF_INPUT => self::ATTR_TEXT_AUTO_INPUT,
            self::AUTO_CRLF_DISABLED => self::ATTR_BINARY,
            default => throw new \InvalidArgumentException("Unknown auto CRLF mode: {$config['autoCrlf']}"),
        };
    }

    /**
     * @param array{autoCrlf?: string, eol?: ?string} $config
     */
    private static function wouldConvertCrlfToLf(string $digest, array $config): bool
    {
        $probe = '';
        return self::convertToGit("\r\n", $digest, $probe, static fn (?string &$buf): ?bool => null, [
            'roundTripCheck' => self::ROUND_TRIP_SKIP,
            'config' => $config,
        ]);
    }

    private static function normalizeEncodingName(string $encoding): string
    {
        return match ($encoding) {
            self::ENCODING_UTF8,
            self::ENCODING_UTF16BE,
            self::ENCODING_UTF16LE,
            self::ENCODING_WINDOWS_1252,
            self::ENCODING_SHIFT_JIS => $encoding,
            default => self::worktreeEncodingForLabel($encoding),
        };
    }

    private static function phpEncodingName(string $encoding): string
    {
        return match ($encoding) {
            self::ENCODING_UTF8 => 'UTF-8',
            self::ENCODING_UTF16BE => 'UTF-16BE',
            self::ENCODING_UTF16LE => 'UTF-16LE',
            self::ENCODING_WINDOWS_1252 => 'Windows-1252',
            self::ENCODING_SHIFT_JIS => 'Shift_JIS',
            default => throw new \InvalidArgumentException("Unsupported encoding: {$encoding}"),
        };
    }
}

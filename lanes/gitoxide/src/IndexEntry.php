<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class IndexEntry
{
    public const STAGE_NORMAL = 0;
    public const STAGE_ANCESTOR = 1;
    public const STAGE_OURS = 2;
    public const STAGE_THEIRS = 3;

    public const MODE_DIR = '40000';
    public const MODE_FILE = '100644';
    public const MODE_FILE_EXECUTABLE = '100755';
    public const MODE_SYMLINK = '120000';
    public const MODE_COMMIT = '160000';

    public const MODE_CHANGE_EXECUTABLE_BIT = 'executable-bit';
    public const MODE_CHANGE_TYPE = 'type';

    public const UPSTREAM_ENTRY_SIZE_SHA1_BYTES = 80;
    public const UPSTREAM_ENTRY_SIZE_SHA256_EXTRA_BYTES = 16;
    public const UPSTREAM_ENTRY_SIZE_BYTES = self::UPSTREAM_ENTRY_SIZE_SHA1_BYTES + self::UPSTREAM_ENTRY_SIZE_SHA256_EXTRA_BYTES;
    public const UPSTREAM_TIME_SIZE_BYTES = 8;
    public const UPSTREAM_FILETIME_SIZE_BYTES = 16;

    private const FLAG_STAGE_MASK = 0x3000;
    private const UINT32_MAX = 0xffffffff;
    private const NSEC_MAX = 999999999;
    private const UNIX_NANOS_PER_SECOND = 1000000000;

    public function __construct(
        public readonly string $path,
        public readonly int $stage,
        public readonly string $mode,
        public readonly string $oid,
        public readonly bool $skipWorktree = false,
        public readonly bool $assumeValid = false,
        public readonly int $mtimeSecs = 0,
        public readonly int $mtimeNsecs = 0,
        public readonly int $ctimeSecs = 0,
        public readonly int $ctimeNsecs = 0,
        public readonly int $dev = 0,
        public readonly int $ino = 0,
        public readonly int $uid = 0,
        public readonly int $gid = 0,
        public readonly int $size = 0,
    ) {
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Index path must be non-empty and cannot contain NUL bytes');
        }
        if (!in_array($stage, [
            self::STAGE_NORMAL,
            self::STAGE_ANCESTOR,
            self::STAGE_OURS,
            self::STAGE_THEIRS,
        ], true)) {
            throw new \InvalidArgumentException("Unsupported index stage: {$stage}");
        }
        TreeEntry::assertValidMode($mode);
        if (!preg_match('/^[0-9a-f]{40}$/', $oid)) {
            throw new \InvalidArgumentException('Index entry object id must be a 40-character SHA-1 hex string');
        }
        self::assertUInt32('mtimeSecs', $mtimeSecs);
        self::assertNanoseconds('mtimeNsecs', $mtimeNsecs);
        self::assertUInt32('ctimeSecs', $ctimeSecs);
        self::assertNanoseconds('ctimeNsecs', $ctimeNsecs);
        self::assertUInt32('dev', $dev);
        self::assertUInt32('ino', $ino);
        self::assertUInt32('uid', $uid);
        self::assertUInt32('gid', $gid);
        self::assertUInt32('size', $size);
    }

    public function side(): string
    {
        return match ($this->stage) {
            self::STAGE_NORMAL => 'normal',
            self::STAGE_ANCESTOR => 'ancestor',
            self::STAGE_OURS => 'ours',
            self::STAGE_THEIRS => 'theirs',
        };
    }

    public static function flagsFromStage(int $stage): int
    {
        self::assertStage($stage);

        return $stage << 12;
    }

    public static function stageFromFlags(int $flags): int
    {
        return ($flags & self::FLAG_STAGE_MASK) >> 12;
    }

    public static function applyModeChange(string $change, string|int $mode, string|int|null $newMode = null): string
    {
        return match ($change) {
            self::MODE_CHANGE_EXECUTABLE_BIT => self::applyExecutableBitChange($mode),
            self::MODE_CHANGE_TYPE => self::applyTypeChange($newMode),
            default => throw new \InvalidArgumentException("Unsupported index mode change: {$change}"),
        };
    }

    public static function modeDebug(string|int $mode): string
    {
        return self::modeDebugForBits(self::modeBits($mode));
    }

    public static function modeDebugFromBits(int $bits): string
    {
        self::assertUInt32('mode bits', $bits);
        if (($bits & ~self::allKnownModeBits()) !== 0) {
            return 'None';
        }

        return 'Some(' . self::modeDebugForBits($bits) . ')';
    }

    public static function modeFromBits(int $bits): ?string
    {
        self::assertUInt32('mode bits', $bits);
        if (($bits & ~self::allKnownModeBits()) !== 0) {
            return null;
        }

        return decoct($bits);
    }

    /**
     * @return array{secs:int,nsecs:int}
     */
    public static function time(int $secs = 0, int $nsecs = 0): array
    {
        self::assertUInt32('time.secs', $secs);
        self::assertNanoseconds('time.nsecs', $nsecs);

        return ['secs' => $secs, 'nsecs' => $nsecs];
    }

    /**
     * @param array{secs?:int,nsecs?:int} $time
     */
    public static function timeToUnixNanoseconds(array $time): int
    {
        $time = self::normalizeTime($time);

        return ($time['secs'] * self::UNIX_NANOS_PER_SECOND) + $time['nsecs'];
    }

    /**
     * @return array{secs:int,nsecs:int}
     */
    public static function timeFromUnixNanoseconds(int $nanoseconds): array
    {
        if ($nanoseconds < 0) {
            throw new \InvalidArgumentException('Unix nanoseconds must be non-negative');
        }

        return self::time(
            intdiv($nanoseconds, self::UNIX_NANOS_PER_SECOND),
            $nanoseconds % self::UNIX_NANOS_PER_SECOND,
        );
    }

    /**
     * @param array{
     *     mtime?:array{secs?:int,nsecs?:int},
     *     ctime?:array{secs?:int,nsecs?:int},
     *     dev?:int,
     *     ino?:int,
     *     uid?:int,
     *     gid?:int,
     *     size?:int
     * } $values
     * @return array{
     *     mtime:array{secs:int,nsecs:int},
     *     ctime:array{secs:int,nsecs:int},
     *     dev:int,
     *     ino:int,
     *     uid:int,
     *     gid:int,
     *     size:int
     * }
     */
    public static function stat(array $values = []): array
    {
        $defaults = [
            'mtime' => self::time(),
            'ctime' => self::time(),
            'dev' => 0,
            'ino' => 0,
            'uid' => 0,
            'gid' => 0,
            'size' => 0,
        ];
        self::assertKnownKeys('stat', $values, $defaults);
        $stat = array_replace($defaults, $values);
        if (!is_array($stat['mtime'])) {
            throw new \InvalidArgumentException('stat.mtime must be a time array');
        }
        if (!is_array($stat['ctime'])) {
            throw new \InvalidArgumentException('stat.ctime must be a time array');
        }
        $stat['mtime'] = self::normalizeTime($stat['mtime'], 'stat.mtime');
        $stat['ctime'] = self::normalizeTime($stat['ctime'], 'stat.ctime');

        foreach (['dev', 'ino', 'uid', 'gid', 'size'] as $field) {
            self::assertUInt32("stat.{$field}", $stat[$field]);
        }

        return $stat;
    }

    /**
     * @return array{
     *     mtime:array{secs:int,nsecs:int},
     *     ctime:array{secs:int,nsecs:int},
     *     dev:int,
     *     ino:int,
     *     uid:int,
     *     gid:int,
     *     size:int
     * }
     */
    public function statMetadata(): array
    {
        return self::stat([
            'mtime' => self::time($this->mtimeSecs, $this->mtimeNsecs),
            'ctime' => self::time($this->ctimeSecs, $this->ctimeNsecs),
            'dev' => $this->dev,
            'ino' => $this->ino,
            'uid' => $this->uid,
            'gid' => $this->gid,
            'size' => $this->size,
        ]);
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     * @param array<string,mixed> $options
     */
    public static function statMatches(array $left, array $right, array $options = []): bool
    {
        $left = self::stat($left);
        $right = self::stat($right);
        $options = self::statOptions($options);

        if ($left['mtime']['secs'] !== $right['mtime']['secs']) {
            return false;
        }
        if ($options['check_stat'] && $options['use_nsec'] && $left['mtime']['nsecs'] !== $right['mtime']['nsecs']) {
            return false;
        }

        if ($left['size'] !== $right['size']) {
            return false;
        }

        if ($options['trust_ctime']) {
            if ($left['ctime']['secs'] !== $right['ctime']['secs']) {
                return false;
            }
            if ($options['check_stat'] && $options['use_nsec'] && $left['ctime']['nsecs'] !== $right['ctime']['nsecs']) {
                return false;
            }
        }

        if ($options['check_stat']) {
            if ($options['use_stdev'] && $left['dev'] !== $right['dev']) {
                return false;
            }

            return $left['ino'] === $right['ino']
                && $left['gid'] === $right['gid']
                && $left['uid'] === $right['uid'];
        }

        return true;
    }

    /**
     * @param array<string,mixed> $stat
     * @param array{secs?:int,nsecs?:int} $timestamp
     * @param array<string,mixed> $options
     */
    public static function statIsRacy(array $stat, array $timestamp, array $options = []): bool
    {
        $stat = self::stat($stat);
        $timestamp = self::normalizeTime($timestamp, 'timestamp');
        $options = self::statOptions($options);

        if ($timestamp['secs'] < $stat['mtime']['secs']) {
            return true;
        }
        if ($timestamp['secs'] > $stat['mtime']['secs']) {
            return false;
        }
        if ($options['use_nsec'] && $options['check_stat']) {
            return $timestamp['nsecs'] <= $stat['mtime']['nsecs'];
        }

        return true;
    }

    public static function sizeOk(int $actualSize, int $expected64BitSize): bool
    {
        return PHP_INT_SIZE >= 8
            ? $actualSize === $expected64BitSize
            : $actualSize <= $expected64BitSize;
    }

    private static function assertStage(int $stage): void
    {
        if (!in_array($stage, [
            self::STAGE_NORMAL,
            self::STAGE_ANCESTOR,
            self::STAGE_OURS,
            self::STAGE_THEIRS,
        ], true)) {
            throw new \InvalidArgumentException("Unsupported index stage: {$stage}");
        }
    }

    private static function applyExecutableBitChange(string|int $mode): string
    {
        $mode = self::normalizedModeString($mode);

        return match ($mode) {
            self::MODE_FILE => self::MODE_FILE_EXECUTABLE,
            self::MODE_FILE_EXECUTABLE => self::MODE_FILE,
            default => throw new \LogicException("Cannot flip executable bit of index mode {$mode}"),
        };
    }

    private static function applyTypeChange(string|int|null $newMode): string
    {
        if ($newMode === null) {
            throw new \InvalidArgumentException('Type mode changes require a new mode');
        }
        $newMode = self::normalizedModeString($newMode);
        if (!in_array($newMode, self::knownModeStrings(), true)) {
            throw new \InvalidArgumentException("Unsupported index mode: {$newMode}");
        }

        return $newMode;
    }

    private static function normalizedModeString(string|int $mode): string
    {
        if (is_int($mode)) {
            self::assertUInt32('mode', $mode);

            return decoct($mode);
        }

        TreeEntry::assertValidMode($mode);

        return decoct(octdec($mode));
    }

    private static function modeBits(string|int $mode): int
    {
        return octdec(self::normalizedModeString($mode));
    }

    /**
     * @return array<string,int>
     */
    private static function knownModeBitsByName(): array
    {
        return [
            'DIR' => 0o040000,
            'FILE' => 0o100644,
            'FILE_EXECUTABLE' => 0o100755,
            'SYMLINK' => 0o120000,
            'COMMIT' => 0o160000,
        ];
    }

    /**
     * @return list<string>
     */
    private static function knownModeStrings(): array
    {
        return [
            self::MODE_DIR,
            self::MODE_FILE,
            self::MODE_FILE_EXECUTABLE,
            self::MODE_SYMLINK,
            self::MODE_COMMIT,
        ];
    }

    private static function allKnownModeBits(): int
    {
        $bits = 0;
        foreach (self::knownModeBitsByName() as $flagBits) {
            $bits |= $flagBits;
        }

        return $bits;
    }

    private static function modeDebugForBits(int $bits): string
    {
        $names = [];
        $namedBits = 0;
        foreach (self::knownModeBitsByName() as $name => $flagBits) {
            if (($bits & $flagBits) === $flagBits) {
                $names[] = $name;
                $namedBits |= $flagBits;
            }
        }

        $remaining = $bits & ~$namedBits;
        if ($remaining !== 0) {
            $names[] = '0x' . dechex($remaining);
        }
        if ($names === []) {
            $names[] = '0x0';
        }

        return 'Mode(' . implode(' | ', $names) . ')';
    }

    /**
     * @param array<string,mixed> $time
     * @return array{secs:int,nsecs:int}
     */
    private static function normalizeTime(array $time, string $label = 'time'): array
    {
        $defaults = ['secs' => 0, 'nsecs' => 0];
        self::assertKnownKeys($label, $time, $defaults);
        $time = array_replace($defaults, $time);
        self::assertUInt32("{$label}.secs", $time['secs']);
        self::assertNanoseconds("{$label}.nsecs", $time['nsecs']);

        return $time;
    }

    /**
     * @param array<string,mixed> $options
     * @return array{trust_ctime:bool,check_stat:bool,use_nsec:bool,use_stdev:bool}
     */
    private static function statOptions(array $options): array
    {
        $defaults = [
            'trust_ctime' => true,
            'check_stat' => true,
            'use_nsec' => false,
            'use_stdev' => false,
        ];
        self::assertKnownKeys('stat options', $options, $defaults);
        $options = array_replace($defaults, $options);
        foreach ($defaults as $field => $_) {
            if (!is_bool($options[$field])) {
                throw new \InvalidArgumentException("stat option {$field} must be a boolean");
            }
        }

        return $options;
    }

    /**
     * @param array<string,mixed> $values
     * @param array<string,mixed> $known
     */
    private static function assertKnownKeys(string $label, array $values, array $known): void
    {
        $unknown = array_diff_key($values, $known);
        if ($unknown !== []) {
            throw new \InvalidArgumentException($label . ' contains unknown keys: ' . implode(', ', array_keys($unknown)));
        }
    }

    private static function assertUInt32(string $field, mixed $value): void
    {
        if (!is_int($value) || $value < 0 || $value > self::UINT32_MAX) {
            throw new \InvalidArgumentException("{$field} must be an unsigned 32-bit integer");
        }
    }

    private static function assertNanoseconds(string $field, mixed $value): void
    {
        if (!is_int($value) || $value < 0 || $value > self::NSEC_MAX) {
            throw new \InvalidArgumentException("{$field} must be nanoseconds in the range 0..999999999");
        }
    }
}

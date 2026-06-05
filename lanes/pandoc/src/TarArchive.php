<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class TarArchive
{
    private const BLOCK_SIZE = 512;
    private const USTAR_MAGIC = "ustar\0";
    private const USTAR_VERSION = '00';
    private const TYPE_REGULAR = '0';
    private const TYPE_DIRECTORY = '5';
    private const TYPE_HARD_LINK = '1';
    private const TYPE_SYMBOLIC_LINK = '2';
    private const TYPE_PAX_EXTENDED = 'x';
    private const TYPE_PAX_GLOBAL = 'g';
    private const TYPE_GNU_LONG_NAME = 'L';
    private const TYPE_GNU_SPARSE = 'S';

    /**
     * @param array<string, TarArchiveEntry> $entriesByName
     * @param list<TarArchiveEntry> $entries
     * @param array<string, string> $globalPaxHeaders
     */
    private function __construct(
        private readonly string $bytes,
        private readonly array $entriesByName,
        private readonly array $entries,
        private readonly array $globalPaxHeaders,
    ) {
    }

    public static function fromString(string $bytes, ?int $maxUnpackedBytes = null): self
    {
        if ($bytes === '') {
            throw new \RuntimeException('TAR archive is empty');
        }

        if ($maxUnpackedBytes !== null && $maxUnpackedBytes < 0) {
            throw new \RuntimeException('TAR max unpacked byte limit must not be negative');
        }

        if (strlen($bytes) % self::BLOCK_SIZE !== 0) {
            throw new \RuntimeException('TAR archive length must be aligned to 512-byte records');
        }

        $cursor = 0;
        $length = strlen($bytes);
        $entries = [];
        $entriesByName = [];
        $pendingPaxHeaders = [];
        $globalPaxHeaders = [];
        $pendingGnuLongName = null;
        $totalUnpackedBytes = 0;
        $sawEndMarker = false;

        while ($cursor < $length) {
            $header = substr($bytes, $cursor, self::BLOCK_SIZE);
            if (self::isZeroBlock($header)) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                self::assertTrailingZeroBlocks($bytes, $cursor);
                $sawEndMarker = true;
                break;
            }

            self::validateHeaderChecksum($header);

            $typeFlag = substr($header, 156, 1);
            if ($typeFlag === "\0" || $typeFlag === '') {
                $typeFlag = self::TYPE_REGULAR;
            }

            $headerSize = self::readNumericField(substr($header, 124, 12), 'TAR entry size');
            $dataOffset = $cursor + self::BLOCK_SIZE;
            self::assertRange($bytes, $dataOffset, $headerSize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($headerSize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }

            if ($typeFlag === self::TYPE_GNU_LONG_NAME) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongName = self::parseGnuLongName(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_PAX_EXTENDED || $typeFlag === self::TYPE_PAX_GLOBAL) {
                $headers = self::parsePaxHeaders(substr($bytes, $dataOffset, $headerSize));
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($typeFlag === self::TYPE_PAX_EXTENDED) {
                    $pendingPaxHeaders = $headers;
                } else {
                    $globalPaxHeaders = array_merge($globalPaxHeaders, $headers);
                }
                $cursor = $nextCursor;
                continue;
            }

            $metadataHeaders = array_merge($globalPaxHeaders, $pendingPaxHeaders);
            $name = self::resolvedNameFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            self::assertSafePath($name, 'TAR entry name');
            $size = self::resolvedSizeFromHeader($header, $metadataHeaders);
            self::assertRange($bytes, $dataOffset, $size, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($size);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }

            if ($typeFlag === self::TYPE_HARD_LINK || $typeFlag === self::TYPE_SYMBOLIC_LINK) {
                throw new \RuntimeException("TAR link entries are not supported by the pandoc archive reader: {$name}");
            }

            if ($typeFlag === self::TYPE_GNU_SPARSE || self::hasSparsePaxHeaders($metadataHeaders)) {
                throw new \RuntimeException("TAR sparse file entries are not supported by the pandoc archive reader: {$name}");
            }

            if ($typeFlag !== self::TYPE_REGULAR && $typeFlag !== self::TYPE_DIRECTORY) {
                throw new \RuntimeException("Unsupported TAR entry type {$typeFlag} for {$name}");
            }

            $entryType = $typeFlag === self::TYPE_DIRECTORY
                ? TarArchiveEntry::TYPE_DIRECTORY
                : TarArchiveEntry::TYPE_FILE;
            if ($entryType === TarArchiveEntry::TYPE_DIRECTORY && $size !== 0) {
                throw new \RuntimeException("TAR directory entry {$name} must not contain payload bytes");
            }

            if ($entryType === TarArchiveEntry::TYPE_FILE) {
                $totalUnpackedBytes += $size;
                if ($maxUnpackedBytes !== null && $totalUnpackedBytes > $maxUnpackedBytes) {
                    throw new \RuntimeException('TAR archive exceeds the configured unpacked byte limit');
                }
            }

            if (isset($entriesByName[$name])) {
                throw new \RuntimeException("Duplicate TAR archive entry: {$name}");
            }

            $entry = new TarArchiveEntry(
                $name,
                $entryType,
                $size,
                self::resolvedModifiedAtFromHeader($header, $metadataHeaders),
                self::readNumericField(substr($header, 100, 8), "TAR mode for {$name}"),
                self::resolvedUidFromHeader($header, $metadataHeaders, $name),
                self::resolvedGidFromHeader($header, $metadataHeaders, $name),
                self::trimNullField(substr($header, 157, 100)),
                self::resolvedUserNameFromHeader($header, $metadataHeaders),
                self::resolvedGroupNameFromHeader($header, $metadataHeaders),
                $metadataHeaders,
                $dataOffset
            );

            $entries[] = $entry;
            $entriesByName[$name] = $entry;
            $pendingPaxHeaders = [];
            $pendingGnuLongName = null;
            $cursor = $nextCursor;
        }

        if (!$sawEndMarker) {
            throw new \RuntimeException('TAR archive is missing the required two-block end marker');
        }

        return new self($bytes, $entriesByName, $entries, $globalPaxHeaders);
    }

    /**
     * @param list<array{name:string, data?:string, type?:string, modifiedAt?:int, mode?:int, uid?:int, gid?:int, userName?:string, groupName?:string}> $entries
     * @param array{globalPaxHeaders?:array<string, string>} $options
     */
    public static function fromEntries(array $entries, array $options = []): self
    {
        return self::fromString(self::build($entries, $options));
    }

    /**
     * @param list<array{name:string, data?:string, type?:string, modifiedAt?:int, mode?:int, uid?:int, gid?:int, userName?:string, groupName?:string}> $entries
     * @param array{globalPaxHeaders?:array<string, string>} $options
     */
    public static function build(array $entries, array $options = []): string
    {
        $bytes = '';
        $names = [];
        $globalPaxHeaders = self::normalizePaxHeaders($options['globalPaxHeaders'] ?? [], 'TAR global PAX headers');

        if ($globalPaxHeaders !== []) {
            $globalPayload = self::buildPaxPayload($globalPaxHeaders);
            $bytes .= self::buildHeader('GlobalHead/PaxGlobal', self::TYPE_PAX_GLOBAL, strlen($globalPayload), [
                'mode' => 0644,
                'uid' => 0,
                'gid' => 0,
                'modifiedAt' => 0,
                'userName' => '',
                'groupName' => '',
            ]);
            $bytes .= $globalPayload . str_repeat("\0", self::paddingSize(strlen($globalPayload)));
        }

        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                throw new \RuntimeException("TAR archive entry {$index} must be an array");
            }

            if (!isset($entry['name']) || !is_string($entry['name'])) {
                throw new \RuntimeException("TAR archive entry {$index} is missing a string name");
            }

            $name = $entry['name'];
            self::assertSafePath($name, "TAR archive entry {$index} name");
            if (isset($names[$name])) {
                throw new \RuntimeException("Duplicate TAR archive entry: {$name}");
            }
            $names[$name] = true;

            $type = $entry['type'] ?? (str_ends_with($name, '/') ? TarArchiveEntry::TYPE_DIRECTORY : TarArchiveEntry::TYPE_FILE);
            if ($type !== TarArchiveEntry::TYPE_FILE && $type !== TarArchiveEntry::TYPE_DIRECTORY) {
                throw new \RuntimeException("Unsupported TAR archive entry type for {$name}");
            }

            $data = $entry['data'] ?? '';
            if (!is_string($data)) {
                throw new \RuntimeException("TAR archive entry {$name} data must be a string");
            }

            if ($type === TarArchiveEntry::TYPE_DIRECTORY && $data !== '') {
                throw new \RuntimeException("TAR directory entry {$name} must not contain file data");
            }

            $modifiedAt = $entry['modifiedAt'] ?? 0;
            self::assertNonNegativeInt($modifiedAt, "TAR entry {$name} modifiedAt");

            $mode = $entry['mode'] ?? ($type === TarArchiveEntry::TYPE_DIRECTORY ? 0755 : 0644);
            self::assertOctalFieldValue($mode, 8, "TAR entry {$name} mode");
            $uid = $entry['uid'] ?? 0;
            self::assertOctalFieldValue($uid, 8, "TAR entry {$name} uid");
            $gid = $entry['gid'] ?? 0;
            self::assertOctalFieldValue($gid, 8, "TAR entry {$name} gid");

            $userName = $entry['userName'] ?? '';
            $groupName = $entry['groupName'] ?? '';
            if (!is_string($userName) || !is_string($groupName)) {
                throw new \RuntimeException("TAR entry {$name} user and group names must be strings");
            }

            $typeFlag = $type === TarArchiveEntry::TYPE_DIRECTORY ? self::TYPE_DIRECTORY : self::TYPE_REGULAR;
            $headerName = $name;
            $paxHeaders = [];
            if (self::splitUstarPath($name) === null) {
                $paxHeaders['path'] = $name;
                $headerName = 'PaxFiles/' . substr(sha1($name), 0, 24);
            }

            $headerOptions = [
                'mode' => $mode,
                'uid' => $uid,
                'gid' => $gid,
                'modifiedAt' => $modifiedAt,
                'userName' => $userName,
                'groupName' => $groupName,
            ];

            if ($paxHeaders !== []) {
                $paxPayload = self::buildPaxPayload($paxHeaders);
                $paxName = 'PaxHeaders/' . substr(sha1($name), 0, 24);
                $bytes .= self::buildHeader($paxName, self::TYPE_PAX_EXTENDED, strlen($paxPayload), $headerOptions);
                $bytes .= $paxPayload . str_repeat("\0", self::paddingSize(strlen($paxPayload)));
            }

            $bytes .= self::buildHeader($headerName, $typeFlag, strlen($data), $headerOptions);
            $bytes .= $data . str_repeat("\0", self::paddingSize(strlen($data)));
        }

        return $bytes . str_repeat("\0", self::BLOCK_SIZE * 2);
    }

    /**
     * @return list<TarArchiveEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (TarArchiveEntry $entry): string => $entry->name, $this->entries);
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    /**
     * @return array<string, string>
     */
    public function globalPaxHeaders(): array
    {
        return $this->globalPaxHeaders;
    }

    public function has(string $name): bool
    {
        $path = ltrim($name, '/');
        self::assertSafePath($path, 'TAR entry lookup name');

        return isset($this->entriesByName[$path]);
    }

    public function entry(string $name): TarArchiveEntry
    {
        $path = ltrim($name, '/');
        self::assertSafePath($path, 'TAR entry lookup name');
        if (!isset($this->entriesByName[$path])) {
            throw new \RuntimeException("TAR archive entry not found: {$name}");
        }

        return $this->entriesByName[$path];
    }

    public function read(string $name): string
    {
        $entry = $this->entry($name);
        if ($entry->isDirectory()) {
            return '';
        }

        return substr($this->bytes, $entry->dataOffset, $entry->size);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function buildHeader(string $name, string $typeFlag, int $size, array $options): string
    {
        $path = self::splitUstarPath($name);
        if ($path === null) {
            throw new \RuntimeException("TAR entry name {$name} is too long for a ustar header without PAX metadata");
        }

        $mode = $options['mode'] ?? 0644;
        $uid = $options['uid'] ?? 0;
        $gid = $options['gid'] ?? 0;
        $modifiedAt = $options['modifiedAt'] ?? 0;
        $userName = $options['userName'] ?? '';
        $groupName = $options['groupName'] ?? '';

        self::assertOctalFieldValue($size, 12, "TAR entry {$name} size");
        self::assertOctalFieldValue($modifiedAt, 12, "TAR entry {$name} modifiedAt");

        $header = self::stringField($path['name'], 100)
            . self::octalField($mode, 8)
            . self::octalField($uid, 8)
            . self::octalField($gid, 8)
            . self::octalField($size, 12)
            . self::octalField($modifiedAt, 12)
            . str_repeat(' ', 8)
            . $typeFlag
            . self::stringField('', 100)
            . self::USTAR_MAGIC
            . self::USTAR_VERSION
            . self::stringField($userName, 32)
            . self::stringField($groupName, 32)
            . self::octalField(0, 8)
            . self::octalField(0, 8)
            . self::stringField($path['prefix'], 155)
            . str_repeat("\0", 12);

        if (strlen($header) !== self::BLOCK_SIZE) {
            throw new \RuntimeException('Internal TAR header construction error');
        }

        $checksum = self::checksum($header);
        $checksumField = sprintf('%06o', $checksum) . "\0 ";

        return substr_replace($header, $checksumField, 148, 8);
    }

    /**
     * @return array{name:string, prefix:string}|null
     */
    private static function splitUstarPath(string $name): ?array
    {
        if (strlen($name) <= 100) {
            return ['name' => $name, 'prefix' => ''];
        }

        $segments = explode('/', $name);
        for ($index = count($segments) - 1; $index > 0; $index--) {
            $prefix = implode('/', array_slice($segments, 0, $index));
            $basename = implode('/', array_slice($segments, $index));
            if (strlen($prefix) <= 155 && strlen($basename) <= 100) {
                return ['name' => $basename, 'prefix' => $prefix];
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedNameFromHeader(string $header, array $headers, ?string $gnuLongName): string
    {
        if (isset($headers['path'])) {
            return $headers['path'];
        }

        if ($gnuLongName !== null) {
            return $gnuLongName;
        }

        $name = self::trimNullField(substr($header, 0, 100));
        $prefix = self::trimNullField(substr($header, 345, 155));

        return $prefix === '' ? $name : $prefix . '/' . $name;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedSizeFromHeader(string $header, array $headers): int
    {
        if (isset($headers['size'])) {
            return self::parsePaxNonNegativeInteger($headers['size'], 'TAR PAX size');
        }

        return self::readNumericField(substr($header, 124, 12), 'TAR entry size');
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedModifiedAtFromHeader(string $header, array $headers): int
    {
        if (isset($headers['mtime'])) {
            return self::parsePaxIntegerTimestamp($headers['mtime'], 'TAR PAX mtime');
        }

        return self::readNumericField(substr($header, 136, 12), 'TAR entry mtime');
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedUidFromHeader(string $header, array $headers, string $name): int
    {
        if (isset($headers['uid'])) {
            return self::parsePaxNonNegativeInteger($headers['uid'], "TAR PAX uid for {$name}");
        }

        return self::readNumericField(substr($header, 108, 8), "TAR uid for {$name}");
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedGidFromHeader(string $header, array $headers, string $name): int
    {
        if (isset($headers['gid'])) {
            return self::parsePaxNonNegativeInteger($headers['gid'], "TAR PAX gid for {$name}");
        }

        return self::readNumericField(substr($header, 116, 8), "TAR gid for {$name}");
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedUserNameFromHeader(string $header, array $headers): string
    {
        return $headers['uname'] ?? self::trimNullField(substr($header, 265, 32));
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedGroupNameFromHeader(string $header, array $headers): string
    {
        return $headers['gname'] ?? self::trimNullField(substr($header, 297, 32));
    }

    private static function validateHeaderChecksum(string $header): void
    {
        $stored = self::readOctalField(substr($header, 148, 8), 'TAR header checksum');
        $actual = self::checksum(substr_replace($header, str_repeat(' ', 8), 148, 8));
        if ($stored !== $actual) {
            throw new \RuntimeException('TAR header checksum does not match header bytes');
        }

        $magic = substr($header, 257, 6);
        if ($magic !== self::USTAR_MAGIC && self::trimNullField($magic) !== '') {
            throw new \RuntimeException('Unsupported TAR header magic');
        }

        if ($magic === self::USTAR_MAGIC && substr($header, 263, 2) !== self::USTAR_VERSION) {
            throw new \RuntimeException('Unsupported TAR header ustar version');
        }
    }

    private static function checksum(string $header): int
    {
        $sum = 0;
        for ($index = 0, $length = strlen($header); $index < $length; $index++) {
            $sum += ord($header[$index]);
        }

        return $sum;
    }

    private static function readOctalField(string $field, string $label): int
    {
        $value = trim($field, " \0");
        if ($value === '') {
            return 0;
        }

        if (!preg_match('/^[0-7]+$/', $value)) {
            throw new \RuntimeException("{$label} is not a supported octal TAR field");
        }

        return intval($value, 8);
    }

    private static function readNumericField(string $field, string $label): int
    {
        if ($field !== '' && (ord($field[0]) & 0x80) !== 0) {
            return self::readBase256Field($field, $label);
        }

        return self::readOctalField($field, $label);
    }

    private static function readBase256Field(string $field, string $label): int
    {
        if ($field === '') {
            return 0;
        }

        $first = ord($field[0]);
        if (($first & 0x40) !== 0) {
            throw new \RuntimeException("{$label} is a negative base-256 TAR field, which is not supported");
        }

        $field[0] = chr($first & 0x7f);
        $value = 0;
        for ($index = 0, $length = strlen($field); $index < $length; $index++) {
            $byte = ord($field[$index]);
            if ($value > intdiv(PHP_INT_MAX - $byte, 256)) {
                throw new \RuntimeException("{$label} is too large for this PHP runtime");
            }
            $value = ($value * 256) + $byte;
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private static function parsePaxHeaders(string $bytes): array
    {
        $headers = [];
        $cursor = 0;
        $length = strlen($bytes);

        while ($cursor < $length) {
            $space = strpos($bytes, ' ', $cursor);
            if ($space === false) {
                throw new \RuntimeException('TAR PAX header record is missing a length separator');
            }

            $lengthText = substr($bytes, $cursor, $space - $cursor);
            if ($lengthText === '' || !ctype_digit($lengthText)) {
                throw new \RuntimeException('TAR PAX header record length is invalid');
            }

            $recordLength = (int) $lengthText;
            if ($recordLength <= 0 || $cursor + $recordLength > $length) {
                throw new \RuntimeException('TAR PAX header record extends beyond payload bytes');
            }

            $record = substr($bytes, $cursor, $recordLength);
            if (!str_ends_with($record, "\n")) {
                throw new \RuntimeException('TAR PAX header record must end with a newline');
            }

            $recordBody = substr($record, strlen($lengthText) + 1, -1);
            $equals = strpos($recordBody, '=');
            if ($equals === false || $equals === 0) {
                throw new \RuntimeException('TAR PAX header record is missing a key/value separator');
            }

            $key = substr($recordBody, 0, $equals);
            $value = substr($recordBody, $equals + 1);
            if (str_contains($key, "\0") || str_contains($value, "\0")) {
                throw new \RuntimeException('TAR PAX header records must not contain NUL bytes');
            }

            $headers[$key] = $value;
            $cursor += $recordLength;
        }

        return $headers;
    }

    private static function parseGnuLongName(string $bytes): string
    {
        $name = rtrim($bytes, "\0");
        self::assertSafePath($name, 'TAR GNU long name');

        return $name;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function hasSparsePaxHeaders(array $headers): bool
    {
        foreach ($headers as $key => $value) {
            if (str_starts_with($key, 'GNU.sparse.')) {
                return true;
            }

            if (str_starts_with($key, 'SCHILY.sparse.')) {
                return true;
            }

            if ($key === 'SCHILY.filetype' && strtolower(trim($value)) === 'sparse') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function buildPaxPayload(array $headers): string
    {
        $headers = self::normalizePaxHeaders($headers, 'TAR PAX headers');
        $payload = '';
        foreach ($headers as $key => $value) {
            $payload .= self::buildPaxRecord($key, $value);
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private static function normalizePaxHeaders(mixed $headers, string $label): array
    {
        if (!is_array($headers)) {
            throw new \RuntimeException("{$label} must be an associative array");
        }

        $normalized = [];
        foreach ($headers as $key => $value) {
            if (!is_string($key) || $key === '' || str_contains($key, "\0") || str_contains($key, '=')) {
                throw new \RuntimeException("{$label} keys must be non-empty strings without NUL bytes or equals signs");
            }

            if (!is_string($value) || str_contains($value, "\0")) {
                throw new \RuntimeException("{$label} values must be strings without NUL bytes");
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private static function buildPaxRecord(string $key, string $value): string
    {
        $body = " {$key}={$value}\n";
        $recordLength = strlen($body) + 1;
        do {
            $nextLength = strlen((string) $recordLength) + strlen($body);
            if ($nextLength === $recordLength) {
                return $recordLength . $body;
            }
            $recordLength = $nextLength;
        } while (true);
    }

    private static function parsePaxIntegerTimestamp(string $value, string $label): int
    {
        if (!preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new \RuntimeException("{$label} is not a supported non-negative timestamp");
        }

        return (int) floor((float) $value);
    }

    private static function parsePaxNonNegativeInteger(string $value, string $label): int
    {
        if ($value === '' || !ctype_digit($value)) {
            throw new \RuntimeException("{$label} is not a supported non-negative integer");
        }

        $integer = (int) $value;
        if ((string) $integer !== ltrim($value, '0') && ltrim($value, '0') !== '') {
            throw new \RuntimeException("{$label} is too large for this PHP runtime");
        }

        return $integer;
    }

    private static function isZeroBlock(string $block): bool
    {
        return $block === str_repeat("\0", self::BLOCK_SIZE);
    }

    private static function assertTrailingZeroBlocks(string $bytes, int $offset): void
    {
        $remaining = substr($bytes, $offset);
        if (strlen($remaining) < self::BLOCK_SIZE * 2) {
            throw new \RuntimeException('TAR archive end marker must contain two zero blocks');
        }

        if (trim($remaining, "\0") !== '') {
            throw new \RuntimeException('TAR archive contains non-zero bytes after the end marker');
        }
    }

    private static function paddedSize(int $size): int
    {
        return $size + self::paddingSize($size);
    }

    private static function paddingSize(int $size): int
    {
        $remainder = $size % self::BLOCK_SIZE;

        return $remainder === 0 ? 0 : self::BLOCK_SIZE - $remainder;
    }

    private static function octalField(int $value, int $length): string
    {
        self::assertOctalFieldValue($value, $length, 'TAR numeric field');
        $digits = $length - 1;

        return str_pad(decoct($value), $digits, '0', STR_PAD_LEFT) . "\0";
    }

    private static function assertOctalFieldValue(mixed $value, int $length, string $label): void
    {
        if (!is_int($value) || $value < 0) {
            throw new \RuntimeException("{$label} must be a non-negative integer");
        }

        $max = intval(str_repeat('7', $length - 1), 8);
        if ($value > $max) {
            throw new \RuntimeException("{$label} is too large for a bounded TAR octal field");
        }
    }

    private static function assertNonNegativeInt(mixed $value, string $label): void
    {
        if (!is_int($value) || $value < 0) {
            throw new \RuntimeException("{$label} must be a non-negative integer");
        }
    }

    private static function stringField(string $value, int $length): string
    {
        if (strlen($value) > $length) {
            throw new \RuntimeException('TAR string field is too long');
        }

        if (str_contains($value, "\0")) {
            throw new \RuntimeException('TAR string fields must not contain NUL bytes');
        }

        return str_pad($value, $length, "\0");
    }

    private static function trimNullField(string $field): string
    {
        return rtrim($field, "\0");
    }

    private static function assertSafePath(string $path, string $label): void
    {
        if ($path === '') {
            throw new \RuntimeException("{$label} must not be empty");
        }

        if (str_contains($path, "\0") || str_starts_with($path, '/') || str_contains($path, '\\')) {
            throw new \RuntimeException("Unsafe {$label}: {$path}");
        }

        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            throw new \RuntimeException("Unsafe {$label}: {$path}");
        }

        $segments = explode('/', $path);
        foreach ($segments as $index => $segment) {
            $isTrailingDirectorySegment = $index === count($segments) - 1 && $segment === '';
            if ($isTrailingDirectorySegment) {
                continue;
            }

            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \RuntimeException("Unsafe {$label}: {$path}");
            }
        }
    }

    private static function assertRange(string $bytes, int $offset, int $length, string $label): void
    {
        if ($offset < 0 || $length < 0 || $offset > strlen($bytes) || $offset + $length > strlen($bytes)) {
            throw new \RuntimeException("TAR archive {$label} extends beyond available bytes");
        }
    }
}

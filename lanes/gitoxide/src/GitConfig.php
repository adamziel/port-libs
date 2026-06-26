<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitConfig
{
    private const WILDMATCH_RECURSION_LIMIT = 64;

    /**
     * @param list<array{name:string,rawName:string,subsection:?string,entries:list<array{key:string,value:string,implicit?:bool}>,path:?string}> $sections
     */
    private function __construct(private array $sections)
    {
    }

    /**
     * @param array{
     *     gitDir?: ?string,
     *     branchName?: ?string,
     *     homeDir?: ?string,
     *     userHomeDirs?: array<string,string>,
     *     installPrefix?: ?string,
     *     maxDepth?: int,
     *     errOnMaxDepthExceeded?: bool,
     *     errOnMissingConfigPath?: bool,
     *     errOnInterpolationFailure?: bool
     * } $options
     */
    public static function fromFile(string $path, array $options = []): self
    {
        $normalized = self::normalizeOptions($options);
        $config = self::parseFile($path);
        self::resolveIncludes($config, null, 0, $normalized);

        return new self($config['sections']);
    }

    /**
     * @param array{
     *     gitDir?: ?string,
     *     branchName?: ?string,
     *     homeDir?: ?string,
     *     userHomeDirs?: array<string,string>,
     *     installPrefix?: ?string,
     *     maxDepth?: int,
     *     errOnMaxDepthExceeded?: bool,
     *     errOnMissingConfigPath?: bool,
     *     errOnInterpolationFailure?: bool
     * } $options
     */
    public static function fromString(string $contents, ?string $path = null, array $options = []): self
    {
        $normalized = self::normalizeOptions($options);
        $config = self::parse($contents, $path);
        self::resolveIncludes($config, null, 0, $normalized);

        return new self($config['sections']);
    }

    /**
     * Build a config from caller-supplied `GIT_CONFIG_KEY_N` /
     * `GIT_CONFIG_VALUE_N` style entries without reading the real process
     * environment.
     *
     * @param array<int|string, array{key?:string,value?:string,0?:string,1?:string}|string> $entries
     * @param array{
     *     gitDir?: ?string,
     *     branchName?: ?string,
     *     homeDir?: ?string,
     *     userHomeDirs?: array<string,string>,
     *     installPrefix?: ?string,
     *     maxDepth?: int,
     *     errOnMaxDepthExceeded?: bool,
     *     errOnMissingConfigPath?: bool,
     *     errOnInterpolationFailure?: bool
     * } $options
     */
    public static function fromEnvironmentPairs(array $entries, array $options = []): self
    {
        $normalized = self::normalizeOptions($options);
        $config = self::parseEnvironmentPairs($entries);
        self::resolveIncludes($config, null, 0, $normalized);

        return new self($config['sections']);
    }

    /**
     * @return list<string>
     */
    public function values(string $section, ?string $subsection, string $key): array
    {
        $section = strtolower($section);
        $key = strtolower($key);
        $values = [];

        foreach ($this->sections as $entrySection) {
            if ($entrySection['name'] !== $section || $entrySection['subsection'] !== $subsection) {
                continue;
            }

            foreach ($entrySection['entries'] as $entry) {
                if ($entry['key'] === $key) {
                    $values[] = $entry['value'];
                }
            }
        }

        return $values;
    }

    public function value(string $section, ?string $subsection, string $key): ?string
    {
        $values = $this->values($section, $subsection, $key);

        return $values === [] ? null : $values[array_key_last($values)];
    }

    /**
     * @return list<string>
     */
    public function rawValues(string $section, ?string $subsection, string $key): array
    {
        $section = strtolower($section);
        $key = strtolower($key);
        $values = [];

        foreach ($this->sections as $entrySection) {
            if ($entrySection['name'] !== $section || $entrySection['subsection'] !== $subsection) {
                continue;
            }

            foreach ($entrySection['entries'] as $entry) {
                if ($entry['key'] === $key && !($entry['implicit'] ?? false)) {
                    $values[] = $entry['value'];
                }
            }
        }

        return $values;
    }

    public function rawValue(string $section, ?string $subsection, string $key): ?string
    {
        $values = $this->rawValues($section, $subsection, $key);

        return $values === [] ? null : $values[array_key_last($values)];
    }

    public function setRawValueBy(string $section, ?string $subsection, string $key, string $value): void
    {
        $sectionName = self::validateSectionName($section);
        if ($subsection !== null) {
            self::validateSubsection($subsection);
        }
        $valueName = self::validateValueName($key);
        $sectionIndex = null;
        $entryIndex = null;

        foreach ($this->sections as $index => $entrySection) {
            if ($entrySection['name'] !== strtolower($sectionName) || $entrySection['subsection'] !== $subsection) {
                continue;
            }

            $sectionIndex = $index;
            foreach ($entrySection['entries'] as $candidateEntryIndex => $entry) {
                if ($entry['key'] === strtolower($valueName)) {
                    $entryIndex = $candidateEntryIndex;
                }
            }
        }

        if ($sectionIndex === null) {
            $this->sections[] = [
                'name' => strtolower($sectionName),
                'rawName' => $sectionName,
                'subsection' => $subsection,
                'entries' => [],
                'path' => null,
            ];
            $sectionIndex = array_key_last($this->sections);
        }

        $entry = ['key' => strtolower($valueName), 'value' => $value, 'implicit' => false];
        if ($entryIndex === null) {
            $this->sections[$sectionIndex]['entries'][] = $entry;
            return;
        }

        $this->sections[$sectionIndex]['entries'][$entryIndex] = $entry;
    }

    public function setExistingRawValueBy(string $section, ?string $subsection, string $key, string $value): void
    {
        $sectionName = self::validateSectionName($section);
        if ($subsection !== null) {
            self::validateSubsection($subsection);
        }
        $valueName = self::validateValueName($key);
        $sectionIndex = null;
        $entryIndex = null;

        foreach ($this->sections as $index => $entrySection) {
            if ($entrySection['name'] !== strtolower($sectionName) || $entrySection['subsection'] !== $subsection) {
                continue;
            }

            $sectionIndex = $index;
            foreach ($entrySection['entries'] as $candidateEntryIndex => $entry) {
                if ($entry['key'] === strtolower($valueName) && !($entry['implicit'] ?? false)) {
                    $entryIndex = $candidateEntryIndex;
                }
            }
        }

        if ($sectionIndex === null || $entryIndex === null) {
            throw new \RuntimeException('The requested Git config value does not exist');
        }

        $this->sections[$sectionIndex]['entries'][$entryIndex] = [
            'key' => strtolower($valueName),
            'value' => $value,
            'implicit' => false,
        ];
    }

    public function toString(): string
    {
        $out = '';
        foreach ($this->sections as $section) {
            $out .= self::formatSectionHeader($section['rawName'], $section['subsection']) . "\n";
            foreach ($section['entries'] as $entry) {
                $out .= "\t" . $entry['key'];
                if ($entry['implicit'] ?? false) {
                    $out .= "\n";
                    continue;
                }
                $out .= ' = ' . self::formatValue($entry['value']) . "\n";
            }
        }

        return $out;
    }

    /**
     * @return list<array{name:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}>
     */
    public function sections(): array
    {
        return array_map(
            static fn (array $section): array => [
                'name' => $section['name'],
                'subsection' => $section['subsection'],
                'entries' => $section['entries'],
                'path' => $section['path'],
            ],
            $this->sections,
        );
    }

    /**
     * @param array<string, mixed> $options
     * @return array{
     *     gitDir: ?string,
     *     branchName: ?string,
     *     homeDir: ?string,
     *     userHomeDirs: array<string,string>,
     *     installPrefix: ?string,
     *     maxDepth: int,
     *     errOnMaxDepthExceeded: bool,
     *     errOnMissingConfigPath: bool,
     *     errOnInterpolationFailure: bool
     * }
     */
    private static function normalizeOptions(array $options): array
    {
        $maxDepth = (int) ($options['maxDepth'] ?? 10);
        if ($maxDepth < 0 || $maxDepth > 255) {
            throw new \InvalidArgumentException('Git config include maxDepth must be between 0 and 255');
        }

        return [
            'gitDir' => isset($options['gitDir']) ? (string) $options['gitDir'] : null,
            'branchName' => isset($options['branchName']) ? (string) $options['branchName'] : null,
            'homeDir' => isset($options['homeDir']) ? (string) $options['homeDir'] : null,
            'userHomeDirs' => self::normalizeUserHomeDirs($options['userHomeDirs'] ?? []),
            'installPrefix' => isset($options['installPrefix']) ? (string) $options['installPrefix'] : null,
            'maxDepth' => $maxDepth,
            'errOnMaxDepthExceeded' => (bool) ($options['errOnMaxDepthExceeded'] ?? true),
            'errOnMissingConfigPath' => (bool) ($options['errOnMissingConfigPath'] ?? true),
            'errOnInterpolationFailure' => (bool) ($options['errOnInterpolationFailure'] ?? false),
        ];
    }

    /**
     * @param mixed $homeDirs
     * @return array<string,string>
     */
    private static function normalizeUserHomeDirs(mixed $homeDirs): array
    {
        if (!is_array($homeDirs)) {
            throw new \InvalidArgumentException('Git config named user home directories must be an array');
        }

        $normalized = [];
        foreach ($homeDirs as $user => $homeDir) {
            if (!is_string($user) || $user === '' || !is_string($homeDir) || $homeDir === '') {
                throw new \InvalidArgumentException('Git config named user home directory mappings require non-empty string keys and values');
            }

            $normalized[$user] = $homeDir;
        }

        return $normalized;
    }

    /**
     * @return array{sections:list<array{name:string,rawName:string,subsection:?string,entries:list<array{key:string,value:string,implicit?:bool}>,path:?string}>,path:?string}
     */
    private static function parseFile(string $path): array
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException("Unable to read Git config file: {$path}");
        }

        return self::parse($bytes, $path);
    }

    /**
     * @return array{sections:list<array{name:string,rawName:string,subsection:?string,entries:list<array{key:string,value:string,implicit?:bool}>,path:?string}>,path:?string}
     */
    private static function parse(string $contents, ?string $path): array
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        $sections = [];
        $current = null;
        $logicalLines = self::logicalLines($contents);

        foreach ($logicalLines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#' || $trimmed[0] === ';') {
                continue;
            }

            if (preg_match('/^\s*\[([A-Za-z0-9.-]+)(?:\s+"((?:\\\\.|[^"])*)")?\]\s*(.*)$/', $line, $matches, PREG_UNMATCHED_AS_NULL) === 1) {
                [$sectionName, $subsection] = self::parseSectionHeaderParts(
                    $matches[1],
                    $matches[2] !== null ? self::unquoteSubsection($matches[2]) : null,
                );
                $section = [
                    'name' => strtolower($sectionName),
                    'rawName' => $sectionName,
                    'subsection' => $subsection,
                    'entries' => [],
                    'path' => $path,
                ];
                $sections[] = $section;
                $current = array_key_last($sections);

                $line = trim($matches[3] ?? '');
                if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                    continue;
                }
            } elseif (str_starts_with(ltrim($line), '[')) {
                throw new \RuntimeException("Invalid Git config section header: {$line}");
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/^\s*([A-Za-z][A-Za-z0-9-]*)\s*(?:=\s*(.*))?$/', $line, $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
                throw new \RuntimeException("Invalid Git config value line: {$line}");
            }

            $implicit = $matches[2] === null;
            $sections[$current]['entries'][] = [
                'key' => strtolower($matches[1]),
                'value' => $implicit ? 'true' : self::parseValue($matches[2]),
                'implicit' => $implicit,
            ];
        }

        return ['sections' => $sections, 'path' => $path];
    }

    /**
     * @param array<int|string, array{key?:string,value?:string,0?:string,1?:string}|string> $entries
     * @return array{sections:list<array{name:string,rawName:string,subsection:?string,entries:list<array{key:string,value:string,implicit?:bool}>,path:?string}>,path:?string}
     */
    private static function parseEnvironmentPairs(array $entries): array
    {
        $sections = [];

        foreach ($entries as $entryKey => $entry) {
            if (is_array($entry)) {
                $key = (string) ($entry['key'] ?? $entry[0] ?? '');
                $value = (string) ($entry['value'] ?? $entry[1] ?? '');
            } elseif (is_string($entryKey)) {
                $key = $entryKey;
                $value = (string) $entry;
            } else {
                throw new \InvalidArgumentException('Git config environment pair must provide a key and value');
            }

            $parsed = self::parseEnvironmentKey($key);
            if ($parsed === null) {
                throw new \InvalidArgumentException("Invalid Git config environment key: {$key}");
            }

            $sectionIndex = null;
            foreach ($sections as $index => $section) {
                if ($section['name'] === $parsed['name'] && $section['subsection'] === $parsed['subsection']) {
                    $sectionIndex = $index;
                    break;
                }
            }

            if ($sectionIndex === null) {
                $sections[] = [
                    'name' => $parsed['name'],
                    'rawName' => $parsed['rawName'],
                    'subsection' => $parsed['subsection'],
                    'entries' => [],
                    'path' => null,
                ];
                $sectionIndex = array_key_last($sections);
            }

            $sections[$sectionIndex]['entries'][] = [
                'key' => $parsed['key'],
                'value' => $value,
                'implicit' => false,
            ];
        }

        return ['sections' => $sections, 'path' => null];
    }

    /**
     * @return array{name:string,rawName:string,subsection:?string,key:string}|null
     */
    private static function parseEnvironmentKey(string $key): ?array
    {
        $lastDot = strrpos($key, '.');
        if ($lastDot === false || $lastDot === 0 || $lastDot === strlen($key) - 1) {
            return null;
        }

        $sectionAndSubsection = substr($key, 0, $lastDot);
        $valueName = substr($key, $lastDot + 1);
        if (preg_match('/^[A-Za-z][A-Za-z0-9-]*$/', $valueName) !== 1) {
            return null;
        }

        $firstDot = strpos($sectionAndSubsection, '.');
        if ($firstDot === false) {
            $sectionName = $sectionAndSubsection;
            $subsection = null;
        } else {
            $sectionName = substr($sectionAndSubsection, 0, $firstDot);
            $subsection = substr($sectionAndSubsection, $firstDot + 1);
            if ($subsection === '') {
                return null;
            }
        }

        if (preg_match('/^[A-Za-z0-9-]+$/', $sectionName) !== 1) {
            return null;
        }

        return [
            'name' => strtolower($sectionName),
            'rawName' => $sectionName,
            'subsection' => $subsection,
            'key' => strtolower($valueName),
        ];
    }

    /**
     * @return list<string>
     */
    private static function logicalLines(string $contents): array
    {
        $lines = [];
        $pending = '';
        $line = '';
        $length = strlen($contents);

        for ($index = 0; $index < $length;) {
            $byte = $contents[$index];
            if ($byte !== "\n" && $byte !== "\r") {
                $line .= $byte;
                $index++;
                continue;
            }

            $newline = $byte;
            if ($byte === "\r" && ($contents[$index + 1] ?? null) === "\n") {
                $newline = "\r\n";
                $index += 2;
            } else {
                $index++;
            }

            if (self::lineContinues($line)) {
                if ($newline === "\r") {
                    throw new \RuntimeException('Git config value continuation requires LF or CRLF line endings');
                }
                $pending .= substr($line, 0, -1);
                $line = '';
                continue;
            }

            $lines[] = $pending . $line;
            $pending = '';
            $line = '';
        }

        if ($line !== '') {
            if (self::lineContinues($line)) {
                $pending .= substr($line, 0, -1);
            } else {
                $lines[] = $pending . $line;
                $pending = '';
            }
        }

        if ($pending !== '') {
            $lines[] = $pending;
        }

        return $lines;
    }

    private static function lineContinues(string $line): bool
    {
        $backslashes = 0;
        for ($index = strlen($line) - 1; $index >= 0 && $line[$index] === '\\'; $index--) {
            $backslashes++;
        }

        return $backslashes % 2 === 1;
    }

    private static function parseValue(string $value): string
    {
        $value = trim(self::stripInlineComment($value));
        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            return self::unquote(substr($value, 1, -1));
        }

        return str_replace('\"', '"', $value);
    }

    private static function stripInlineComment(string $value): string
    {
        $inQuote = false;
        $escaped = false;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $byte = $value[$index];
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($byte === '\\') {
                $escaped = true;
                continue;
            }
            if ($byte === '"') {
                $inQuote = !$inQuote;
                continue;
            }
            if (!$inQuote && ($byte === '#' || $byte === ';')) {
                return substr($value, 0, $index);
            }
        }

        return $value;
    }

    private static function unquote(string $value): string
    {
        $out = '';
        $escaped = false;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $byte = $value[$index];
            if (!$escaped && $byte === '\\') {
                $escaped = true;
                continue;
            }

            if ($escaped) {
                $out .= match ($byte) {
                    'n' => "\n",
                    't' => "\t",
                    'b' => "\x08",
                    '"' => '"',
                    '\\' => '\\',
                    default => $byte,
                };
                $escaped = false;
                continue;
            }

            $out .= $byte;
        }

        if ($escaped) {
            $out .= '\\';
        }

        return $out;
    }

    /**
     * @return array{string, ?string}
     */
    private static function parseSectionHeaderParts(string $name, ?string $quotedSubsection): array
    {
        self::validateSectionName($name);

        if ($quotedSubsection !== null) {
            self::validateSubsection($quotedSubsection);
            return [$name, $quotedSubsection];
        }

        $dot = strpos($name, '.');
        if ($dot === false) {
            return [$name, null];
        }

        $section = substr($name, 0, $dot);
        $subsection = substr($name, $dot + 1);
        self::validateSectionName($section);
        self::validateSubsection($subsection);

        return [$section, $subsection];
    }

    private static function unquoteSubsection(string $value): string
    {
        $out = '';
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $byte = $value[$index];
            if ($byte === '\\' && $index + 1 < $length) {
                $index++;
                $out .= $value[$index];
                continue;
            }

            $out .= $byte;
        }

        return $out;
    }

    private static function validateSectionName(string $name): string
    {
        if (preg_match('/^[A-Za-z0-9-]+(?:\.[A-Za-z0-9.-]+)?$/', $name) !== 1) {
            throw new \InvalidArgumentException("Invalid Git config section name: {$name}");
        }

        return $name;
    }

    private static function validateValueName(string $name): string
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9-]*$/', $name) !== 1) {
            throw new \InvalidArgumentException("Invalid Git config value name: {$name}");
        }

        return $name;
    }

    private static function validateSubsection(string $subsection): void
    {
        if (str_contains($subsection, "\n") || str_contains($subsection, "\0")) {
            throw new \InvalidArgumentException('Invalid Git config subsection');
        }
    }

    private static function formatSectionHeader(string $name, ?string $subsection): string
    {
        self::validateSectionName($name);
        if ($subsection === null) {
            return '[' . $name . ']';
        }

        self::validateSubsection($subsection);
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $subsection);

        return '[' . $name . ' "' . $escaped . '"]';
    }

    private static function formatValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        $needsQuotes = trim($value) !== $value
            || str_contains($value, "\n")
            || str_contains($value, "\t")
            || str_contains($value, "\x08")
            || str_contains($value, '"')
            || str_contains($value, '\\')
            || str_starts_with($value, '#')
            || str_starts_with($value, ';')
            || str_contains($value, ' #')
            || str_contains($value, ' ;');

        if (!$needsQuotes) {
            return $value;
        }

        $out = '"';
        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            $out .= match ($value[$index]) {
                "\n" => '\n',
                "\t" => '\t',
                "\x08" => '\b',
                '"' => '\"',
                '\\' => '\\\\',
                default => $value[$index],
            };
        }

        return $out . '"';
    }

    /**
     * @param array{sections:list<array{name:string,rawName:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}>,path:?string} $target
     * @param list<array{name:string,rawName:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}>|null $searchSections
     * @param array{
     *     gitDir: ?string,
     *     branchName: ?string,
     *     homeDir: ?string,
     *     userHomeDirs: array<string,string>,
     *     installPrefix: ?string,
     *     maxDepth: int,
     *     errOnMaxDepthExceeded: bool,
     *     errOnMissingConfigPath: bool,
     *     errOnInterpolationFailure: bool
     * } $options
     */
    private static function resolveIncludes(array &$target, ?array $searchSections, int $depth, array $options): void
    {
        if ($depth >= $options['maxDepth']) {
            if ($options['errOnMaxDepthExceeded']) {
                throw new \RuntimeException("Git config include depth {$options['maxDepth']} exceeded");
            }
            return;
        }

        $original = $target['sections'];
        $currentSearchSections = $searchSections ?? $original;
        $resolved = [];

        foreach ($original as $section) {
            $resolved[] = $section;
            $paths = self::includePathsForSection($section, $currentSearchSections, $options);
            foreach ($paths as $includePath) {
                $resolvedPath = self::resolvePath($includePath, $section['path'], $options);
                if ($resolvedPath === null || !is_file($resolvedPath)) {
                    continue;
                }

                $includeConfig = self::parseFile($resolvedPath);
                self::resolveIncludes($includeConfig, $currentSearchSections, $depth + 1, $options);
                foreach ($includeConfig['sections'] as $includeSection) {
                    $resolved[] = $includeSection;
                }
                if ($searchSections === null) {
                    array_push($currentSearchSections, ...$includeConfig['sections']);
                }
            }
        }

        $target['sections'] = $resolved;
    }

    /**
     * @param array{name:string,rawName:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string} $section
     * @param list<array{name:string,rawName:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}> $searchSections
     * @param array<string, mixed> $options
     * @return list<string>
     */
    private static function includePathsForSection(array $section, array $searchSections, array $options): array
    {
        $rawName = $section['rawName'] ?? $section['name'];

        if ($rawName === 'include' && $section['subsection'] === null) {
            return self::sectionValues($section, 'path');
        }

        if ($rawName !== 'includeIf' || $section['subsection'] === null) {
            return [];
        }

        if (!self::includeConditionMatches($section['subsection'], $section['path'], $searchSections, $options)) {
            return [];
        }

        return self::sectionValues($section, 'path');
    }

    /**
     * @param array{name:string,rawName?:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string} $section
     * @return list<string>
     */
    private static function sectionValues(array $section, string $key): array
    {
        $values = [];
        foreach ($section['entries'] as $entry) {
            if ($entry['key'] === $key) {
                $values[] = $entry['value'];
            }
        }

        return $values;
    }

    /**
     * @param list<array{name:string,rawName?:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}> $searchSections
     * @param array<string, mixed> $options
     */
    private static function includeConditionMatches(string $condition, ?string $targetConfigPath, array $searchSections, array $options): bool
    {
        $colon = strpos($condition, ':');
        if ($colon === false) {
            return false;
        }

        $prefix = substr($condition, 0, $colon);
        $body = substr($condition, $colon + 1);

        return match ($prefix) {
            'gitdir' => self::gitDirMatches($body, $targetConfigPath, $options, false),
            'gitdir/i' => self::gitDirMatches($body, $targetConfigPath, $options, true),
            'onbranch' => self::branchMatches($body, $options),
            'hasconfig' => self::hasConfigMatches($body, $searchSections),
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function gitDirMatches(string $pattern, ?string $targetConfigPath, array $options, bool $ignoreCase): bool
    {
        $gitDir = $options['gitDir'];
        if ($gitDir === null || $gitDir === '') {
            if ($options['errOnInterpolationFailure']) {
                throw new \RuntimeException('The git directory must be provided to support gitdir conditional includes');
            }
            return false;
        }

        $pattern = self::stripOptionalPathPrefix($pattern);
        $pattern = self::interpolatePath($pattern, $options);
        if ($pattern === null) {
            return false;
        }

        if (str_starts_with($pattern, './')) {
            if ($targetConfigPath === null) {
                if ($options['errOnMissingConfigPath']) {
                    throw new \RuntimeException('Relative gitdir conditions require a config file path');
                }
                return false;
            }
            $base = self::normalizePath(dirname($targetConfigPath));
            $pattern = $base . '/' . substr($pattern, 2);
        }

        if (!self::isAbsolutePath($pattern)) {
            $pattern = '**/' . $pattern;
        }
        if (str_ends_with($pattern, '/')) {
            $pattern .= '**';
        }

        $gitDirPaths = array_unique([
            self::normalizePath($gitDir),
            self::realpathLikeGitoxide($gitDir),
        ]);

        foreach (self::gitDirPatternCandidates($pattern) as $candidatePattern) {
            foreach ($gitDirPaths as $gitDirPath) {
                if (self::wildmatch($candidatePattern, $gitDirPath, $ignoreCase)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function branchMatches(string $pattern, array $options): bool
    {
        $branchName = $options['branchName'];
        if (!is_string($branchName) || !str_starts_with($branchName, 'refs/heads/')) {
            return false;
        }

        $shortName = substr($branchName, strlen('refs/heads/'));
        if (str_ends_with($pattern, '/')) {
            $pattern .= '**';
        }

        return self::wildmatch($pattern, $shortName, false);
    }

    /**
     * @param list<array{name:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}> $searchSections
     */
    private static function hasConfigMatches(string $condition, array $searchSections): bool
    {
        $colon = strpos($condition, ':');
        if ($colon === false) {
            return false;
        }

        $keyGlob = substr($condition, 0, $colon);
        $valueGlob = substr($condition, $colon + 1);
        if ($keyGlob !== 'remote.*.url') {
            return false;
        }

        foreach ($searchSections as $section) {
            if ($section['name'] !== 'remote') {
                continue;
            }

            foreach (self::sectionValues($section, 'url') as $url) {
                if (self::wildmatch($valueGlob, $url, false)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function resolvePath(string $path, ?string $targetConfigPath, array $options): ?string
    {
        $path = self::stripOptionalPathPrefix($path);
        $path = self::interpolatePath($path, $options);
        if ($path === null) {
            return null;
        }
        $path = self::normalizePath($path);

        if (!self::isAbsolutePath($path)) {
            if ($targetConfigPath === null) {
                if ($options['errOnMissingConfigPath']) {
                    throw new \RuntimeException('Relative include paths require a config file path');
                }
                return null;
            }
            $path = self::normalizePath(dirname($targetConfigPath)) . '/' . $path;
        }

        return $path;
    }

    private static function stripOptionalPathPrefix(string $path): string
    {
        $prefix = ':(optional)';

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function interpolatePath(string $path, array $options): ?string
    {
        if ($path === '') {
            if ($options['errOnInterpolationFailure']) {
                throw new \RuntimeException('Git config path interpolation requires a non-empty path');
            }
            return null;
        }

        if (str_starts_with($path, '%(prefix)/')) {
            $installPrefix = $options['installPrefix'];
            if (!is_string($installPrefix) || $installPrefix === '') {
                if ($options['errOnInterpolationFailure']) {
                    throw new \RuntimeException('Git install prefix is required for Git config path interpolation');
                }
                return null;
            }

            return rtrim($installPrefix, "\\/") . '/' . substr($path, strlen('%(prefix)/'));
        }

        if (str_starts_with($path, '~/')) {
            $home = $options['homeDir'];
            if (!is_string($home) || $home === '') {
                if ($options['errOnInterpolationFailure']) {
                    throw new \RuntimeException('Home directory is required for Git config path interpolation');
                }
                return null;
            }

            return rtrim($home, "\\/") . substr($path, 1);
        }

        if ($path !== '~' && str_starts_with($path, '~') && str_contains($path, '/')) {
            $slash = strpos($path, '/');
            if ($slash !== false && $slash > 1) {
                $user = substr($path, 1, $slash - 1);
                $homeDirs = $options['userHomeDirs'];
                if (array_key_exists($user, $homeDirs)) {
                    return rtrim($homeDirs[$user], "\\/") . substr($path, $slash);
                }
            }

            if ($options['errOnInterpolationFailure']) {
                throw new \RuntimeException('Named-user home interpolation requires a caller-supplied home directory');
            }
            return null;
        }

        return $path;
    }

    private static function normalizePath(string $path): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? str_replace('\\', '/', $path) : $path;
    }

    private static function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Za-z]:\//', $path) === 1;
    }

    /**
     * @return list<string>
     */
    private static function gitDirPatternCandidates(string $pattern): array
    {
        $patterns = [$pattern => true];
        $realPattern = self::realpathPatternPrefix($pattern);
        if ($realPattern !== null) {
            $patterns[$realPattern] = true;
        }

        return array_keys($patterns);
    }

    private static function realpathPatternPrefix(string $pattern): ?string
    {
        if (str_starts_with($pattern, '//')) {
            return null;
        }

        $metaIndex = self::firstWildmatchMetaIndex($pattern);
        $literalLength = $metaIndex ?? strlen($pattern);
        $literalPrefix = substr($pattern, 0, $literalLength);
        if ($literalPrefix === '' || !self::isAbsolutePath($literalPrefix)) {
            return null;
        }

        if ($metaIndex !== null && !str_ends_with($literalPrefix, '/')) {
            $probe = self::dirnamePath($literalPrefix);
        } else {
            $probe = rtrim($literalPrefix, '/');
            if ($probe === '') {
                $probe = '/';
            }
        }

        if ($probe === '.' || !self::isAbsolutePath($probe)) {
            return null;
        }

        $real = realpath($probe);
        if (!is_string($real)) {
            return null;
        }

        return self::normalizePath($real) . substr($pattern, strlen($probe));
    }

    private static function firstWildmatchMetaIndex(string $pattern): ?int
    {
        $length = strlen($pattern);
        for ($index = 0; $index < $length; $index++) {
            $byte = $pattern[$index];
            if ($byte === '\\') {
                $index++;
                continue;
            }
            if ($byte === '*' || $byte === '?' || $byte === '[') {
                return $index;
            }
        }

        return null;
    }

    private static function dirnamePath(string $path): string
    {
        $path = rtrim($path, '/');
        if ($path === '') {
            return '/';
        }

        $slash = strrpos($path, '/');
        if ($slash === false) {
            return '.';
        }
        if ($slash === 0) {
            return '/';
        }
        if (DIRECTORY_SEPARATOR === '\\' && $slash === 2 && preg_match('/^[A-Za-z]:/', $path) === 1) {
            return substr($path, 0, 3);
        }

        return substr($path, 0, $slash);
    }

    private static function realpathLikeGitoxide(string $path): string
    {
        $real = realpath($path);
        if (is_string($real)) {
            return self::normalizePath($real);
        }

        $path = self::normalizePath($path);
        if (!self::isAbsolutePath($path)) {
            $cwd = getcwd();
            if ($cwd !== false && $cwd !== '') {
                $path = rtrim(self::normalizePath($cwd), '/') . '/' . $path;
            }
        }

        return self::collapsePathComponents($path);
    }

    private static function collapsePathComponents(string $path): string
    {
        $prefix = '';
        $absolute = false;

        if (str_starts_with($path, '/')) {
            $absolute = true;
        } elseif (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Za-z]:\//', $path) === 1) {
            $absolute = true;
            $prefix = substr($path, 0, 2);
            $path = substr($path, 2);
        }

        $components = [];
        foreach (explode('/', $path) as $component) {
            if ($component === '' || $component === '.') {
                continue;
            }

            if ($component === '..') {
                if ($components !== [] && end($components) !== '..') {
                    array_pop($components);
                    continue;
                }
                if ($absolute) {
                    throw new \RuntimeException('Git config gitdir realpath cannot resolve parent above root');
                }
                if (!$absolute) {
                    $components[] = '..';
                }
                continue;
            }

            $components[] = $component;
        }

        $collapsed = implode('/', $components);
        if ($prefix !== '') {
            return $prefix . '/' . $collapsed;
        }
        if ($absolute) {
            return '/' . $collapsed;
        }

        return $collapsed === '' ? '.' : $collapsed;
    }

    private static function wildmatch(string $pattern, string $text, bool $ignoreCase): bool
    {
        if (self::exceedsWildmatchDoubleStarRecursionLimit($pattern)) {
            return false;
        }

        $regex = '';
        $length = strlen($pattern);

        for ($index = 0; $index < $length; $index++) {
            $byte = $pattern[$index];
            if ($byte === '\\') {
                if ($index + 1 >= $length) {
                    return false;
                }
                $index++;
                $regex .= preg_quote($pattern[$index], '~');
                continue;
            }

            if ($byte === '*') {
                $starStart = $index;
                while ($index + 1 < $length && $pattern[$index + 1] === '*') {
                    $index++;
                }

                $starCount = $index - $starStart + 1;
                $nextByte = $pattern[$index + 1] ?? null;
                $nextIsEscapedSlash = $nextByte === '\\' && ($pattern[$index + 2] ?? null) === '/';
                $isPathComponentDoubleStar = $starCount >= 2
                    && ($starStart === 0 || $pattern[$starStart - 1] === '/')
                    && ($nextByte === null || $nextByte === '/' || $nextIsEscapedSlash);

                if ($isPathComponentDoubleStar) {
                    if ($nextByte === '/') {
                        $regex .= '(?:.*/)?';
                        $index++;
                        continue;
                    }
                    if ($nextIsEscapedSlash) {
                        $regex .= '(?:.*/)?';
                        $index += 2;
                        continue;
                    }
                    $regex .= '.*';
                } else {
                    $regex .= '[^/]*';
                }
                continue;
            }

            if ($byte === '?') {
                $regex .= '[^/]';
                continue;
            }

            if ($byte === '[') {
                $end = self::findCharacterClassEnd($pattern, $index);
                if ($end !== null) {
                    $classRegex = self::characterClassRegex(
                        substr($pattern, $index + 1, $end - $index - 1),
                        $ignoreCase,
                    );
                    $regex .= $classRegex === null ? '(?!)' : '(?!/)' . $classRegex;
                    $index = $end;
                    continue;
                }

                $regex .= '(?!)';
                continue;
            }

            $regex .= preg_quote($byte, '~');
        }

        // Gitoxide's wildmatch works on BStr bytes, including malformed UTF-8.
        return preg_match('~\A' . $regex . '\z~' . ($ignoreCase ? 'i' : ''), $text) === 1;
    }

    private static function exceedsWildmatchDoubleStarRecursionLimit(string $pattern): bool
    {
        $depth = 0;
        $length = strlen($pattern);

        for ($index = 0; $index < $length;) {
            if ($pattern[$index] === '\\') {
                $depth = 0;
                $index += 2;
                continue;
            }

            if ($pattern[$index] === '[') {
                $end = self::findCharacterClassEnd($pattern, $index);
                $depth = 0;
                $index = $end === null ? $index + 1 : $end + 1;
                continue;
            }

            $nextIndex = self::pathComponentDoubleStarSlashEnd($pattern, $index);
            if ($nextIndex !== null) {
                $depth++;
                if ($depth >= self::WILDMATCH_RECURSION_LIMIT) {
                    return true;
                }
                $index = $nextIndex;
                continue;
            }

            $depth = 0;
            $index++;
        }

        return false;
    }

    private static function pathComponentDoubleStarSlashEnd(string $pattern, int $index): ?int
    {
        if (($pattern[$index] ?? null) !== '*') {
            return null;
        }

        $length = strlen($pattern);
        $starStart = $index;
        while ($index + 1 < $length && $pattern[$index + 1] === '*') {
            $index++;
        }

        if ($index - $starStart + 1 < 2 || ($starStart > 0 && $pattern[$starStart - 1] !== '/')) {
            return null;
        }

        $nextByte = $pattern[$index + 1] ?? null;
        if ($nextByte === '/') {
            return $index + 2;
        }

        if ($nextByte === '\\' && ($pattern[$index + 2] ?? null) === '/') {
            return $index + 3;
        }

        return null;
    }

    private static function findCharacterClassEnd(string $pattern, int $start): ?int
    {
        $length = strlen($pattern);
        $cursor = $start + 1;
        if ($cursor >= $length) {
            return null;
        }
        if (($pattern[$cursor] ?? '') === '!' || ($pattern[$cursor] ?? '') === '^') {
            $cursor++;
        }
        if (($pattern[$cursor] ?? '') === ']') {
            $cursor++;
        }

        for (; $cursor < $length; $cursor++) {
            $char = $pattern[$cursor];
            if ($char === '\\') {
                $cursor++;
                continue;
            }
            if ($char === '[' && ($pattern[$cursor + 1] ?? '') === ':') {
                $classEnd = strpos($pattern, ':]', $cursor + 2);
                if ($classEnd !== false) {
                    $cursor = $classEnd + 1;
                    continue;
                }
            }
            if ($char === ']') {
                return $cursor;
            }
        }

        return null;
    }

    private static function characterClassRegex(string $class, bool $ignoreCase): ?string
    {
        if ($class === '') {
            return '(?!)';
        }

        $negated = false;
        if ($class[0] === '!' || $class[0] === '^') {
            $negated = true;
            $class = substr($class, 1);
        }

        $matchedBytes = [];
        $previousByte = null;
        $length = strlen($class);
        for ($index = 0; $index < $length;) {
            $byte = $class[$index];
            if (
                $byte === '-'
                && $previousByte !== null
                && $index + 1 < $length
                && $class[$index + 1] !== ']'
            ) {
                $index++;
                [$rangeEnd, $index] = self::readCharacterClassRangeEnd($class, $index);
                if ($rangeEnd === null) {
                    return null;
                }

                $start = $ignoreCase ? self::asciiLowerByte($previousByte) : $previousByte;
                $end = $ignoreCase ? self::asciiLowerByte($rangeEnd) : $rangeEnd;
                if ($start <= $end) {
                    for ($rangeByte = $start; $rangeByte <= $end; $rangeByte++) {
                        $matchedBytes[$rangeByte] = true;
                    }
                } elseif ($ignoreCase) {
                    for ($rangeByte = $end; $rangeByte <= $start; $rangeByte++) {
                        $matchedBytes[$rangeByte] = true;
                    }
                }
                $previousByte = null;
                continue;
            }

            if ($byte === '\\') {
                $index++;
                $literal = $index < $length ? ord($class[$index]) : ord('\\');
                $matchedBytes[$literal] = true;
                $previousByte = $literal;
                $index++;
                continue;
            }

            if ($byte === '[' && ($class[$index + 1] ?? '') === ':') {
                $end = strpos($class, ':]', $index + 2);
                if ($end !== false) {
                    $name = substr($class, $index + 2, $end - $index - 2);
                    $mapped = self::posixCharacterClassBytes($ignoreCase ? strtolower($name) : $name);
                    if ($mapped === null) {
                        return null;
                    }
                    foreach ($mapped as $mappedByte) {
                        $matchedBytes[$mappedByte] = true;
                    }
                    $previousByte = null;
                    $index = $end + 2;
                    continue;
                }
            }

            $literal = ord($byte);
            $matchedBytes[$literal] = true;
            $previousByte = $literal;
            $index++;
        }

        if ($matchedBytes === []) {
            return '(?!)';
        }

        return '[' . ($negated ? '^' : '') . self::bytesToCharacterClassBody($matchedBytes) . ']';
    }

    /**
     * @return array{?int, int}
     */
    private static function readCharacterClassRangeEnd(string $class, int $index): array
    {
        if ($class[$index] === '\\') {
            $index++;
            if ($index >= strlen($class)) {
                return [null, $index];
            }

            return [ord($class[$index]), $index + 1];
        }

        return [ord($class[$index]), $index + 1];
    }

    private static function asciiLowerByte(int $byte): int
    {
        return $byte >= 65 && $byte <= 90 ? $byte + 32 : $byte;
    }

    /**
     * @param array<int, true> $bytes
     */
    private static function bytesToCharacterClassBody(array $bytes): string
    {
        ksort($bytes, SORT_NUMERIC);

        $body = '';
        foreach (array_keys($bytes) as $byte) {
            $body .= sprintf('\\x%02X', $byte);
        }

        return $body;
    }

    /**
     * @return list<int>|null
     */
    private static function posixCharacterClassBytes(string $class): ?array
    {
        $ranges = match ($class) {
            'alnum' => [[48, 57], [65, 90], [97, 122]],
            'alpha' => [[65, 90], [97, 122]],
            // gix-glob uses Rust's ASCII whitespace helper for [:blank:],
            // which includes HT, LF, FF, CR, and space, but not VT.
            'blank' => [[9, 10], [12, 13], [32, 32]],
            'cntrl' => [[0, 31], [127, 127]],
            'digit' => [[48, 57]],
            'graph' => [[33, 126]],
            'lower' => [[97, 122]],
            'print' => [[32, 126]],
            'punct' => [[33, 47], [58, 64], [91, 96], [123, 126]],
            'space' => [[32, 32]],
            'upper' => [[65, 90]],
            'xdigit' => [[48, 57], [65, 70], [97, 102]],
            default => null,
        };

        if ($ranges === null) {
            return null;
        }

        $bytes = [];
        foreach ($ranges as [$start, $end]) {
            for ($byte = $start; $byte <= $end; $byte++) {
                $bytes[] = $byte;
            }
        }

        return $bytes;
    }
}

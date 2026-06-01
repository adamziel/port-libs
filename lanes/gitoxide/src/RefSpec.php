<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class RefSpec
{
    public const OP_FETCH = 'fetch';
    public const OP_PUSH = 'push';

    public const MODE_NORMAL = 'normal';
    public const MODE_FORCE = 'force';
    public const MODE_NEGATIVE = 'negative';

    public const INSTRUCTION_FETCH_ONLY = 'fetch-only';
    public const INSTRUCTION_FETCH_AND_UPDATE = 'fetch-and-update';
    public const INSTRUCTION_FETCH_EXCLUDE = 'fetch-exclude';
    public const INSTRUCTION_PUSH_MATCHING = 'push-matching';
    public const INSTRUCTION_PUSH_DELETE = 'push-delete';
    public const INSTRUCTION_PUSH_ALL_MATCHING_BRANCHES = 'push-all-matching-branches';
    public const INSTRUCTION_PUSH_EXCLUDE = 'push-exclude';

    private function __construct(
        private readonly string $operation,
        private readonly string $mode,
        private readonly ?string $source,
        private readonly ?string $destination,
    ) {
    }

    public static function parseFetch(string $spec): self
    {
        return self::parse($spec, self::OP_FETCH);
    }

    public static function parsePush(string $spec): self
    {
        return self::parse($spec, self::OP_PUSH);
    }

    public static function parse(string $spec, string $operation): self
    {
        if ($operation !== self::OP_FETCH && $operation !== self::OP_PUSH) {
            throw new \InvalidArgumentException('Refspec operation must be fetch or push');
        }

        if ($spec === '') {
            if ($operation === self::OP_FETCH) {
                return new self($operation, self::MODE_NORMAL, 'HEAD', null);
            }
            throw new \InvalidArgumentException('Empty push refspecs are invalid');
        }

        $mode = self::MODE_NORMAL;
        $first = $spec[0];
        if ($first === '^') {
            $mode = self::MODE_NEGATIVE;
            $spec = substr($spec, 1);
        } elseif ($first === '+') {
            $mode = self::MODE_FORCE;
            $spec = substr($spec, 1);
        }

        if ($operation === self::OP_FETCH && $mode !== self::MODE_NEGATIVE && $spec === '') {
            return new self($operation, $mode, 'HEAD', null);
        }

        [$source, $destination] = self::splitSides($spec, $operation, $mode);
        if ($source === '@') {
            $source = 'HEAD';
        }

        $oneSided = $destination === null;
        [$source, $sourceHadPattern] = self::validatedSide(
            $source,
            $operation === self::OP_PUSH && $destination !== null,
            $oneSided
        );
        [$destination, $destinationHadPattern] = self::validatedSide($destination, false, false);

        if (!$oneSided && $mode !== self::MODE_NEGATIVE && $sourceHadPattern !== $destinationHadPattern) {
            throw new \InvalidArgumentException('Refspec patterns must be present on both sides');
        }

        if ($mode === self::MODE_NEGATIVE) {
            self::assertNegative($source, $sourceHadPattern);
        }

        return new self($operation, $mode, $source, $destination);
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function source(): ?string
    {
        return $this->source;
    }

    public function destination(): ?string
    {
        return $this->destination;
    }

    public function remote(): ?string
    {
        return $this->operation === self::OP_PUSH ? $this->destination : $this->source;
    }

    public function local(): ?string
    {
        return $this->operation === self::OP_PUSH ? $this->source : $this->destination;
    }

    public function allowNonFastForward(): bool
    {
        return $this->mode === self::MODE_FORCE;
    }

    public function instructionName(): string
    {
        if ($this->operation === self::OP_FETCH) {
            if ($this->mode === self::MODE_NEGATIVE) {
                return self::INSTRUCTION_FETCH_EXCLUDE;
            }

            return $this->destination === null ? self::INSTRUCTION_FETCH_ONLY : self::INSTRUCTION_FETCH_AND_UPDATE;
        }

        if ($this->mode === self::MODE_NEGATIVE) {
            return self::INSTRUCTION_PUSH_EXCLUDE;
        }
        if ($this->source === null && $this->destination === null) {
            return self::INSTRUCTION_PUSH_ALL_MATCHING_BRANCHES;
        }
        if ($this->source === null) {
            return self::INSTRUCTION_PUSH_DELETE;
        }

        return self::INSTRUCTION_PUSH_MATCHING;
    }

    /**
     * @return array{operation: string, instruction: string, mode: string, source: ?string, destination: ?string, remote: ?string, local: ?string, allowNonFastForward: bool, prefix: ?string, expandedPrefixes: list<string>, normalized: string}
     */
    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'instruction' => $this->instructionName(),
            'mode' => $this->mode,
            'source' => $this->source,
            'destination' => $this->destination,
            'remote' => $this->remote(),
            'local' => $this->local(),
            'allowNonFastForward' => $this->allowNonFastForward(),
            'prefix' => $this->prefix(),
            'expandedPrefixes' => $this->expandPrefixes(),
            'normalized' => $this->toString(),
        ];
    }

    /**
     * Return the upstream instruction identity used for equality, ordering, and
     * hash keys. This differs from the parse shape for source-only push specs
     * because their destination is implicit.
     *
     * @return array{operation: string, instruction: string, source: ?string, destination: ?string, allowNonFastForward?: bool}
     */
    public function instructionIdentity(): array
    {
        if ($this->operation === self::OP_FETCH) {
            if ($this->mode === self::MODE_NEGATIVE) {
                return [
                    'operation' => self::OP_FETCH,
                    'instruction' => self::INSTRUCTION_FETCH_EXCLUDE,
                    'source' => $this->source,
                    'destination' => null,
                ];
            }

            if ($this->destination === null) {
                return [
                    'operation' => self::OP_FETCH,
                    'instruction' => self::INSTRUCTION_FETCH_ONLY,
                    'source' => $this->source,
                    'destination' => null,
                ];
            }

            return [
                'operation' => self::OP_FETCH,
                'instruction' => self::INSTRUCTION_FETCH_AND_UPDATE,
                'source' => $this->source,
                'destination' => $this->destination,
                'allowNonFastForward' => $this->mode === self::MODE_FORCE,
            ];
        }

        if ($this->mode === self::MODE_NEGATIVE) {
            return [
                'operation' => self::OP_PUSH,
                'instruction' => self::INSTRUCTION_PUSH_EXCLUDE,
                'source' => $this->source,
                'destination' => null,
            ];
        }
        if ($this->source === null && $this->destination === null) {
            return [
                'operation' => self::OP_PUSH,
                'instruction' => self::INSTRUCTION_PUSH_ALL_MATCHING_BRANCHES,
                'source' => null,
                'destination' => null,
                'allowNonFastForward' => $this->mode === self::MODE_FORCE,
            ];
        }
        if ($this->source === null) {
            return [
                'operation' => self::OP_PUSH,
                'instruction' => self::INSTRUCTION_PUSH_DELETE,
                'source' => null,
                'destination' => $this->destination,
            ];
        }

        return [
            'operation' => self::OP_PUSH,
            'instruction' => self::INSTRUCTION_PUSH_MATCHING,
            'source' => $this->source,
            'destination' => $this->destination ?? $this->source,
            'allowNonFastForward' => $this->mode === self::MODE_FORCE,
        ];
    }

    public function instructionKey(): string
    {
        return json_encode(
            $this->instructionIdentity(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    public function equivalentTo(self $other): bool
    {
        return $this->instructionIdentity() === $other->instructionIdentity();
    }

    public function prefix(): ?string
    {
        if ($this->mode === self::MODE_NEGATIVE) {
            return null;
        }

        $source = $this->operation === self::OP_FETCH ? $this->source : $this->destination;
        if ($source === null) {
            return null;
        }
        if ($source === 'HEAD') {
            return 'HEAD';
        }
        if (!str_starts_with($source, 'refs/')) {
            return null;
        }

        $sansRefs = substr($source, 5);
        $star = strpos($sansRefs, '*');
        if ($star !== false) {
            if (
                $star === 0
                || str_contains(substr($sansRefs, $star + 1), '*')
                || preg_match('/[?\[\]\\\\]/', $sansRefs) === 1
            ) {
                return null;
            }

            $prefix = substr($source, 0, 5 + $star);
            return $prefix === '' ? null : $prefix;
        }

        return $source;
    }

    /**
     * @return list<string>
     */
    public function expandPrefixes(): array
    {
        $prefix = $this->prefix();
        if ($prefix !== null) {
            return [$prefix];
        }
        if ($this->mode === self::MODE_NEGATIVE) {
            return [];
        }

        $source = $this->operation === self::OP_FETCH ? $this->source : $this->destination;
        if ($source === null || self::looksLikeFullObjectHash($source)) {
            return [];
        }
        if (str_starts_with($source, 'refs/')) {
            return str_contains(substr($source, 5), '/') ? [] : [$source];
        }

        return [
            $source,
            'refs/' . $source,
            'refs/tags/' . $source,
            'refs/heads/' . $source,
            'refs/remotes/' . $source,
            'refs/remotes/' . $source . '/HEAD',
        ];
    }

    /**
     * Match fetch refspec left-hand sides against advertised remote refs.
     *
     * This mirrors the bounded behavior of gix-refspec MatchGroup::match_lhs:
     * positive fetch specs produce deduplicated remote-to-local mappings, object
     * IDs are mapped without requiring an advertised ref, and negative fetch
     * specs remove matching ref-name mappings after all positives are collected.
     *
     * @param list<string|self> $fetchRefspecs
     * @param list<string|array{name: string, target?: ?string, object?: ?string}> $remoteRefs
     * @return list<array{remote: string, local: ?string, specIndex: int, itemIndex: ?int}>
     */
    public static function matchFetchRemoteRefs(array $fetchRefspecs, array $remoteRefs): array
    {
        $specs = [];
        foreach ($fetchRefspecs as $index => $candidate) {
            $specs[] = self::fetchSpecForMatch($candidate, $index);
        }

        $items = [];
        foreach ($remoteRefs as $index => $item) {
            $items[] = self::remoteRefItemForMatch($item, $index);
        }

        $out = [];
        $seen = [];
        foreach ($specs as $specIndex => $spec) {
            if ($spec->mode === self::MODE_NEGATIVE) {
                continue;
            }

            $source = $spec->source;
            if ($source === null) {
                continue;
            }

            if (self::looksLikeFullObjectHash($source)) {
                self::pushUniqueFetchMapping(
                    $out,
                    $seen,
                    strtolower($source),
                    self::fetchDestinationToLocal($spec->destination, null),
                    $specIndex,
                    null
                );
                continue;
            }

            $oneSided = $spec->destination === null;
            foreach ($items as $itemIndex => $item) {
                $match = self::matchFetchSource($source, $item['name'], $oneSided);
                if ($match === null) {
                    continue;
                }

                self::pushUniqueFetchMapping(
                    $out,
                    $seen,
                    $item['name'],
                    self::fetchDestinationToLocal($spec->destination, $match['capture']),
                    $specIndex,
                    $itemIndex
                );
            }
        }

        foreach ($specs as $spec) {
            if ($spec->mode !== self::MODE_NEGATIVE || $spec->source === null) {
                continue;
            }

            $source = $spec->source;
            $out = array_values(array_filter(
                $out,
                static fn (array $mapping): bool => self::matchFetchSource($source, $mapping['remote'], true) === null
            ));
        }

        return $out;
    }

    /**
     * Validate fetch match output like `gix_refspec::match_lhs().validated()`.
     *
     * Conflicting full local destinations are reported as terminal issues.
     * Partial glob destinations are removed and returned as non-terminal fixes.
     *
     * @param list<string|self> $fetchRefspecs
     * @param list<string|array{name: string, target?: ?string, object?: ?string}> $remoteRefs
     * @return array{ok: bool, mappings: list<array{remote: string, local: ?string, specIndex: int, itemIndex: ?int}>, fixes: list<array{type: string, name: string, spec: string, specIndex: int}>, issues: list<array{type: string, destination: string, sources: list<string>, specs: list<string>, specIndexes: list<int>}>}
     */
    public static function validatedFetchRemoteRefs(array $fetchRefspecs, array $remoteRefs): array
    {
        $specs = [];
        foreach ($fetchRefspecs as $index => $candidate) {
            $specs[] = self::fetchSpecForMatch($candidate, $index);
        }

        $mappings = self::matchFetchRemoteRefs($specs, $remoteRefs);
        $issues = self::validatedFetchConflictIssues($mappings, $specs);
        if ($issues !== []) {
            return [
                'ok' => false,
                'mappings' => $mappings,
                'fixes' => [],
                'issues' => $issues,
            ];
        }

        $validated = [];
        $fixes = [];
        foreach ($mappings as $mapping) {
            $local = $mapping['local'];
            if ($local !== null && $local !== 'HEAD' && !str_starts_with($local, 'refs/')) {
                $fixes[] = [
                    'type' => 'partial-destination-removed',
                    'name' => $local,
                    'spec' => $specs[$mapping['specIndex']]->toString(),
                    'specIndex' => $mapping['specIndex'],
                ];
                continue;
            }

            $validated[] = $mapping;
        }

        return [
            'ok' => true,
            'mappings' => $validated,
            'fixes' => $fixes,
            'issues' => [],
        ];
    }

    public function toString(): string
    {
        if ($this->operation === self::OP_FETCH && $this->destination === null && $this->mode !== self::MODE_NEGATIVE && $this->source !== null) {
            return $this->source;
        }

        if ($this->operation === self::OP_PUSH && $this->source === null && $this->destination !== null && $this->mode !== self::MODE_NEGATIVE) {
            return ':' . $this->destination;
        }
        if ($this->operation === self::OP_PUSH && $this->source !== null && $this->destination === null && $this->mode !== self::MODE_NEGATIVE) {
            $prefix = $this->mode === self::MODE_FORCE ? '+' : '';
            return $prefix . $this->source . ':' . $this->source;
        }

        $prefix = match ($this->mode) {
            self::MODE_FORCE => '+',
            self::MODE_NEGATIVE => '^',
            default => '',
        };

        if ($this->destination !== null) {
            return $prefix . ($this->source ?? '') . ':' . $this->destination;
        }
        if ($this->source === null) {
            return $prefix . ':';
        }

        return $prefix . $this->source;
    }

    private static function fetchSpecForMatch(mixed $candidate, int $index): self
    {
        if ($candidate instanceof self) {
            $spec = $candidate;
        } elseif (is_string($candidate)) {
            $spec = self::parseFetch($candidate);
        } else {
            throw new \InvalidArgumentException("Fetch refspec at index {$index} must be a string or RefSpec");
        }

        if ($spec->operation !== self::OP_FETCH) {
            throw new \InvalidArgumentException("Refspec at index {$index} is not a fetch refspec");
        }

        return $spec;
    }

    /**
     * @return array{name: string}
     */
    private static function remoteRefItemForMatch(mixed $item, int $index): array
    {
        if (is_string($item) && $item !== '') {
            return ['name' => $item];
        }

        if (!is_array($item) || !isset($item['name']) || !is_string($item['name']) || $item['name'] === '') {
            throw new \InvalidArgumentException("Remote ref item at index {$index} must provide a non-empty name");
        }

        return ['name' => $item['name']];
    }

    /**
     * @return array{capture: ?string}|null
     */
    private static function matchFetchSource(string $source, string $remoteRef, bool $oneSided): ?array
    {
        if ($oneSided && self::mustUsePatternMatching($source)) {
            return self::refPatternMatches($source, $remoteRef) ? ['capture' => null] : null;
        }

        if (str_contains($source, '*')) {
            $capture = self::simpleGlobCapture($source, $remoteRef);
            return $capture === null ? null : ['capture' => $capture];
        }

        if (str_starts_with($source, 'refs/')) {
            return $source === $remoteRef ? ['capture' => null] : null;
        }

        if (self::looksLikeFullObjectHash($source)) {
            return null;
        }

        foreach (self::expandPartialRefName($source) as $expanded) {
            if ($expanded === $remoteRef) {
                return ['capture' => null];
            }
        }

        return null;
    }

    private static function simpleGlobCapture(string $pattern, string $value): ?string
    {
        $star = strpos($pattern, '*');
        if ($star === false) {
            return $pattern === $value ? '' : null;
        }

        $prefix = substr($pattern, 0, $star);
        $suffix = substr($pattern, $star + 1);
        if (!str_starts_with($value, $prefix) || ($suffix !== '' && !str_ends_with($value, $suffix))) {
            return null;
        }

        $end = strlen($value) - strlen($suffix);
        if ($end < strlen($prefix)) {
            return null;
        }

        return substr($value, strlen($prefix), $end - strlen($prefix));
    }

    private static function fetchDestinationToLocal(?string $destination, ?string $capture): ?string
    {
        if ($destination === null) {
            return null;
        }

        if (str_contains($destination, '*')) {
            if ($capture === null) {
                throw new \InvalidArgumentException('Glob fetch destination requires a source glob capture');
            }

            $star = strpos($destination, '*');
            return substr($destination, 0, $star) . $capture . substr($destination, $star + 1);
        }

        if (str_starts_with($destination, 'refs/')) {
            return $destination;
        }

        if (self::looksLikeFullObjectHash($destination)) {
            return 'refs/heads/' . strtolower($destination);
        }

        $base = 'refs/';
        if (!str_starts_with($destination, 'tags/') && !str_starts_with($destination, 'remotes/')) {
            $base .= 'heads/';
        }

        return $base . $destination;
    }

    /**
     * @param list<array{remote: string, local: ?string, specIndex: int, itemIndex: ?int}> $out
     * @param array<string, true> $seen
     */
    private static function pushUniqueFetchMapping(
        array &$out,
        array &$seen,
        string $remote,
        ?string $local,
        int $specIndex,
        ?int $itemIndex
    ): void {
        $key = $remote . "\0" . ($local ?? "\0");
        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $out[] = [
            'remote' => $remote,
            'local' => $local,
            'specIndex' => $specIndex,
            'itemIndex' => $itemIndex,
        ];
    }

    /**
     * @return list<string>
     */
    private static function expandPartialRefName(string $name): array
    {
        return [
            $name,
            'refs/' . $name,
            'refs/tags/' . $name,
            'refs/heads/' . $name,
            'refs/remotes/' . $name,
            'refs/remotes/' . $name . '/HEAD',
        ];
    }

    /**
     * @param list<array{remote: string, local: ?string, specIndex: int, itemIndex: ?int}> $mappings
     * @param list<self> $specs
     * @return list<array{type: string, destination: string, sources: list<string>, specs: list<string>, specIndexes: list<int>}>
     */
    private static function validatedFetchConflictIssues(array $mappings, array $specs): array
    {
        $byDestination = [];
        foreach ($mappings as $mapping) {
            $local = $mapping['local'];
            if ($local === null) {
                continue;
            }

            $source = $mapping['remote'];
            if (!isset($byDestination[$local])) {
                $byDestination[$local] = [
                    'sources' => [],
                    'specs' => [],
                    'specIndexes' => [],
                    'seenSources' => [],
                ];
            }
            if (isset($byDestination[$local]['seenSources'][$source])) {
                continue;
            }

            $byDestination[$local]['seenSources'][$source] = true;
            $byDestination[$local]['sources'][] = $source;
            $byDestination[$local]['specs'][] = $specs[$mapping['specIndex']]->toString();
            $byDestination[$local]['specIndexes'][] = $mapping['specIndex'];
        }

        ksort($byDestination);
        $issues = [];
        foreach ($byDestination as $destination => $entry) {
            if (count($entry['sources']) < 2) {
                continue;
            }

            $issues[] = [
                'type' => 'conflicting-destination',
                'destination' => $destination,
                'sources' => $entry['sources'],
                'specs' => $entry['specs'],
                'specIndexes' => $entry['specIndexes'],
            ];
        }

        return $issues;
    }

    private static function mustUsePatternMatching(string $pattern): bool
    {
        return substr_count($pattern, '*') > 1 || preg_match('/[?\[\]\\\\]/', $pattern) === 1;
    }

    private static function refPatternMatches(string $pattern, string $value): bool
    {
        $regex = self::refPatternRegex($pattern);
        return @preg_match($regex, $value) === 1;
    }

    private static function refPatternRegex(string $pattern): string
    {
        $regex = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length;) {
            $char = $pattern[$i];
            if ($char === '*') {
                $runStart = $i;
                while ($i < $length && $pattern[$i] === '*') {
                    ++$i;
                }
                $runLength = $i - $runStart;
                if ($runLength > 1) {
                    $atComponentStart = $runStart === 0 || $pattern[$runStart - 1] === '/';
                    $nextIsEnd = $i >= $length;
                    $nextIsSlash = !$nextIsEnd && $pattern[$i] === '/';
                    $nextIsEscapedSlash = !$nextIsEnd && $pattern[$i] === '\\' && $i + 1 < $length && $pattern[$i + 1] === '/';
                    if ($atComponentStart && ($nextIsEnd || $nextIsSlash || $nextIsEscapedSlash)) {
                        if ($nextIsEnd) {
                            $regex .= '.*';
                        } else {
                            $regex .= '(?:.*/)?';
                            $i += $nextIsSlash ? 1 : 2;
                        }
                        continue;
                    }
                }
                $regex .= '[^/]*';
                continue;
            }
            if ($char === '?') {
                $regex .= '[^/]';
                ++$i;
                continue;
            }
            if ($char === '\\' && $i + 1 < $length) {
                $regex .= preg_quote($pattern[$i + 1], '~');
                $i += 2;
                continue;
            }
            if ($char === '[') {
                $end = strpos($pattern, ']', $i + 1);
                if ($end !== false && $end > $i + 1) {
                    $class = substr($pattern, $i + 1, $end - $i - 1);
                    if ($class !== '') {
                        if ($class[0] === '!') {
                            $class = '^' . substr($class, 1);
                        } elseif ($class[0] === '^') {
                            $class = '\\^' . substr($class, 1);
                        }
                        $class = str_replace(['\\', '~'], ['\\\\', '\\~'], $class);
                        $regex .= '(?!/)[' . $class . ']';
                        $i = $end + 1;
                        continue;
                    }
                }
            }

            $regex .= preg_quote($char, '~');
            ++$i;
        }

        return '~\A' . $regex . '\z~s';
    }

    private static function splitSides(string $spec, string $operation, string $mode): array
    {
        $colon = strpos($spec, ':');
        if ($colon === false) {
            return [$spec === '' ? null : $spec, null];
        }
        if ($mode === self::MODE_NEGATIVE) {
            throw new \InvalidArgumentException('Negative refspecs cannot have destinations');
        }

        $left = substr($spec, 0, $colon);
        $right = substr($spec, $colon + 1);
        $source = $left === '' ? null : $left;
        $destination = $right === '' ? null : $right;

        return match ([$source !== null, $destination !== null, $operation]) {
            [false, false, self::OP_PUSH] => [null, null],
            [false, false, self::OP_FETCH] => ['HEAD', null],
            [false, true, self::OP_PUSH] => [null, $destination],
            [false, true, self::OP_FETCH] => ['HEAD', $destination],
            [true, false, self::OP_FETCH] => [$source, null],
            [true, false, self::OP_PUSH] => throw new \InvalidArgumentException('Cannot push into an empty destination'),
            default => [$source, $destination],
        };
    }

    /**
     * @return array{0: ?string, 1: bool}
     */
    private static function validatedSide(?string $side, bool $allowRevspecs, bool $oneSided): array
    {
        if ($side === null) {
            return [null, false];
        }

        $globCount = substr_count($side, '*');
        if ($globCount > 1 && !$oneSided) {
            throw new \InvalidArgumentException('Refspec glob patterns may only contain one asterisk');
        }
        $hasGlob = $globCount > 0;
        if ($hasGlob) {
            if (!$oneSided) {
                $candidate = preg_replace('/\*/', 'a', $side, 1);
                ReferenceName::assertValidPartial((string) $candidate);
            }
            return [$side, true];
        }

        try {
            ReferenceName::assertValidPartial($side);
        } catch (\InvalidArgumentException $error) {
            if (!$allowRevspecs || !self::looksLikeRevspec($side)) {
                throw $error;
            }
        }

        return [$side, false];
    }

    private static function assertNegative(?string $source, bool $sourceHadPattern): void
    {
        if ($source === null) {
            throw new \InvalidArgumentException('Negative refspecs must not be empty');
        }
        if ($sourceHadPattern) {
            throw new \InvalidArgumentException('Negative glob patterns are not allowed');
        }
        if (self::looksLikeObjectHashPrefix($source)) {
            throw new \InvalidArgumentException('Negative refspecs must not be object hashes');
        }
        if (!str_starts_with($source, 'refs/') && $source !== 'HEAD') {
            throw new \InvalidArgumentException('Negative refspecs must be full ref names or HEAD');
        }
    }

    private static function looksLikeObjectHashPrefix(string $value): bool
    {
        return strlen($value) >= 4 && preg_match('/^[0-9a-fA-F]+$/', $value) === 1;
    }

    private static function looksLikeFullObjectHash(string $value): bool
    {
        return (strlen($value) === 40 || strlen($value) === 64) && preg_match('/^[0-9a-fA-F]+$/', $value) === 1;
    }

    private static function looksLikeRevspec(string $value): bool
    {
        return $value !== '' && preg_match('/[\^~]|@\{|\.\./', $value) === 1 && preg_match('/[\x00-\x20\x7f:]/', $value) !== 1;
    }
}

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

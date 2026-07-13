<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PackedReferences
{
    public const PEELED_UNSPECIFIED = 'unspecified';
    public const PEELED_PARTIAL = 'partial';
    public const PEELED_FULLY = 'fully';

    /**
     * @param list<PackedReference> $references
     * @param list<string> $headerTraits
     */
    private function __construct(
        private readonly array $references,
        private readonly bool $hasHeader,
        private readonly bool $headerSorted,
        private readonly string $headerPeeledState,
        private readonly array $headerTraits,
    ) {
    }

    public static function fromBytes(string $contents, string $algorithm = 'sha1'): self
    {
        $offset = 0;
        $hasHeader = false;
        $headerSorted = false;
        $headerPeeledState = self::PEELED_UNSPECIFIED;
        $headerTraits = [];

        if ($contents !== '' && $contents[0] === '#') {
            $hasHeader = true;
            $line = self::readLine($contents, $offset);
            $prefix = '# pack-refs with: ';
            if (!str_starts_with($line, $prefix)) {
                throw new \InvalidArgumentException('Packed refs header could not be parsed');
            }

            $traits = substr($line, strlen($prefix));
            foreach (explode(' ', $traits) as $trait) {
                if ($trait === '') {
                    continue;
                }
                $headerTraits[] = $trait;
                if ($trait === 'fully-peeled') {
                    $headerPeeledState = self::PEELED_FULLY;
                } elseif ($trait === 'peeled') {
                    $headerPeeledState = self::PEELED_PARTIAL;
                }
                if ($trait === 'sorted') {
                    $headerSorted = true;
                }
            }
        }

        $references = [];
        while ($offset < strlen($contents)) {
            $references[] = self::readReference($contents, $offset, $algorithm);
        }

        if (!$headerSorted) {
            usort(
                $references,
                static fn (PackedReference $a, PackedReference $b): int => strcmp($a->name, $b->name)
            );
        }

        return new self($references, $hasHeader, $headerSorted, $headerPeeledState, $headerTraits);
    }

    /**
     * @return list<array{line:int,reference:?PackedReference,error:?\InvalidArgumentException}>
     */
    public static function iterResults(string $contents, string $algorithm = 'sha1'): array
    {
        $offset = 0;
        $lineNumber = 1;
        $results = [];
        $pending = null;
        $pendingLine = 0;

        if ($contents !== '' && $contents[0] === '#') {
            self::readIterationLine($contents, $offset);
            $lineNumber++;
        }

        while ($offset < strlen($contents)) {
            $line = self::readIterationLine($contents, $offset);

            if (str_starts_with($line, '^')) {
                if ($pending !== null && self::isObjectIdLine(substr($line, 1), $algorithm)) {
                    $pending = new PackedReference(
                        $pending->name,
                        $pending->target,
                        strtolower(substr($line, 1)),
                    );
                } else {
                    if ($pending !== null) {
                        $results[] = ['line' => $pendingLine, 'reference' => $pending, 'error' => null];
                        $pending = null;
                    }
                    $results[] = [
                        'line' => $lineNumber,
                        'reference' => null,
                        'error' => new \InvalidArgumentException(self::invalidLineMessage($lineNumber, $line)),
                    ];
                }
                $lineNumber++;
                continue;
            }

            if ($pending !== null) {
                $results[] = ['line' => $pendingLine, 'reference' => $pending, 'error' => null];
                $pending = null;
            }

            try {
                $pending = self::referenceFromLine($line, $lineNumber, $algorithm);
                $pendingLine = $lineNumber;
            } catch (\InvalidArgumentException $exception) {
                $results[] = ['line' => $lineNumber, 'reference' => null, 'error' => $exception];
            }

            $lineNumber++;
        }

        if ($pending !== null) {
            $results[] = ['line' => $pendingLine, 'reference' => $pending, 'error' => null];
        }

        return $results;
    }

    public static function open(string $path, string $algorithm = 'sha1'): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Packed refs file not found: {$path}");
        }

        return self::fromBytes((string) file_get_contents($path), $algorithm);
    }

    public function hasHeader(): bool
    {
        return $this->hasHeader;
    }

    public function headerSorted(): bool
    {
        return $this->headerSorted;
    }

    public function headerPeeledState(): string
    {
        return $this->headerPeeledState;
    }

    /**
     * @return list<string>
     */
    public function headerTraits(): array
    {
        return $this->headerTraits;
    }

    /**
     * @return list<PackedReference>
     */
    public function all(): array
    {
        return $this->references;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (PackedReference $reference): string => $reference->name, $this->references);
    }

    /**
     * @return list<PackedReference>
     */
    public function prefixed(string $prefix): array
    {
        ReferenceName::assertValidPartial(rtrim($prefix, '/'));

        $references = [];
        foreach ($this->references as $reference) {
            if (str_starts_with($reference->name, $prefix)) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    public function tryFind(string $name): ?PackedReference
    {
        $byName = [];
        foreach ($this->references as $reference) {
            $byName[$reference->name] = $reference;
        }

        foreach (self::lookupCandidates($name) as $candidate) {
            if (isset($byName[$candidate])) {
                return $byName[$candidate];
            }
        }

        return null;
    }

    public function find(string $name): PackedReference
    {
        $reference = $this->tryFind($name);
        if ($reference === null) {
            throw new \RuntimeException("Packed reference not found: {$name}");
        }

        return $reference;
    }

    private static function readReference(string $contents, int &$offset, string $algorithm): PackedReference
    {
        if (substr($contents, $offset, 1) === '^') {
            throw new \InvalidArgumentException('Peeled packed-ref line cannot appear without a preceding reference');
        }

        $hashLength = ReferenceTarget::hashHexLength($algorithm);
        $target = substr($contents, $offset, $hashLength);
        if (strlen($target) !== $hashLength || preg_match('/^[0-9a-fA-F]+$/', $target) !== 1) {
            throw new \InvalidArgumentException('Packed reference object id could not be parsed');
        }
        $offset += $hashLength;

        if (substr($contents, $offset, 1) !== ' ') {
            throw new \InvalidArgumentException('Packed reference line must separate target and name with a space');
        }
        $offset++;

        $name = self::readLine($contents, $offset);
        $reference = new PackedReference($name, ReferenceTarget::object($target, $algorithm));

        if (substr($contents, $offset, 1) !== '^') {
            return $reference;
        }

        $offset++;
        $peeled = self::readLine($contents, $offset);
        ReferenceTarget::assertValidObjectId($peeled, $algorithm);

        return new PackedReference($name, ReferenceTarget::object($target, $algorithm), strtolower($peeled));
    }

    private static function referenceFromLine(string $line, int $lineNumber, string $algorithm): PackedReference
    {
        $hashLength = ReferenceTarget::hashHexLength($algorithm);
        $target = substr($line, 0, $hashLength);

        try {
            if (!self::isObjectIdLine($target, $algorithm) || substr($line, $hashLength, 1) !== ' ') {
                throw new \InvalidArgumentException('invalid packed reference line');
            }

            return new PackedReference(
                substr($line, $hashLength + 1),
                ReferenceTarget::object($target, $algorithm),
            );
        } catch (\Throwable) {
            throw new \InvalidArgumentException(self::invalidLineMessage($lineNumber, $line));
        }
    }

    private static function isObjectIdLine(string $line, string $algorithm): bool
    {
        $hashLength = ReferenceTarget::hashHexLength($algorithm);

        return strlen($line) === $hashLength && preg_match('/^[0-9a-fA-F]+$/', $line) === 1;
    }

    /**
     * @return list<string>
     */
    private static function lookupCandidates(string $name): array
    {
        if (str_starts_with($name, 'refs/')) {
            ReferenceName::assertValid($name);
            return [$name];
        }

        if (str_starts_with($name, 'main-worktree/refs/')) {
            $transformed = substr($name, strlen('main-worktree/'));
            ReferenceName::assertValid($transformed);
            return [$transformed];
        }

        if (str_starts_with($name, 'worktrees/')) {
            $parts = explode('/', $name, 3);
            if (count($parts) === 3 && str_starts_with($parts[2], 'refs/')) {
                ReferenceName::assertValid($parts[2]);
                return [$parts[2]];
            }
            ReferenceName::assertValid($name);
            return [];
        }

        if (ReferenceName::isPseudoRef($name)) {
            ReferenceName::assertValidPartial($name);
        } else {
            ReferenceName::assertValidPartial($name);
        }

        $candidates = [];
        foreach (['', 'tags', 'heads', 'remotes'] as $inbetween) {
            $candidate = 'refs/' . ($inbetween === '' ? '' : $inbetween . '/') . $name;
            ReferenceName::assertValid($candidate);
            $candidates[] = $candidate;
        }

        return $candidates;
    }

    private static function readLine(string $contents, int &$offset): string
    {
        $length = strlen($contents);
        if ($offset >= $length) {
            throw new \InvalidArgumentException('Packed refs line ended unexpectedly');
        }

        $lineEnd = strcspn($contents, "\r\n", $offset);
        $endOffset = $offset + $lineEnd;
        if ($endOffset >= $length) {
            throw new \InvalidArgumentException('Packed refs line is missing a line ending');
        }

        $line = substr($contents, $offset, $lineEnd);
        $newline = $contents[$endOffset];
        $offset = $endOffset + 1;
        if ($newline === "\r" && substr($contents, $offset, 1) === "\n") {
            $offset++;
        }

        return $line;
    }

    private static function readIterationLine(string $contents, int &$offset): string
    {
        $length = strlen($contents);
        $lineEnd = strcspn($contents, "\r\n", $offset);
        $endOffset = $offset + $lineEnd;
        $line = substr($contents, $offset, $lineEnd);

        if ($endOffset >= $length) {
            $offset = $length;
            return $line;
        }

        $newline = $contents[$endOffset];
        $offset = $endOffset + 1;
        if ($newline === "\r" && substr($contents, $offset, 1) === "\n") {
            $offset++;
        }

        return $line;
    }

    private static function invalidLineMessage(int $lineNumber, string $line): string
    {
        return "Invalid reference in line {$lineNumber}: \"{$line}\"";
    }
}

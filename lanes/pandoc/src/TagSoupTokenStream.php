<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Compact internal storage for a tag-soup token sequence.
 *
 * HTML readers only need a token object while examining the current token.
 * Keeping a `TagSoupTag` object for every text span and tag dominates memory
 * on ordinary prose documents, so this stream stores one byte of type data
 * plus the token payload. `tokenAt()` reconstructs a short-lived token on
 * demand and consumed payloads can be released without changing indices.
 *
 * @implements \IteratorAggregate<int, TagSoupTag>
 */
final class TagSoupTokenStream implements \Countable, \IteratorAggregate
{
    private const PAYLOAD_CHUNK_SIZE = 1024;
    private const OPEN = "\x01";
    private const CLOSE = "\x02";
    private const TEXT = "\x03";
    private const COMMENT = "\x04";
    private const WARNING = "\x05";
    private const POSITION = "\x06";

    private string $types = '';

    /** @var array<int, array<int, int|string|array<int, mixed>|null>> */
    private array $payloadChunks = [];

    /** @var array<string, int> */
    private array $nameIds = [];

    /** @var list<string> */
    private array $names = [];

    private int $released = 0;

    public function append(TagSoupTag $token): void
    {
        $index = $this->count();
        $this->types .= self::codeForType($token->type);
        $this->setPayload($index, $this->compactPayload($token));
    }

    public function replaceAt(int $index, TagSoupTag $token): void
    {
        if ($index < $this->released || $index < 0 || $index >= $this->count()) {
            throw new \OutOfBoundsException('Cannot replace a token outside the retained stream.');
        }

        $this->types[$index] = self::codeForType($token->type);
        $this->setPayload($index, $this->compactPayload($token));
    }

    public function tokenAt(int $index): ?TagSoupTag
    {
        if ($index < $this->released || $index < 0 || $index >= $this->count()) {
            return null;
        }

        $type = self::typeForCode($this->types[$index]);
        $payload = $this->payloadAt($index);
        if ($type === TagSoupTag::OPEN) {
            if (is_int($payload)) {
                return TagSoupTag::fromTokenStreamStorage(TagSoupTag::OPEN, [$this->nameForId($payload)]);
            }

            if (is_array($payload)) {
                $id = $payload[0] ?? null;
                $attributes = $payload[1] ?? [];
                if (is_int($id) && is_array($attributes)) {
                    return TagSoupTag::fromTokenStreamStorage(
                        TagSoupTag::OPEN,
                        $attributes === [] ? [$this->nameForId($id)] : [$this->nameForId($id), $attributes],
                    );
                }
            }
        }

        if ($type === TagSoupTag::CLOSE && is_int($payload)) {
            return TagSoupTag::fromTokenStreamStorage(TagSoupTag::CLOSE, $this->nameForId($payload));
        }

        return TagSoupTag::fromTokenStreamStorage($type, is_string($payload) || is_array($payload) ? $payload : null);
    }

    public function lastToken(): ?TagSoupTag
    {
        return $this->tokenAt($this->count() - 1);
    }

    public function replaceLast(TagSoupTag $token): void
    {
        $last = $this->count() - 1;
        if ($last < $this->released) {
            throw new \OutOfBoundsException('Cannot replace a token in an empty stream.');
        }

        $this->replaceAt($last, $token);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function releaseBefore(int $exclusive): void
    {
        $limit = min(max($exclusive, $this->released), $this->count());
        $firstChunkToRetain = intdiv($limit, self::PAYLOAD_CHUNK_SIZE);
        foreach (array_keys($this->payloadChunks) as $chunk) {
            if ($chunk < $firstChunkToRetain) {
                unset($this->payloadChunks[$chunk]);
            }
        }

        $firstRetainedOffset = $limit % self::PAYLOAD_CHUNK_SIZE;
        if ($firstRetainedOffset > 0 && isset($this->payloadChunks[$firstChunkToRetain])) {
            for ($offset = 0; $offset < $firstRetainedOffset; ++$offset) {
                unset($this->payloadChunks[$firstChunkToRetain][$offset]);
            }
        }
        $this->released = $limit;
    }

    /**
     * @return list<TagSoupTag>
     */
    public function slice(int $offset, ?int $length = null): array
    {
        $end = $length === null
            ? $this->count()
            : min($this->count(), max($offset, 0) + max($length, 0));
        $tokens = [];
        for ($index = max($offset, $this->released); $index < $end; ++$index) {
            $token = $this->tokenAt($index);
            if ($token instanceof TagSoupTag) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    public function count(): int
    {
        return strlen($this->types);
    }

    /**
     * @return \Traversable<int, TagSoupTag>
     */
    public function getIterator(): \Traversable
    {
        $count = $this->count();
        for ($index = $this->released; $index < $count; ++$index) {
            $token = $this->tokenAt($index);
            if ($token instanceof TagSoupTag) {
                yield $index => $token;
            }
        }
    }

    private static function codeForType(string $type): string
    {
        return match ($type) {
            TagSoupTag::OPEN => self::OPEN,
            TagSoupTag::CLOSE => self::CLOSE,
            TagSoupTag::TEXT => self::TEXT,
            TagSoupTag::COMMENT => self::COMMENT,
            TagSoupTag::WARNING => self::WARNING,
            TagSoupTag::POSITION => self::POSITION,
            default => throw new \InvalidArgumentException("Unknown tag-soup token type '{$type}'."),
        };
    }

    private static function typeForCode(string $code): string
    {
        return match ($code) {
            self::OPEN => TagSoupTag::OPEN,
            self::CLOSE => TagSoupTag::CLOSE,
            self::TEXT => TagSoupTag::TEXT,
            self::COMMENT => TagSoupTag::COMMENT,
            self::WARNING => TagSoupTag::WARNING,
            self::POSITION => TagSoupTag::POSITION,
            default => throw new \UnexpectedValueException('Unknown compact tag-soup token code.'),
        };
    }

    /**
     * @return int|string|array<int, mixed>|null
     */
    private function compactPayload(TagSoupTag $token): int|string|array|null
    {
        $storage = $token->storageForTokenStream();
        if ($token->type === TagSoupTag::OPEN && is_array($storage)) {
            $name = $storage[0] ?? '';
            $attributes = $storage[1] ?? [];
            if (is_string($name) && is_array($attributes)) {
                $id = $this->nameId($name);

                return $attributes === [] ? $id : [$id, $attributes];
            }
        }

        if ($token->type === TagSoupTag::CLOSE && is_string($storage)) {
            return $this->nameId($storage);
        }

        return $storage;
    }

    private function nameId(string $name): int
    {
        if (isset($this->nameIds[$name])) {
            return $this->nameIds[$name];
        }

        $id = count($this->names);
        $this->nameIds[$name] = $id;
        $this->names[] = $name;

        return $id;
    }

    private function nameForId(int $id): string
    {
        return $this->names[$id] ?? '';
    }

    /**
     * @param int|string|array<int, mixed>|null $payload
     */
    private function setPayload(int $index, int|string|array|null $payload): void
    {
        $chunk = intdiv($index, self::PAYLOAD_CHUNK_SIZE);
        $offset = $index % self::PAYLOAD_CHUNK_SIZE;
        $this->payloadChunks[$chunk][$offset] = $payload;
    }

    /**
     * @return int|string|array<int, mixed>|null
     */
    private function payloadAt(int $index): int|string|array|null
    {
        $chunk = intdiv($index, self::PAYLOAD_CHUNK_SIZE);
        $offset = $index % self::PAYLOAD_CHUNK_SIZE;

        return $this->payloadChunks[$chunk][$offset] ?? null;
    }
}

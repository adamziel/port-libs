<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ReflogEntry
{
    private const SUPPORTED_HASH_LENGTHS = [64, 40];

    public function __construct(
        public readonly string $previousOid,
        public readonly string $newOid,
        public readonly CommitSignature $signature,
        public readonly string $message,
    ) {
        self::assertSupportedObjectId($previousOid);
        self::assertSupportedObjectId($newOid);

        if (str_contains($message, "\n")) {
            throw new \InvalidArgumentException('Reflog message must not contain newline bytes');
        }
    }

    public static function parse(string $bytes, string $algorithm = 'any'): self
    {
        $lineEnd = strpos($bytes, "\n");
        $line = $lineEnd === false ? $bytes : substr($bytes, 0, $lineEnd);
        $tab = strpos($line, "\t");
        if ($tab === false) {
            $head = $line;
            $message = '';
        } else {
            $head = substr($line, 0, $tab);
            $message = substr($line, $tab + 1);
        }

        try {
            $previousOid = self::consumeObjectId($head, $algorithm);
            $head = self::consumeLiteralSpace($head);
            $newOid = self::consumeObjectId($head, $algorithm);
            $head = self::consumeLiteralSpace($head);
            $parsedSignature = CommitSignature::parseConsuming($head);
            if ($parsedSignature['rest'] !== '') {
                throw new \InvalidArgumentException('Committer signature did not consume the whole reflog head');
            }
        } catch (\Throwable $throwable) {
            throw new \InvalidArgumentException(
                "Invalid reflog entry: {$line}",
                0,
                $throwable,
            );
        }

        return new self($previousOid, $newOid, $parsedSignature['signature'], $message);
    }

    /**
     * @return list<self>
     */
    public static function parseAll(string $bytes, string $algorithm = 'any'): array
    {
        $entries = [];
        foreach (self::splitLines($bytes) as $index => $line) {
            try {
                $entries[] = self::parse($line, $algorithm);
            } catch (\InvalidArgumentException $exception) {
                $lineNumber = $index + 1;
                throw new \InvalidArgumentException(
                    "Invalid reflog entry at line {$lineNumber}: {$exception->getMessage()}",
                    0,
                    $exception,
                );
            }
        }

        return $entries;
    }

    /**
     * @return list<array{ok: bool, line: int, fromEnd: bool, raw: string, entry?: self, error?: string}>
     */
    public static function iterateForward(string $bytes, string $algorithm = 'any'): array
    {
        return self::iterateLines(self::splitLines($bytes), false, $algorithm);
    }

    /**
     * @return list<self>
     */
    public static function parseReverse(string $bytes, string $algorithm = 'any'): array
    {
        $lines = self::splitLines($bytes);
        $entries = [];
        $fromEnd = 0;
        for ($index = count($lines) - 1; $index >= 0; $index--) {
            try {
                $entries[] = self::parse($lines[$index], $algorithm);
            } catch (\InvalidArgumentException $exception) {
                $lineNumber = $fromEnd + 1;
                throw new \InvalidArgumentException(
                    "Invalid reflog entry at line {$lineNumber} from end: {$exception->getMessage()}",
                    0,
                    $exception,
                );
            }
            $fromEnd++;
        }

        return $entries;
    }

    /**
     * @return list<array{ok: bool, line: int, fromEnd: bool, raw: string, entry?: self, error?: string}>
     */
    public static function iterateReverse(string $bytes, string $algorithm = 'any'): array
    {
        $lines = self::splitLines($bytes);
        $reversed = [];
        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $reversed[] = $lines[$index];
        }

        return self::iterateLines($reversed, true, $algorithm);
    }

    /**
     * @return list<array{ok: bool, line: int, fromEnd: bool, raw: string, entry?: self, error?: string, bufferTooSmall?: bool}>
     */
    public static function iterateReverseBounded(string $bytes, int $bufferSize, string $algorithm = 'any'): array
    {
        if ($bufferSize <= 0) {
            throw new \InvalidArgumentException('Zero sized buffers are not allowed, use 256 bytes or more for typical logs');
        }

        $lines = self::splitLines($bytes);
        $results = [];
        $fromEnd = 0;
        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $line = $lines[$index];
            $lineNumber = $fromEnd + 1;
            if (strlen($line) > $bufferSize) {
                $raw = substr($line, -$bufferSize);
                $results[] = [
                    'ok' => false,
                    'line' => $lineNumber,
                    'fromEnd' => true,
                    'raw' => $raw,
                    'error' => 'In line ' . $lineNumber . ' from the end: buffer too small for line size, got until "' . self::formatErrorBytes($raw) . '"',
                    'bufferTooSmall' => true,
                ];
                break;
            }

            try {
                $results[] = [
                    'ok' => true,
                    'line' => $lineNumber,
                    'fromEnd' => true,
                    'raw' => $line,
                    'entry' => self::parse($line, $algorithm),
                ];
            } catch (\InvalidArgumentException $exception) {
                $results[] = [
                    'ok' => false,
                    'line' => $lineNumber,
                    'fromEnd' => true,
                    'raw' => $line,
                    'error' => "In line {$lineNumber} from the end: {$exception->getMessage()}",
                ];
            }
            $fromEnd++;
        }

        return $results;
    }

    public static function appendLine(
        ?ReferenceTarget $previous,
        ReferenceTarget $new,
        CommitSignature $committer,
        string $message = '',
        string $algorithm = 'sha1',
    ): string {
        if (!$new->isObject()) {
            throw new \InvalidArgumentException('Reflog append entries require an object target');
        }
        if ($previous !== null && !$previous->isObject()) {
            $previous = null;
        }
        if (str_contains($message, "\n") || str_contains($message, "\r")) {
            throw new \InvalidArgumentException('Reflog message must not contain newline bytes');
        }

        ReferenceTarget::assertValidObjectId($new->value, $algorithm);
        if ($previous !== null) {
            ReferenceTarget::assertValidObjectId($previous->value, $algorithm);
        }

        $old = $previous?->value ?? str_repeat('0', ReferenceTarget::hashHexLength($algorithm));
        $entry = new self($old, $new->value, $committer->trimmed(), $message);

        return $entry->storageBytes(true);
    }

    public function storageBytes(bool $omitEmptyMessageTab = false): string
    {
        $line = $this->previousOid . ' ' . $this->newOid . ' ' . $this->signature->trimmed()->storageBytes();
        if ($this->message !== '' || !$omitEmptyMessageTab) {
            $line .= "\t{$this->message}";
        }

        return $line . "\n";
    }

    private static function consumeObjectId(string &$head, string $algorithm): string
    {
        $normalized = strtolower($algorithm);
        $lengths = $normalized === 'any'
            ? self::SUPPORTED_HASH_LENGTHS
            : [ReferenceTarget::hashHexLength($normalized)];

        foreach ($lengths as $length) {
            $candidate = substr($head, 0, $length);
            if (strlen($candidate) === $length && preg_match('/^[0-9a-fA-F]+$/', $candidate) === 1) {
                $head = substr($head, $length);

                return strtolower($candidate);
            }
        }

        throw new \InvalidArgumentException("Expected {$algorithm} object id at start of reflog entry");
    }

    private static function consumeLiteralSpace(string $head): string
    {
        if (!str_starts_with($head, ' ')) {
            throw new \InvalidArgumentException('Expected one space in reflog entry');
        }

        return substr($head, 1);
    }

    private static function assertSupportedObjectId(string $oid): void
    {
        foreach (self::SUPPORTED_HASH_LENGTHS as $length) {
            if (preg_match('/^[0-9a-fA-F]{' . $length . '}$/', $oid) === 1) {
                return;
            }
        }

        throw new \InvalidArgumentException('Reflog object ids must be SHA-1 or SHA-256 hex ids');
    }

    private static function formatErrorBytes(string $bytes): string
    {
        return addcslashes($bytes, "\0..\37\"\\");
    }

    /**
     * @param list<string> $lines
     * @return list<array{ok: bool, line: int, fromEnd: bool, raw: string, entry?: self, error?: string}>
     */
    private static function iterateLines(array $lines, bool $fromEnd, string $algorithm): array
    {
        $results = [];
        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            try {
                $results[] = [
                    'ok' => true,
                    'line' => $lineNumber,
                    'fromEnd' => $fromEnd,
                    'raw' => $line,
                    'entry' => self::parse($line, $algorithm),
                ];
            } catch (\InvalidArgumentException $exception) {
                $suffix = $fromEnd ? ' from the end' : '';
                $results[] = [
                    'ok' => false,
                    'line' => $lineNumber,
                    'fromEnd' => $fromEnd,
                    'raw' => $line,
                    'error' => "In line {$lineNumber}{$suffix}: {$exception->getMessage()}",
                ];
            }
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private static function splitLines(string $bytes): array
    {
        if ($bytes === '') {
            return [];
        }

        $lines = explode("\n", $bytes);
        if (end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }
}

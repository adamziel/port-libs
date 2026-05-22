<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class Commit
{
    /**
     * @param list<string> $parents
     * @param array<string, list<string>> $headers
     * @param list<array{name: string, value: string}> $extraHeaderList
     */
    public function __construct(
        public readonly string $tree,
        public readonly array $parents,
        public readonly string $author,
        public readonly string $committer,
        public readonly string $message,
        public readonly array $headers,
        public readonly ?string $encoding = null,
        public readonly array $extraHeaders = [],
        public readonly ?string $rawBody = null,
        private readonly array $extraHeaderList = [],
    ) {
    }

    public static function parse(string $body, string $algorithm = 'sha1'): self
    {
        $separator = strpos($body, "\n\n");
        if ($separator === false) {
            throw new \InvalidArgumentException('Commit message is missing header separator');
        }

        $headerBlock = substr($body, 0, $separator);
        $message = substr($body, $separator + 2);
        $headers = [];
        $current = null;
        $currentEntryIndex = null;
        $headerEntries = [];

        foreach (explode("\n", $headerBlock) as $line) {
            if ($line === '') {
                continue;
            }
            if ($line[0] === ' ') {
                if ($current === null) {
                    throw new \InvalidArgumentException('Commit continuation line without a header');
                }
                $last = array_key_last($headers[$current]);
                $headers[$current][$last] .= "\n" . substr($line, 1);
                if ($currentEntryIndex !== null) {
                    $headerEntries[$currentEntryIndex]['value'] .= "\n" . substr($line, 1);
                }
                continue;
            }

            $space = strpos($line, ' ');
            if ($space === false) {
                throw new \InvalidArgumentException('Invalid commit header line: ' . $line);
            }
            $current = substr($line, 0, $space);
            $value = substr($line, $space + 1);
            $headers[$current][] = $value;
            $currentEntryIndex = count($headerEntries);
            $headerEntries[] = ['name' => $current, 'value' => $value];
        }

        $hashLength = ReferenceTarget::hashHexLength($algorithm);
        foreach (['tree', 'author', 'committer'] as $required) {
            if (($headers[$required] ?? []) === []) {
                throw new \InvalidArgumentException("Commit is missing required {$required} header");
            }
        }

        $tree = strtolower($headers['tree'][0]);
        if (!preg_match('/^[0-9a-f]{' . $hashLength . '}$/', $tree)) {
            throw new \InvalidArgumentException("Commit tree must be a {$hashLength}-character {$algorithm} hex object id");
        }

        $parents = array_map('strtolower', $headers['parent'] ?? []);
        foreach ($parents as $parent) {
            if (!preg_match('/^[0-9a-f]{' . $hashLength . '}$/', $parent)) {
                throw new \InvalidArgumentException("Commit parent must be a {$hashLength}-character {$algorithm} hex object id");
            }
        }

        CommitSignature::parse($headers['author'][0]);
        CommitSignature::parse($headers['committer'][0]);

        $extraHeaders = [];
        $extraHeaderList = [];
        $standardHeaders = ['tree', 'parent', 'author', 'committer', 'encoding'];
        foreach ($headers as $name => $values) {
            if (in_array($name, $standardHeaders, true)) {
                continue;
            }
            $extraHeaders[$name] = $values;
        }
        foreach ($headerEntries as $entry) {
            if (!in_array($entry['name'], $standardHeaders, true)) {
                $extraHeaderList[] = $entry;
            }
        }

        return new self(
            $tree,
            $parents,
            $headers['author'][0],
            $headers['committer'][0],
            $message,
            $headers,
            $headers['encoding'][0] ?? null,
            $extraHeaders,
            $body,
            $extraHeaderList,
        );
    }

    /**
     * @return list<array{ok: bool, token?: array<string, mixed>, error?: string}>
     */
    public static function iterateTokens(string $body, string $algorithm = 'sha1'): array
    {
        $offset = 0;
        $tokens = [];
        $hashLength = ReferenceTarget::hashHexLength($algorithm);

        if ($body === '') {
            return [];
        }

        try {
            $tree = self::readRequiredTokenHeader($body, $offset, 'tree');
            self::validateTokenObjectId($tree, $hashLength, $algorithm, 'tree');
            $tokens[] = self::okToken(['type' => 'tree', 'id' => strtolower($tree), 'rawId' => $tree]);

            while (substr($body, $offset, 7) === 'parent ') {
                $parent = self::readRequiredTokenHeader($body, $offset, 'parent');
                self::validateTokenObjectId($parent, $hashLength, $algorithm, 'parent');
                $tokens[] = self::okToken(['type' => 'parent', 'id' => strtolower($parent), 'rawId' => $parent]);
            }

            $author = self::readRequiredTokenHeader($body, $offset, 'author');
            CommitSignature::parse($author);
            $tokens[] = self::okToken(['type' => 'author', 'signature' => $author]);

            $committer = self::readRequiredTokenHeader($body, $offset, 'committer');
            CommitSignature::parse($committer);
            $tokens[] = self::okToken(['type' => 'committer', 'signature' => $committer]);

            if (substr($body, $offset, 9) === 'encoding ') {
                $tokens[] = self::okToken([
                    'type' => 'encoding',
                    'encoding' => self::readRequiredTokenHeader($body, $offset, 'encoding'),
                ]);
            }

            while ($offset < strlen($body) && ($body[$offset] ?? '') !== "\n") {
                [$name, $value] = self::readAnyTokenHeader($body, $offset);
                $tokens[] = self::okToken(['type' => 'extraHeader', 'name' => $name, 'value' => $value]);
            }

            if (($body[$offset] ?? null) !== "\n") {
                throw new \InvalidArgumentException('Commit message is missing header separator');
            }
            $tokens[] = self::okToken(['type' => 'message', 'message' => substr($body, $offset + 1)]);
        } catch (\InvalidArgumentException $exception) {
            $tokens[] = ['ok' => false, 'error' => $exception->getMessage()];
        }

        return $tokens;
    }

    public function authorSignature(): CommitSignature
    {
        return CommitSignature::parse($this->author);
    }

    public function committerSignature(): CommitSignature
    {
        return CommitSignature::parse($this->committer);
    }

    public function parsedMessage(): CommitMessage
    {
        return CommitMessage::fromBytes($this->message);
    }

    public function messageSummary(): string
    {
        return $this->parsedMessage()->summary();
    }

    public function messageTitle(): string
    {
        return $this->parsedMessage()->title;
    }

    public function messageBody(): ?string
    {
        return $this->parsedMessage()->body;
    }

    public function messageBodyWithoutTrailers(): ?string
    {
        return $this->parsedMessage()->bodyWithoutTrailers();
    }

    /**
     * @return list<CommitTrailer>
     */
    public function messageTrailers(): array
    {
        return $this->parsedMessage()->trailers();
    }

    /**
     * @return list<CommitTrailer>
     */
    public function signedOffByTrailers(): array
    {
        return $this->parsedMessage()->signedOffByTrailers();
    }

    /**
     * @return list<CommitTrailer>
     */
    public function coAuthoredByTrailers(): array
    {
        return $this->parsedMessage()->coAuthoredByTrailers();
    }

    /**
     * @return list<CommitTrailer>
     */
    public function ackedByTrailers(): array
    {
        return $this->parsedMessage()->ackedByTrailers();
    }

    /**
     * @return list<CommitTrailer>
     */
    public function reviewedByTrailers(): array
    {
        return $this->parsedMessage()->reviewedByTrailers();
    }

    /**
     * @return list<CommitTrailer>
     */
    public function testedByTrailers(): array
    {
        return $this->parsedMessage()->testedByTrailers();
    }

    /**
     * @return list<CommitTrailer>
     */
    public function authorTrailers(): array
    {
        return $this->parsedMessage()->authorTrailers();
    }

    /**
     * @return list<CommitTrailer>
     */
    public function attributionTrailers(): array
    {
        return $this->parsedMessage()->attributionTrailers();
    }

    public function storageBytes(): string
    {
        $out = "tree {$this->tree}\n";
        foreach ($this->parents as $parent) {
            $out .= "parent {$parent}\n";
        }

        CommitSignature::parse($this->author);
        CommitSignature::parse($this->committer);
        $out .= "author {$this->author}\n"
            . "committer {$this->committer}\n";

        if ($this->encoding !== null) {
            self::validateSingleLineHeaderValue('encoding', $this->encoding);
            $out .= "encoding {$this->encoding}\n";
        }

        foreach ($this->allExtraHeaders() as $entry) {
            $out .= self::formatMultiLineHeader($entry['name'], $entry['value']);
        }

        return $out . "\n" . $this->message;
    }

    public function size(): int
    {
        return strlen($this->storageBytes());
    }

    public function object(): GitObject
    {
        return new GitObject('commit', $this->storageBytes());
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    public function allExtraHeaders(): array
    {
        if ($this->extraHeaderList !== []) {
            return $this->extraHeaderList;
        }

        $entries = [];
        foreach ($this->extraHeaders as $name => $values) {
            foreach ($values as $value) {
                $entries[] = ['name' => $name, 'value' => $value];
            }
        }

        return $entries;
    }

    public function extraHeader(string $name): ?string
    {
        foreach ($this->allExtraHeaders() as $entry) {
            if ($entry['name'] === $name) {
                return $entry['value'];
            }
        }

        return null;
    }

    public function extraHeaderPosition(string $name): ?int
    {
        foreach ($this->allExtraHeaders() as $position => $entry) {
            if ($entry['name'] === $name) {
                return $position;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function extraHeaderValues(string $name): array
    {
        $values = [];
        foreach ($this->allExtraHeaders() as $entry) {
            if ($entry['name'] === $name) {
                $values[] = $entry['value'];
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    public function mergeTagHeaders(): array
    {
        return $this->extraHeaderValues('mergetag');
    }

    /**
     * @return list<GitTag>
     */
    public function mergeTags(string $algorithm = 'sha1'): array
    {
        return array_map(
            static fn (string $header): GitTag => GitTag::parse($header, $algorithm),
            $this->mergeTagHeaders(),
        );
    }

    public function pgpSignature(): ?string
    {
        $header = $this->signatureHeaderWithRange();
        if ($header !== null) {
            return $header['signature'];
        }

        return $this->extraHeader('gpgsig');
    }

    public function signedDataForSignature(): ?string
    {
        $header = $this->signatureHeaderWithRange();
        if ($header === null || $this->rawBody === null) {
            return null;
        }

        return substr($this->rawBody, 0, $header['start']) . substr($this->rawBody, $header['end']);
    }

    /**
     * @return array{signature: string, start: int, end: int}|null
     */
    private function signatureHeaderWithRange(): ?array
    {
        if ($this->rawBody === null) {
            return null;
        }

        $offset = 0;
        $length = strlen($this->rawBody);
        while ($offset < $length) {
            $start = $offset;
            [, $line, $nextOffset] = self::lineWithTerminatorAt($this->rawBody, $offset);
            if ($line === '') {
                break;
            }
            if ($line[0] === ' ') {
                $offset = $nextOffset;
                continue;
            }

            $space = strpos($line, ' ');
            if ($space === false) {
                $offset = $nextOffset;
                continue;
            }

            $name = substr($line, 0, $space);
            $value = substr($line, $space + 1);
            $end = $nextOffset;
            $peek = $nextOffset;
            while ($peek < $length) {
                [, $nextLine, $afterNext] = self::lineWithTerminatorAt($this->rawBody, $peek);
                if ($nextLine === '' || $nextLine[0] !== ' ') {
                    break;
                }
                $value .= "\n" . substr($nextLine, 1);
                $end = $afterNext;
                $peek = $afterNext;
            }

            if ($name === 'gpgsig') {
                return ['signature' => $value, 'start' => $start, 'end' => $end];
            }

            $offset = $end;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $token
     * @return array{ok: bool, token: array<string, mixed>}
     */
    private static function okToken(array $token): array
    {
        return ['ok' => true, 'token' => $token];
    }

    private static function readRequiredTokenHeader(string $input, int &$offset, string $name): string
    {
        if (strpos($input, "\n", $offset) === false) {
            throw new \InvalidArgumentException("Commit {$name} header is not newline terminated");
        }

        [, $line, $nextOffset] = self::lineWithTerminatorAt($input, $offset);
        $prefix = $name . ' ';
        if (!str_starts_with($line, $prefix)) {
            throw new \InvalidArgumentException("Commit is missing required {$name} header");
        }

        $offset = $nextOffset;
        return substr($line, strlen($prefix));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function readAnyTokenHeader(string $input, int &$offset): array
    {
        if (strpos($input, "\n", $offset) === false) {
            throw new \InvalidArgumentException('Commit extra header is not newline terminated');
        }

        [, $line, $nextOffset] = self::lineWithTerminatorAt($input, $offset);
        if ($line === '' || $line[0] === ' ') {
            throw new \InvalidArgumentException('Commit extra header has no field name');
        }

        $space = strpos($line, ' ');
        if ($space === false) {
            throw new \InvalidArgumentException('Commit extra header has no value separator');
        }

        $name = substr($line, 0, $space);
        $value = substr($line, $space + 1);
        $offset = $nextOffset;
        while ($offset < strlen($input) && ($input[$offset] ?? '') === ' ') {
            if (strpos($input, "\n", $offset) === false) {
                throw new \InvalidArgumentException('Commit extra header continuation is not newline terminated');
            }
            [, $continuation, $nextOffset] = self::lineWithTerminatorAt($input, $offset);
            $value .= "\n" . substr($continuation, 1);
            $offset = $nextOffset;
        }

        return [$name, $value];
    }

    private static function validateTokenObjectId(string $id, int $hashLength, string $algorithm, string $field): void
    {
        if (preg_match('/^[0-9a-fA-F]{' . $hashLength . '}$/', $id) !== 1) {
            throw new \InvalidArgumentException("Commit {$field} must be a {$hashLength}-character {$algorithm} hex object id");
        }
    }

    private static function validateSingleLineHeaderValue(string $name, string $value): void
    {
        if ($value === '') {
            throw new \InvalidArgumentException("Commit {$name} header value cannot be empty");
        }
        if (str_contains($value, "\n")) {
            throw new \InvalidArgumentException("Commit {$name} header value cannot contain a newline");
        }
    }

    private static function formatMultiLineHeader(string $name, string $value): string
    {
        if ($name === '' || strpbrk($name, " \t\n\r") !== false) {
            throw new \InvalidArgumentException('Commit extra header name is invalid');
        }

        $out = $name . ' ';
        $offset = 0;
        $first = true;
        $length = strlen($value);
        while ($offset < $length) {
            $newline = strpos($value, "\n", $offset);
            if ($newline === false) {
                $line = substr($value, $offset);
                $offset = $length;
            } else {
                $line = substr($value, $offset, $newline - $offset + 1);
                $offset = $newline + 1;
            }

            if (!$first) {
                $out .= ' ';
            }
            $out .= $line;
            $first = false;
        }

        if (!str_ends_with($value, "\n")) {
            $out .= "\n";
        }

        return $out;
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private static function lineWithTerminatorAt(string $input, int $offset): array
    {
        if ($offset >= strlen($input)) {
            return ['', '', $offset];
        }

        $newline = strpos($input, "\n", $offset);
        if ($newline === false) {
            $raw = substr($input, $offset);
            return [$raw, self::trimLineEnding($raw), strlen($input)];
        }

        $raw = substr($input, $offset, $newline - $offset + 1);
        return [$raw, self::trimLineEnding($raw), $newline + 1];
    }

    private static function trimLineEnding(string $line): string
    {
        if (str_ends_with($line, "\n")) {
            $line = substr($line, 0, -1);
            if (str_ends_with($line, "\r")) {
                $line = substr($line, 0, -1);
            }
            return $line;
        }

        if (str_ends_with($line, "\r")) {
            return substr($line, 0, -1);
        }

        return $line;
    }
}

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
        $offset = 0;
        $headers = [];
        $extraHeaders = [];
        $extraHeaderList = [];
        $appendHeader = static function (string $name, string $value) use (&$headers): void {
            $headers[$name][] = $value;
        };
        $hashLength = ReferenceTarget::hashHexLength($algorithm);

        $treeRaw = self::readRequiredTokenHeader($body, $offset, 'tree');
        self::validateTokenObjectId($treeRaw, $hashLength, $algorithm, 'tree');
        $tree = strtolower($treeRaw);
        $appendHeader('tree', $treeRaw);

        $parents = [];
        while (substr($body, $offset, 7) === 'parent ') {
            $parentRaw = self::readRequiredTokenHeader($body, $offset, 'parent');
            self::validateTokenObjectId($parentRaw, $hashLength, $algorithm, 'parent');
            $parents[] = strtolower($parentRaw);
            $appendHeader('parent', $parentRaw);
        }

        $author = self::readRequiredTokenHeader($body, $offset, 'author');
        self::parseCompleteSignature($author, 'author');
        $appendHeader('author', $author);

        $committer = self::readRequiredTokenHeader($body, $offset, 'committer');
        self::parseCompleteSignature($committer, 'committer');
        $appendHeader('committer', $committer);

        $encoding = null;
        if (substr($body, $offset, 9) === 'encoding ') {
            $encoding = self::readRequiredTokenHeader($body, $offset, 'encoding');
            $appendHeader('encoding', $encoding);
        }

        while ($offset < strlen($body) && ($body[$offset] ?? '') !== "\n") {
            [$name, $value] = self::readAnyTokenHeader($body, $offset);
            $appendHeader($name, $value);
            $extraHeaders[$name][] = $value;
            $extraHeaderList[] = ['name' => $name, 'value' => $value];
        }

        if (($body[$offset] ?? null) !== "\n") {
            throw new \InvalidArgumentException('Commit message is missing header separator');
        }

        return new self(
            $tree,
            $parents,
            $author,
            $committer,
            substr($body, $offset + 1),
            $headers,
            $encoding,
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
            self::parseCompleteSignature($author, 'author');
            $tokens[] = self::okToken(['type' => 'author', 'signature' => $author]);

            $committer = self::readRequiredTokenHeader($body, $offset, 'committer');
            self::parseCompleteSignature($committer, 'committer');
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
        self::validateWritableObjectId($this->tree, 'tree');
        $hashLength = strlen($this->tree);
        $out = "tree {$this->tree}\n";
        foreach ($this->parents as $parent) {
            self::validateWritableObjectId($parent, 'parent');
            if (strlen($parent) !== $hashLength) {
                throw new \InvalidArgumentException('Commit parent object id must use the same hash length as the tree object id');
            }
            $out .= "parent {$parent}\n";
        }

        self::validateWritableSignature($this->author, 'author');
        self::validateWritableSignature($this->committer, 'committer');
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

    public function withGpgSignature(string $signature): self
    {
        if ($this->pgpSignature() !== null) {
            return $this;
        }

        $extraHeaderList = $this->allExtraHeaders();
        $extraHeaderList[] = ['name' => 'gpgsig', 'value' => $signature];

        $headers = $this->headers;
        $headers['gpgsig'][] = $signature;

        $signed = new self(
            $this->tree,
            $this->parents,
            $this->author,
            $this->committer,
            $this->message,
            $headers,
            $this->encoding,
            self::groupExtraHeaders($extraHeaderList),
            null,
            $extraHeaderList,
        );

        $algorithm = strlen($this->tree) === ReferenceTarget::hashHexLength('sha256') ? 'sha256' : 'sha1';

        return self::parse($signed->storageBytes(), $algorithm);
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

    /**
     * @param list<array{name: string, value: string}> $entries
     * @return array<string, list<string>>
     */
    private static function groupExtraHeaders(array $entries): array
    {
        $headers = [];
        foreach ($entries as $entry) {
            $headers[$entry['name']][] = $entry['value'];
        }

        return $headers;
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
        $signature = $this->signatureForVerification();
        return $signature === null ? null : $signature['signedData'];
    }

    /**
     * Return the first gpgsig value and signed bytes from raw commit data.
     *
     * This mirrors gix_object::CommitRefIter::signature(): headers before the
     * first signature are validated, but bytes after that signature are not
     * decoded before the signed-data range is returned.
     *
     * @return array{signature: string, signedData: string}|null
     */
    public static function signatureForVerificationFromBytes(string $body, string $algorithm = 'sha1'): ?array
    {
        $header = self::signatureHeaderWithRangeFromCommitBytes($body, $algorithm);
        if ($header === null) {
            return null;
        }

        return [
            'signature' => $header['signature'],
            'signedData' => substr($body, 0, $header['start']) . substr($body, $header['end']),
        ];
    }

    /**
     * Return a commit object's first gpgsig value and exact signed bytes.
     *
     * This mirrors gix::object::Commit::signature(): callers that already
     * decoded a loose object do not have to manually split the commit body.
     *
     * @return array{signature: string, signedData: string}|null
     */
    public static function signatureForVerificationFromObject(GitObject $object, string $algorithm = 'sha1'): ?array
    {
        if ($object->type !== 'commit') {
            throw new \InvalidArgumentException('Git object signature verification requires a commit object');
        }

        return self::signatureForVerificationFromBytes($object->body, $algorithm);
    }

    /**
     * Decode loose-object storage bytes and return commit gpgsig verification data.
     *
     * @return array{signature: string, signedData: string}|null
     */
    public static function signatureForVerificationFromStorageBytes(string $bytes, string $algorithm = 'sha1'): ?array
    {
        return self::signatureForVerificationFromObject(GitObject::fromStorageBytes($bytes), $algorithm);
    }

    /**
     * Return the first gpgsig value with the exact commit bytes that were signed.
     *
     * @return array{signature: string, signedData: string}|null
     */
    public function signatureForVerification(): ?array
    {
        $header = $this->signatureHeaderWithRange();
        if ($header === null || $this->rawBody === null) {
            return null;
        }

        return [
            'signature' => $header['signature'],
            'signedData' => substr($this->rawBody, 0, $header['start']) . substr($this->rawBody, $header['end']),
        ];
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
            if (($this->rawBody[$offset] ?? '') === "\n") {
                break;
            }

            if (($this->rawBody[$offset] ?? '') === ' ') {
                [, , $nextOffset] = self::lineWithTerminatorAt($this->rawBody, $offset);
                $offset = $nextOffset;
                continue;
            }

            try {
                $header = self::readAnyTokenHeaderWithRange($this->rawBody, $offset, true);
            } catch (\InvalidArgumentException) {
                break;
            }

            if ($header['name'] === 'gpgsig') {
                return ['signature' => $header['value'], 'start' => $header['start'], 'end' => $header['end']];
            }
        }

        return null;
    }

    /**
     * @return array{signature: string, start: int, end: int}|null
     */
    private static function signatureHeaderWithRangeFromCommitBytes(string $body, string $algorithm): ?array
    {
        $offset = 0;
        $hashLength = ReferenceTarget::hashHexLength($algorithm);

        $tree = self::readRequiredTokenHeader($body, $offset, 'tree');
        self::validateTokenObjectId($tree, $hashLength, $algorithm, 'tree');

        while (substr($body, $offset, 7) === 'parent ') {
            $parent = self::readRequiredTokenHeader($body, $offset, 'parent');
            self::validateTokenObjectId($parent, $hashLength, $algorithm, 'parent');
        }

        self::parseCompleteSignature(self::readRequiredTokenHeader($body, $offset, 'author'), 'author');
        self::parseCompleteSignature(self::readRequiredTokenHeader($body, $offset, 'committer'), 'committer');

        if (substr($body, $offset, 9) === 'encoding ') {
            self::readRequiredTokenHeader($body, $offset, 'encoding');
        }

        while ($offset < strlen($body)) {
            if (($body[$offset] ?? '') === "\n") {
                return null;
            }

            $header = self::readAnyTokenHeaderWithRange($body, $offset, true);
            if ($header['name'] === 'gpgsig') {
                return ['signature' => $header['value'], 'start' => $header['start'], 'end' => $header['end']];
            }
        }

        throw new \InvalidArgumentException('Commit message is missing header separator');
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
        $header = self::readAnyTokenHeaderWithRange($input, $offset, true);
        return [$header['name'], $header['value']];
    }

    /**
     * @return array{name: string, value: string, start: int, end: int}
     */
    private static function readAnyTokenHeaderWithRange(string $input, int &$offset, bool $preserveMultiLineTerminators): array
    {
        if (strpos($input, "\n", $offset) === false) {
            throw new \InvalidArgumentException('Commit extra header is not newline terminated');
        }

        $start = $offset;
        [$rawLine, $line, $nextOffset] = self::lineWithTerminatorAt($input, $offset);
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
        $hasContinuation = false;
        while ($offset < strlen($input) && ($input[$offset] ?? '') === ' ') {
            if (strpos($input, "\n", $offset) === false) {
                throw new \InvalidArgumentException('Commit extra header continuation is not newline terminated');
            }
            [$rawContinuation, $continuation, $nextOffset] = self::lineWithTerminatorAt($input, $offset);
            if ($preserveMultiLineTerminators) {
                if (!$hasContinuation) {
                    $value = substr($rawLine, $space + 1);
                }
                $value .= substr($rawContinuation, 1);
            } else {
                $value .= "\n" . substr($continuation, 1);
            }
            $offset = $nextOffset;
            $hasContinuation = true;
        }

        return ['name' => $name, 'value' => $value, 'start' => $start, 'end' => $offset];
    }

    private static function validateTokenObjectId(string $id, int $hashLength, string $algorithm, string $field): void
    {
        if (preg_match('/^[0-9a-fA-F]{' . $hashLength . '}$/', $id) !== 1) {
            throw new \InvalidArgumentException("Commit {$field} must be a {$hashLength}-character {$algorithm} hex object id");
        }
    }

    private static function validateWritableObjectId(string $id, string $field): void
    {
        if (preg_match('/^(?:[0-9a-f]{40}|[0-9a-f]{64})$/', $id) !== 1) {
            throw new \InvalidArgumentException("Commit {$field} must be a lowercase sha1 or sha256 hex object id");
        }
    }

    private static function validateWritableSignature(string $signature, string $field): void
    {
        if (str_contains($signature, "\n") || str_contains($signature, "\r")) {
            throw new \InvalidArgumentException("Commit {$field} signature cannot contain line break bytes");
        }

        self::parseCompleteSignature($signature, $field)->storageBytes();
    }

    private static function parseCompleteSignature(string $signature, string $field): CommitSignature
    {
        $parsed = CommitSignature::parseConsuming($signature);
        if ($parsed['rest'] !== '') {
            throw new \InvalidArgumentException("Commit {$field} signature has unconsumed bytes after the timestamp");
        }

        return $parsed['signature'];
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
            return substr($line, 0, -1);
        }

        return $line;
    }
}

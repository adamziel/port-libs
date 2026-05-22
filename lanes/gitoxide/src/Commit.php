<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class Commit
{
    /**
     * @param list<string> $parents
     * @param array<string, list<string>> $headers
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
    ) {
    }

    public static function parse(string $body, string $algorithm = 'sha1'): self
    {
        [$headerBlock, $message] = array_pad(explode("\n\n", $body, 2), 2, '');
        $headers = [];
        $current = null;

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
                continue;
            }

            $space = strpos($line, ' ');
            if ($space === false) {
                throw new \InvalidArgumentException('Invalid commit header line: ' . $line);
            }
            $current = substr($line, 0, $space);
            $headers[$current][] = substr($line, $space + 1);
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
        foreach ($headers as $name => $values) {
            if (in_array($name, ['tree', 'parent', 'author', 'committer', 'encoding'], true)) {
                continue;
            }
            $extraHeaders[$name] = $values;
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
        );
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

    public function pgpSignature(): ?string
    {
        $header = $this->signatureHeaderWithRange();
        if ($header !== null) {
            return $header['signature'];
        }

        return $this->extraHeaders['gpgsig'][0] ?? null;
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

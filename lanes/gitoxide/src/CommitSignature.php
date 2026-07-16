<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class CommitSignature
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $time,
    ) {
    }

    public static function parse(string $raw): self
    {
        return self::parseConsuming($raw)['signature'];
    }

    /**
     * Parse one signature and return the remaining input after the parsed time bytes.
     *
     * @return array{signature: self, rest: string}
     */
    public static function parseConsuming(string $raw): array
    {
        $eol = strpos($raw, "\n");
        $line = $eol === false ? $raw : substr($raw, 0, $eol);
        $right = strrpos($line, '>');
        if ($right === false) {
            throw new \InvalidArgumentException("Commit signature closing '>' was not found");
        }

        $nameAndEmail = substr($line, 0, $right);
        $left = strpos($nameAndEmail, '<');
        if ($left === false) {
            throw new \InvalidArgumentException("Commit signature opening '<' was not found");
        }

        $skipLeft = 0;
        $length = strlen($line);
        for ($index = $left; $index < $length && $line[$index] === '<'; $index++) {
            $skipLeft++;
        }

        $skipRight = 0;
        for ($index = strlen($nameAndEmail) - 1; $index >= 0 && $nameAndEmail[$index] === '>'; $index--) {
            $skipRight++;
        }

        $emailStart = $left + $skipLeft;
        $emailEnd = $right - $skipRight;
        if ($emailEnd < $emailStart) {
            throw new \InvalidArgumentException('Commit signature skipped delimiters overlap');
        }

        $name = substr($line, 0, $left);
        if (str_ends_with($name, ' ')) {
            $name = substr($name, 0, -1);
        }

        $email = substr($line, $emailStart, $emailEnd - $emailStart);
        $rest = substr($raw, $right + 1);
        if (str_starts_with($rest, ' ')) {
            $rest = substr($rest, 1);
        }

        $timeLength = strspn($rest, "+-0123456789 \t");
        $time = substr($rest, 0, $timeLength);

        return [
            'signature' => new self($name, $email, $time),
            'rest' => substr($rest, $timeLength),
        ];
    }

    public function trimmed(): self
    {
        return new self(trim($this->name), trim($this->email), trim($this->time));
    }

    public function identity(): CommitIdentity
    {
        return new CommitIdentity($this->name, $this->email);
    }

    public function seconds(): int
    {
        $first = explode(' ', trim($this->time), 2)[0] ?? '';
        return preg_match('/^-?\d+$/', $first) === 1 ? (int) $first : 0;
    }

    /**
     * @return array{seconds: int, offset: int}|null
     */
    public function time(): ?array
    {
        $input = trim($this->time);
        if ($input === '' || str_contains($input, ':')) {
            return null;
        }

        $parts = preg_split('/\s+/', $input);
        if ($parts === false || $parts === []) {
            return null;
        }

        $secondsToken = array_shift($parts);
        if ($secondsToken === null) {
            return null;
        }

        if (preg_match('/^-?\d+$/', $secondsToken) === 1) {
            $seconds = (int) $secondsToken;
        } elseif (preg_match('/^(\d+)/', $secondsToken, $matches) === 1) {
            $seconds = (int) $matches[1];
        } else {
            return null;
        }

        if ($parts === []) {
            return ['seconds' => $seconds, 'offset' => 0];
        }

        $offset = 0;
        if (count($parts) === 1) {
            $offset = self::parseOffsetToken($parts[0]) ?? 0;
        }

        return ['seconds' => $seconds, 'offset' => $offset];
    }

    public function offsetSeconds(): ?int
    {
        return $this->time()['offset'] ?? null;
    }

    public function storageBytes(): string
    {
        self::validateWriteToken($this->name);
        self::validateWriteToken($this->email);
        self::validateWriteToken($this->time);

        return "{$this->name} <{$this->email}> {$this->time}";
    }

    private static function parseOffsetToken(string $offset): ?int
    {
        if (preg_match('/^([+-])(\d{2})(\d{2})(\d{2})?$/', $offset, $matches) !== 1) {
            return null;
        }

        $hours = (int) $matches[2];
        $minutes = (int) $matches[3];
        $seconds = isset($matches[4]) && $matches[4] !== '' ? (int) $matches[4] : 0;
        $total = ($hours * 3600) + ($minutes * 60) + $seconds;

        return $matches[1] === '-' ? -$total : $total;
    }

    private static function validateWriteToken(string $token): void
    {
        if (strpbrk($token, "<>\n") !== false) {
            throw new \InvalidArgumentException("Signature name, email, and time must not contain '<', '>' or newline bytes");
        }
    }
}

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
        if (substr($rest, $timeLength) !== '') {
            throw new \InvalidArgumentException('Commit signature has trailing bytes after timestamp');
        }

        return (new self($name, $email, $time))->trimmed();
    }

    public function trimmed(): self
    {
        return new self(trim($this->name), trim($this->email), trim($this->time));
    }

    public function seconds(): int
    {
        $first = explode(' ', trim($this->time), 2)[0] ?? '';
        return preg_match('/^-?\d+$/', $first) === 1 ? (int) $first : 0;
    }

    public function offsetSeconds(): ?int
    {
        if (preg_match('/(?:^|[ \t])([+-])(\d{2})(\d{2})$/', trim($this->time), $matches) !== 1) {
            return null;
        }

        $hours = (int) $matches[2];
        $minutes = (int) $matches[3];
        if ($minutes >= 60) {
            return null;
        }

        $seconds = ($hours * 3600) + ($minutes * 60);
        return $matches[1] === '-' ? -$seconds : $seconds;
    }

    public function storageBytes(): string
    {
        return "{$this->name} <{$this->email}> {$this->time}";
    }
}

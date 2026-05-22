<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class CommitIdentity
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {
    }

    public static function parse(string $raw): self
    {
        $eol = strpos($raw, "\n");
        $line = $eol === false ? $raw : substr($raw, 0, $eol);
        $right = strrpos($line, '>');
        if ($right === false) {
            throw new \InvalidArgumentException("Commit identity closing '>' was not found");
        }

        $nameAndEmail = substr($line, 0, $right);
        $left = strpos($nameAndEmail, '<');
        if ($left === false) {
            throw new \InvalidArgumentException("Commit identity opening '<' was not found");
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
            throw new \InvalidArgumentException('Commit identity skipped delimiters overlap');
        }

        $name = substr($line, 0, $left);
        if (str_ends_with($name, ' ')) {
            $name = substr($name, 0, -1);
        }

        return new self($name, substr($line, $emailStart, $emailEnd - $emailStart));
    }

    public function trimmed(): self
    {
        return new self(trim($this->name), trim($this->email));
    }

    public function storageBytes(): string
    {
        self::validateWriteToken($this->name);
        self::validateWriteToken($this->email);

        return "{$this->name} <{$this->email}>";
    }

    public function size(): int
    {
        return strlen($this->storageBytes());
    }

    private static function validateWriteToken(string $token): void
    {
        if (strpbrk($token, "<>\n") !== false) {
            throw new \InvalidArgumentException("Identity name and email must not contain '<', '>' or newline bytes");
        }
    }
}

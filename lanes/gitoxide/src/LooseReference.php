<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class LooseReference
{
    public function __construct(
        public readonly string $name,
        public readonly ReferenceTarget $target,
    ) {
        ReferenceName::assertValid($name);
    }

    public static function direct(string $name, string $oid, string $algorithm = 'sha1'): self
    {
        return new self($name, ReferenceTarget::object($oid, $algorithm));
    }

    public static function symbolic(string $name, string $targetName): self
    {
        return new self($name, ReferenceTarget::symbolic($targetName));
    }

    public static function parse(string $name, string $contents, string $algorithm = 'sha1'): self
    {
        ReferenceName::assertValid($name);

        if (str_starts_with($contents, 'ref: ')) {
            $target = substr($contents, 5);
            while (str_starts_with($target, ' ')) {
                $target = substr($target, 1);
            }

            $end = strcspn($target, "\r\n");
            return self::symbolic($name, substr($target, 0, $end));
        }

        $length = ReferenceTarget::hashHexLength($algorithm);
        $hex = substr($contents, 0, $length);
        if (strlen($hex) !== $length || preg_match('/^[0-9a-fA-F]+$/', $hex) !== 1) {
            throw new \InvalidArgumentException('Loose reference content could not be parsed');
        }

        $next = substr($contents, $length, 1);
        if ($next !== '' && ctype_xdigit($next)) {
            throw new \InvalidArgumentException('Loose reference contains trailing hexadecimal data after object id');
        }

        return self::direct($name, strtolower($hex), $algorithm);
    }

    public function kind(): string
    {
        return $this->target->kind;
    }

    public function storageBytes(): string
    {
        return $this->target->storageBytes();
    }
}

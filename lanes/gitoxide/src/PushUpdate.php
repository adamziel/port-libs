<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PushUpdate
{
    public const ZERO_OID = '0000000000000000000000000000000000000000';

    public readonly string $oldObject;
    public readonly string $newObject;
    public readonly string $refName;

    public function __construct(string $oldObject, string $newObject, string $refName)
    {
        $this->assertObjectId($oldObject);
        $this->assertObjectId($newObject);
        ReferenceName::assertValid($refName);

        $this->oldObject = strtolower($oldObject);
        $this->newObject = strtolower($newObject);
        $this->refName = $refName;
    }

    public static function create(string $newObject, string $refName): self
    {
        return new self(self::ZERO_OID, $newObject, $refName);
    }

    public static function update(string $oldObject, string $newObject, string $refName): self
    {
        return new self($oldObject, $newObject, $refName);
    }

    public static function delete(string $oldObject, string $refName): self
    {
        return new self($oldObject, self::ZERO_OID, $refName);
    }

    public function isCreate(): bool
    {
        return $this->oldObject === self::ZERO_OID && $this->newObject !== self::ZERO_OID;
    }

    public function isDelete(): bool
    {
        return $this->oldObject !== self::ZERO_OID && $this->newObject === self::ZERO_OID;
    }

    public function commandLine(): string
    {
        return "{$this->oldObject} {$this->newObject} {$this->refName}";
    }

    private function assertObjectId(string $oid): void
    {
        if (preg_match('/^[0-9a-fA-F]{40}$/', $oid) !== 1) {
            throw new \InvalidArgumentException('Push update object id must be a 40-character SHA-1 hex string');
        }
    }
}

<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PushUpdate
{
    public const ZERO_OID = '0000000000000000000000000000000000000000';

    public readonly string $oldObject;
    public readonly string $newObject;
    public readonly string $refName;
    private readonly string $objectFormat;

    public function __construct(string $oldObject, string $newObject, string $refName, string $objectFormat = 'sha1')
    {
        self::assertObjectFormat($objectFormat);
        $this->objectFormat = $objectFormat;
        $this->assertObjectId($oldObject);
        $this->assertObjectId($newObject);
        ReferenceName::assertValid($refName);

        $this->oldObject = strtolower($oldObject);
        $this->newObject = strtolower($newObject);
        $this->refName = $refName;
    }

    public static function create(string $newObject, string $refName, string $objectFormat = 'sha1'): self
    {
        return new self(self::zeroOid($objectFormat), $newObject, $refName, $objectFormat);
    }

    public static function update(string $oldObject, string $newObject, string $refName, string $objectFormat = 'sha1'): self
    {
        return new self($oldObject, $newObject, $refName, $objectFormat);
    }

    public static function delete(string $oldObject, string $refName, string $objectFormat = 'sha1'): self
    {
        return new self($oldObject, self::zeroOid($objectFormat), $refName, $objectFormat);
    }

    public function isCreate(): bool
    {
        $zeroOid = self::zeroOid($this->objectFormat);

        return $this->oldObject === $zeroOid && $this->newObject !== $zeroOid;
    }

    public function isDelete(): bool
    {
        $zeroOid = self::zeroOid($this->objectFormat);

        return $this->oldObject !== $zeroOid && $this->newObject === $zeroOid;
    }

    public function objectFormat(): string
    {
        return $this->objectFormat;
    }

    public function commandLine(): string
    {
        return "{$this->oldObject} {$this->newObject} {$this->refName}";
    }

    public static function zeroOid(string $objectFormat = 'sha1'): string
    {
        self::assertObjectFormat($objectFormat);

        return $objectFormat === 'sha256'
            ? str_repeat('0', 64)
            : self::ZERO_OID;
    }

    private function assertObjectId(string $oid): void
    {
        $length = $this->objectFormat === 'sha256' ? 64 : 40;
        $name = $this->objectFormat === 'sha256' ? 'SHA-256' : 'SHA-1';
        if (preg_match('/^[0-9a-fA-F]{' . $length . '}$/', $oid) !== 1) {
            throw new \InvalidArgumentException("Push update object id must be a {$length}-character {$name} hex string");
        }
    }

    private static function assertObjectFormat(string $objectFormat): void
    {
        if (!in_array($objectFormat, ['sha1', 'sha256'], true)) {
            throw new \InvalidArgumentException("Push update object format {$objectFormat} is not supported");
        }
    }
}

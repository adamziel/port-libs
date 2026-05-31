<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PushRefStatus
{
    public const OK = 'ok';
    public const REJECTED = 'ng';

    public function __construct(
        public readonly string $status,
        public readonly string $refName,
        public readonly ?string $message = null,
        public readonly ?string $reportedRefName = null,
        public readonly ?string $oldObject = null,
        public readonly ?string $newObject = null,
        public readonly bool $forcedUpdate = false,
    ) {
        if ($status !== self::OK && $status !== self::REJECTED) {
            throw new \InvalidArgumentException("push response: unsupported ref status {$status}");
        }
        ReferenceName::assertValid($refName);
        if ($reportedRefName !== null) {
            ReferenceName::assertValid($reportedRefName);
        }
        if ($oldObject !== null) {
            self::assertObjectId($oldObject);
        }
        if ($newObject !== null) {
            self::assertObjectId($newObject);
        }
        if ($status === self::OK && $message !== null) {
            throw new \InvalidArgumentException('push response: successful ref status cannot have an error message');
        }
        if ($status === self::REJECTED && ($message === null || $message === '')) {
            throw new \InvalidArgumentException('push response: rejected ref status requires an error message');
        }
    }

    public static function ok(string $refName): self
    {
        return new self(self::OK, $refName);
    }

    public static function rejected(string $refName, string $message): self
    {
        return new self(self::REJECTED, $refName, $message);
    }

    public function isOk(): bool
    {
        return $this->status === self::OK;
    }

    public function isRejected(): bool
    {
        return $this->status === self::REJECTED;
    }

    public function effectiveRefName(): string
    {
        return $this->reportedRefName ?? $this->refName;
    }

    public function withOption(string $name, ?string $value = null): self
    {
        if (!$this->isOk()) {
            throw new \InvalidArgumentException('push response: report-status-v2 options require a successful ref status');
        }

        if ($name === 'refname') {
            if ($this->reportedRefName !== null) {
                throw new \InvalidArgumentException('push response: duplicate refname option');
            }
            if ($value === null) {
                throw new \InvalidArgumentException('push response: refname option requires a value');
            }

            return new self($this->status, $this->refName, null, $value, $this->oldObject, $this->newObject, $this->forcedUpdate);
        }
        if ($name === 'old-oid') {
            if ($this->oldObject !== null) {
                throw new \InvalidArgumentException('push response: duplicate old-oid option');
            }
            if ($value === null) {
                throw new \InvalidArgumentException('push response: old-oid option requires a value');
            }

            return new self($this->status, $this->refName, null, $this->reportedRefName, strtolower($value), $this->newObject, $this->forcedUpdate);
        }
        if ($name === 'new-oid') {
            if ($this->newObject !== null) {
                throw new \InvalidArgumentException('push response: duplicate new-oid option');
            }
            if ($value === null) {
                throw new \InvalidArgumentException('push response: new-oid option requires a value');
            }

            return new self($this->status, $this->refName, null, $this->reportedRefName, $this->oldObject, strtolower($value), $this->forcedUpdate);
        }
        if ($name === 'forced-update') {
            if ($value !== null) {
                throw new \InvalidArgumentException('push response: forced-update option does not take a value');
            }
            if ($this->forcedUpdate) {
                throw new \InvalidArgumentException('push response: duplicate forced-update option');
            }

            return new self($this->status, $this->refName, null, $this->reportedRefName, $this->oldObject, $this->newObject, true);
        }

        throw new \InvalidArgumentException("push response: unknown report-status-v2 option {$name}");
    }

    private static function assertObjectId(string $oid): void
    {
        if (preg_match('/^(?:[0-9a-fA-F]{40}|[0-9a-fA-F]{64})$/', $oid) !== 1) {
            throw new \InvalidArgumentException('push response: option object id must be a 40- or 64-character hex object id');
        }
    }
}

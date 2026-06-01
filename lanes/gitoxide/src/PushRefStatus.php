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
        public readonly bool $fallThrough = false,
        private readonly bool $reportOptionSeen = false,
    ) {
        if ($status !== self::OK && $status !== self::REJECTED) {
            throw new \InvalidArgumentException("push response: unsupported ref status {$status}");
        }
        ReferenceName::assertValid($refName);
        if ($reportedRefName !== null && $reportedRefName !== '') {
            ReferenceName::assertValid($reportedRefName);
        }
        if ($oldObject !== null) {
            self::assertObjectId($oldObject);
        }
        if ($newObject !== null) {
            self::assertObjectId($newObject);
        }
        if ($status === self::REJECTED && $message === null) {
            throw new \InvalidArgumentException('push response: rejected ref status requires an error message');
        }
    }

    public static function ok(string $refName, ?string $message = null): self
    {
        return new self(self::OK, $refName, $message);
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

    public function hasReportOption(): bool
    {
        return $this->reportOptionSeen;
    }

    public function asRejected(string $message): self
    {
        return new self(
            self::REJECTED,
            $this->refName,
            $message,
            $this->reportedRefName,
            $this->oldObject,
            $this->newObject,
            $this->forcedUpdate,
            $this->fallThrough,
            $this->reportOptionSeen
        );
    }

    public function withOption(string $name, ?string $value = null, string $objectFormat = 'any'): self
    {
        if (!$this->isOk()) {
            throw new \InvalidArgumentException('push response: report-status-v2 options require a successful ref status');
        }

        if ($name === 'refname') {
            if ($value === null) {
                return new self($this->status, $this->refName, $this->message, $this->reportedRefName, $this->oldObject, $this->newObject, $this->forcedUpdate, $this->fallThrough, true);
            }

            return new self($this->status, $this->refName, $this->message, $value, $this->oldObject, $this->newObject, $this->forcedUpdate, $this->fallThrough, true);
        }
        if ($name === 'old-oid') {
            if ($value === null || $value === '') {
                return new self($this->status, $this->refName, $this->message, $this->reportedRefName, $this->oldObject, $this->newObject, $this->forcedUpdate, $this->fallThrough, true);
            }

            $objectId = self::parseObjectIdOption($value, $objectFormat);
            if ($objectId === null) {
                return new self($this->status, $this->refName, $this->message, $this->reportedRefName, $this->oldObject, $this->newObject, $this->forcedUpdate, $this->fallThrough, true);
            }

            return new self($this->status, $this->refName, $this->message, $this->reportedRefName, $objectId, $this->newObject, $this->forcedUpdate, $this->fallThrough, true);
        }
        if ($name === 'new-oid') {
            if ($value === null || $value === '') {
                return new self($this->status, $this->refName, $this->message, $this->reportedRefName, $this->oldObject, $this->newObject, $this->forcedUpdate, $this->fallThrough, true);
            }

            $objectId = self::parseObjectIdOption($value, $objectFormat);
            if ($objectId === null) {
                return new self($this->status, $this->refName, $this->message, $this->reportedRefName, $this->oldObject, $this->newObject, $this->forcedUpdate, $this->fallThrough, true);
            }

            return new self($this->status, $this->refName, $this->message, $this->reportedRefName, $this->oldObject, $objectId, $this->forcedUpdate, $this->fallThrough, true);
        }
        if ($name === 'forced-update') {
            return new self($this->status, $this->refName, $this->message, $this->reportedRefName, $this->oldObject, $this->newObject, true, $this->fallThrough, true);
        }
        if ($name === 'fall-through') {
            if ($value !== null) {
                throw new \InvalidArgumentException('push response: fall-through option does not take a value');
            }
            if ($this->fallThrough) {
                throw new \InvalidArgumentException('push response: duplicate fall-through option');
            }

            return new self($this->status, $this->refName, $this->message, $this->reportedRefName, $this->oldObject, $this->newObject, $this->forcedUpdate, true, true);
        }

        return new self($this->status, $this->refName, $this->message, $this->reportedRefName, $this->oldObject, $this->newObject, $this->forcedUpdate, $this->fallThrough, true);
    }

    private static function parseObjectIdOption(string $value, string $objectFormat): ?string
    {
        if ($objectFormat === 'sha1') {
            return self::parseFixedObjectIdPrefix($value, 40);
        }
        if ($objectFormat === 'sha256') {
            return self::parseFixedObjectIdPrefix($value, 64);
        }

        foreach ([64, 40] as $length) {
            if (preg_match('/^([0-9a-fA-F]{' . $length . '})(?:$|[^0-9a-fA-F])/', $value, $matches) === 1) {
                return strtolower($matches[1]);
            }
        }

        return null;
    }

    private static function parseFixedObjectIdPrefix(string $value, int $length): ?string
    {
        if (preg_match('/^[0-9a-fA-F]{' . $length . '}/', $value) !== 1) {
            return null;
        }

        return strtolower(substr($value, 0, $length));
    }

    private static function assertObjectId(string $oid): void
    {
        if (preg_match('/^(?:[0-9a-fA-F]{40}|[0-9a-fA-F]{64})$/', $oid) !== 1) {
            throw new \InvalidArgumentException('push response: option object id must be a 40- or 64-character hex object id');
        }
    }
}

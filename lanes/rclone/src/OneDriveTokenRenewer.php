<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

/**
 * Deterministic model of rclone oauthutil.Renew as wired by OneDrive.
 *
 * The real Go helper listens for token expiry in a goroutine and invokes a
 * provider transaction only while uploads are active. This PHP port keeps the
 * same decisions explicit and testable without timers, OAuth tokens, or live
 * provider metadata reads.
 */
final class OneDriveTokenRenewer
{
    private int $activeUploads = 0;
    private bool $shutdown = false;
    private int $expirySignals = 0;
    private bool $armedForNextExpiry = true;

    /** @var list<string> */
    private array $events = [];

    /**
     * @param \Closure(): void $refreshRootMetadata
     */
    public function __construct(
        private readonly string $name,
        private readonly \Closure $refreshRootMetadata,
    ) {
    }

    public function startUpload(): void
    {
        if ($this->shutdown) {
            $this->events[] = 'start-ignored-after-shutdown';
            return;
        }

        ++$this->activeUploads;
        $this->events[] = 'upload-started';
    }

    public function stopUpload(): void
    {
        if ($this->activeUploads > 0) {
            --$this->activeUploads;
        }

        $this->events[] = 'upload-stopped';
    }

    /**
     * @return array{refreshed: bool, error: ?string, activeUploads: int}
     */
    public function expire(): array
    {
        if ($this->shutdown) {
            $this->events[] = 'expiry-ignored-after-shutdown';
            return $this->result(false);
        }

        ++$this->expirySignals;
        if ($this->activeUploads === 0) {
            $this->events[] = 'expiry-no-active-upload';
            $this->events[] = 'expiry-rearmed';
            return $this->result(false);
        }

        $this->events[] = 'expiry-refresh-started';
        try {
            ($this->refreshRootMetadata)();
            $this->events[] = 'expiry-refresh-ok';
            $this->events[] = 'expiry-rearmed';
            return $this->result(true);
        } catch (\Throwable $exception) {
            $this->events[] = 'expiry-refresh-error';
            $this->events[] = 'expiry-rearmed';
            return $this->result(true, $exception->getMessage());
        }
    }

    public function shutdown(): void
    {
        if ($this->shutdown) {
            return;
        }

        $this->shutdown = true;
        $this->armedForNextExpiry = false;
        $this->events[] = 'shutdown';
    }

    public function activeUploads(): int
    {
        return $this->activeUploads;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function expirySignals(): int
    {
        return $this->expirySignals;
    }

    public function isArmedForNextExpiry(): bool
    {
        return $this->armedForNextExpiry;
    }

    /**
     * @return list<string>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * @return array{refreshed: bool, error: ?string, activeUploads: int}
     */
    private function result(bool $refreshed, ?string $error = null): array
    {
        return [
            'refreshed' => $refreshed,
            'error' => $error,
            'activeUploads' => $this->activeUploads,
        ];
    }
}

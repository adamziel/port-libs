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
        private readonly bool $hasExpirySource = true,
    ) {
        if (!$this->hasExpirySource) {
            $this->armedForNextExpiry = false;
            $this->events[] = 'watchdog-not-started-no-expiry-source';
        }
    }

    public function startUpload(): void
    {
        if ($this->shutdown) {
            $this->events[] = 'start-ignored-after-shutdown';
            return;
        }

        $this->armedForNextExpiry = $this->hasExpirySource;
        ++$this->activeUploads;
        $this->events[] = 'upload-started';
    }

    public function stopUpload(): void
    {
        --$this->activeUploads;
        $this->events[] = 'upload-stopped';
    }

    /**
     * Model OneDrive upload call sites that bracket Put/Update with
     * oauthutil.Renew Start/Stop and defer Stop even when upload work fails.
     *
     * @template T
     * @param \Closure(): T $upload
     * @return T
     */
    public function duringUpload(\Closure $upload): mixed
    {
        $activeBeforeStart = $this->activeUploads;
        $this->startUpload();
        $started = $this->activeUploads !== $activeBeforeStart;

        try {
            return $upload();
        } finally {
            if ($started) {
                $this->stopUpload();
            }
        }
    }

    /**
     * @return array{refreshed: bool, error: ?string, activeUploads: int}
     */
    public function expire(): array
    {
        if (!$this->hasExpirySource) {
            $this->events[] = 'expiry-ignored-no-expiry-source';
            return $this->result(false);
        }

        if ($this->shutdown) {
            $this->events[] = 'expiry-ignored-after-shutdown';
            return $this->result(false);
        }

        ++$this->expirySignals;
        if ($this->activeUploads <= 0) {
            $this->armedForNextExpiry = true;
            $this->events[] = 'expiry-no-active-upload';
            $this->events[] = 'expiry-rearmed';
            return $this->result(false);
        }

        $this->events[] = 'expiry-refresh-started';
        try {
            ($this->refreshRootMetadata)();
            $this->armedForNextExpiry = true;
            $this->events[] = 'expiry-refresh-ok';
            $this->events[] = 'expiry-rearmed';
            return $this->result(true);
        } catch (\Throwable $exception) {
            $this->armedForNextExpiry = true;
            $this->events[] = 'expiry-refresh-error';
            $this->events[] = 'expiry-rearmed';
            return $this->result(true, $exception->getMessage());
        }
    }

    /**
     * Model one receive from oauthutil.Renew's expiry-channel watcher.
     *
     * @return array{refreshed: bool, error: ?string, activeUploads: int, running: bool}
     */
    public function watchdogCycle(bool $expiryChannelOpen = true): array
    {
        if (!$this->hasExpirySource) {
            $this->events[] = 'watchdog-not-running-no-expiry-source';
            return $this->watchdogResult($this->result(false), false);
        }

        if (!$expiryChannelOpen) {
            $this->shutdown = true;
            $this->armedForNextExpiry = false;
            $this->events[] = 'watchdog-expiry-channel-closed';
            return $this->watchdogResult($this->result(false), false);
        }

        $result = $this->expire();

        return $this->watchdogResult($result, !$this->shutdown);
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

    public function isShutdown(): bool
    {
        return $this->shutdown;
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

    /**
     * @param array{refreshed: bool, error: ?string, activeUploads: int} $result
     * @return array{refreshed: bool, error: ?string, activeUploads: int, running: bool}
     */
    private function watchdogResult(array $result, bool $running): array
    {
        return $result + ['running' => $running];
    }
}

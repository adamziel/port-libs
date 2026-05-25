<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

/**
 * Deterministic model of OneDrive provider shutdown lifecycle wiring.
 *
 * Upstream exposes provider cleanup through feature hooks: the backend shuts
 * down token renewal and change notification without doing provider I/O. This
 * helper keeps those effects explicit for local tests.
 */
final class OneDriveProviderLifecycle
{
    private bool $changeNotifyRunning = false;
    private bool $shutdown = false;

    /** @var list<string> */
    private array $events = [];

    public function __construct(
        private readonly OneDriveTokenRenewer $tokenRenewer,
        private readonly bool $changeNotifySupported = true,
    ) {
    }

    public function startChangeNotify(bool $featureMasked = false): void
    {
        if ($featureMasked) {
            $this->events[] = 'change-notify-masked';
            return;
        }

        if (!$this->changeNotifySupported) {
            $this->events[] = 'change-notify-unsupported';
            return;
        }

        if ($this->shutdown) {
            $this->events[] = 'change-notify-start-ignored-after-shutdown';
            return;
        }

        if ($this->changeNotifyRunning) {
            $this->events[] = 'change-notify-already-running';
            return;
        }

        $this->changeNotifyRunning = true;
        $this->events[] = 'change-notify-started';
    }

    /**
     * @return array{tokenRenewerShutdown: bool, changeNotifyStopped: bool, alreadyShutdown: bool}
     */
    public function shutdown(): array
    {
        if ($this->shutdown) {
            $this->events[] = 'shutdown-ignored-already-closed';

            return [
                'tokenRenewerShutdown' => false,
                'changeNotifyStopped' => false,
                'alreadyShutdown' => true,
            ];
        }

        $this->shutdown = true;
        $changeNotifyStopped = false;
        if ($this->changeNotifyRunning) {
            $this->changeNotifyRunning = false;
            $changeNotifyStopped = true;
            $this->events[] = 'change-notify-stopped';
        }

        $this->tokenRenewer->shutdown();
        $this->events[] = 'token-renewer-shutdown';
        $this->events[] = 'provider-shutdown';

        return [
            'tokenRenewerShutdown' => true,
            'changeNotifyStopped' => $changeNotifyStopped,
            'alreadyShutdown' => false,
        ];
    }

    public function isShutdown(): bool
    {
        return $this->shutdown;
    }

    public function isChangeNotifyRunning(): bool
    {
        return $this->changeNotifyRunning;
    }

    /**
     * @return list<string>
     */
    public function events(): array
    {
        return $this->events;
    }
}

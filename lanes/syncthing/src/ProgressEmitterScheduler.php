<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class ProgressEmitterScheduler
{
    /**
     * @var array<string, ProgressConnection>
     */
    private array $connections = [];

    private int $lastUpdated = 0;
    private int $lastCount = 0;

    public function __construct(
        private readonly ProgressEmitter $emitter,
        private readonly int $intervalSeconds = 1,
        private readonly bool $stopOnSendFailure = true,
    ) {
        if ($this->intervalSeconds <= 0) {
            throw new \InvalidArgumentException('Progress update interval must be positive');
        }
    }

    /**
     * @param list<string> $folders
     */
    public function subscribe(ProgressConnection $connection, array $folders): void
    {
        $this->connections[$connection->deviceId()] = $connection;
        $this->emitter->temporaryIndexSubscribe($connection->deviceId(), $folders);
    }

    public function unsubscribe(string $deviceId): void
    {
        unset($this->connections[$deviceId]);
        $this->emitter->temporaryIndexUnsubscribe($deviceId);
    }

    public function tick(): ProgressEmitterTickResult
    {
        if ($this->emitter->isDisabled()) {
            return new ProgressEmitterTickResult(false, [], [], [], [], null);
        }

        $revision = $this->emitter->progressRevision();
        $nextInterval = $revision['count'] === 0 ? null : $this->intervalSeconds;
        $changed = $revision['latestUpdated'] !== $this->lastUpdated || $revision['count'] !== $this->lastCount;
        if (!$changed) {
            return new ProgressEmitterTickResult(false, [], [], [], [], $nextInterval);
        }

        $this->lastUpdated = $revision['latestUpdated'];
        $this->lastCount = $revision['count'];

        $event = $this->emitter->downloadProgressEvent();
        $batches = $this->emitter->computeProgressUpdates();
        $sent = [];
        $failures = [];

        foreach ($batches as $batch) {
            $connection = $this->connections[$batch->deviceId] ?? null;
            if ($connection === null) {
                $failures[] = new ProgressSendFailure(
                    $batch->deviceId,
                    $batch->folder,
                    new \RuntimeException('No progress connection registered for device'),
                );
                if ($this->stopOnSendFailure) {
                    break;
                }
                continue;
            }

            try {
                $connection->sendDownloadProgress($batch->toDownloadProgress());
                $sent[] = $batch;
            } catch (\Throwable $throwable) {
                $failures[] = new ProgressSendFailure($batch->deviceId, $batch->folder, $throwable);
                if ($this->stopOnSendFailure) {
                    break;
                }
            }
        }

        return new ProgressEmitterTickResult($changed, $event, $batches, $sent, $failures, $nextInterval);
    }
}

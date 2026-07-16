<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class SqliteCheckpointStore implements FolderScanCheckpointRepository
{
    public const DEFAULT_TABLE = 'syncthing_folder_scan_checkpoints';

    public function __construct(
        private readonly \PDO $pdo,
        private readonly string $tableName = self::DEFAULT_TABLE,
    ) {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $this->tableName) !== 1) {
            throw new \InvalidArgumentException('SQLite checkpoint table name must be a simple identifier');
        }

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        $this->createSchema();
    }

    public static function open(string $path, string $tableName = self::DEFAULT_TABLE): self
    {
        if ($path === '') {
            throw new \InvalidArgumentException('SQLite checkpoint path must not be empty');
        }
        $dir = dirname($path);
        if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
            throw new \InvalidArgumentException('SQLite checkpoint directory does not exist: ' . $dir);
        }

        return new self(new \PDO('sqlite:' . $path), $tableName);
    }

    public static function inMemory(string $tableName = self::DEFAULT_TABLE): self
    {
        return new self(new \PDO('sqlite::memory:'), $tableName);
    }

    public function load(string $folderId, ?int $now = null): ?FolderScanCheckpointSnapshot
    {
        self::assertFolderId($folderId);
        $now ??= time();
        self::assertClock($now);

        return $this->readSnapshot($folderId, $now, deleteExpired: true);
    }

    public function save(
        FolderScanCheckpoint $checkpoint,
        ?int $expectedRevision = null,
        ?int $now = null,
        ?int $ttlSeconds = null,
    ): FolderScanCheckpointSnapshot {
        $now ??= time();
        self::assertClock($now);
        self::assertExpectedRevision($expectedRevision);
        self::assertTtl($ttlSeconds);

        return $this->transactional(function () use ($checkpoint, $expectedRevision, $now, $ttlSeconds): FolderScanCheckpointSnapshot {
            $folderId = $checkpoint->folderId();
            $current = $this->readSnapshot($folderId, $now, deleteExpired: true);
            $currentRevision = $current?->revision ?? 0;
            if ($expectedRevision !== null && $expectedRevision !== $currentRevision) {
                throw self::conflict($folderId, $expectedRevision, $currentRevision);
            }

            $snapshot = new FolderScanCheckpointSnapshot(
                $checkpoint,
                $currentRevision + 1,
                $now,
                $ttlSeconds === null ? $current?->expiresAt : $now + $ttlSeconds,
            );
            $this->writeSnapshot($snapshot, $currentRevision);

            return $snapshot;
        });
    }

    public function mergeResult(
        string $folderId,
        FileInfoScanResult $result,
        ?int $expectedRevision = null,
        ?int $now = null,
        ?int $ttlSeconds = null,
    ): FolderScanCheckpointSnapshot {
        self::assertFolderId($folderId);
        $now ??= time();
        self::assertClock($now);
        self::assertExpectedRevision($expectedRevision);
        self::assertTtl($ttlSeconds);

        return $this->transactional(function () use ($folderId, $result, $expectedRevision, $now, $ttlSeconds): FolderScanCheckpointSnapshot {
            $current = $this->readSnapshot($folderId, $now, deleteExpired: true);
            $currentRevision = $current?->revision ?? 0;
            if ($expectedRevision !== null && $expectedRevision !== $currentRevision) {
                throw self::conflict($folderId, $expectedRevision, $currentRevision);
            }

            $checkpoint = $current === null
                ? FolderScanCheckpoint::fromResult($folderId, $result)
                : $current->checkpoint->withResult($result);
            $snapshot = new FolderScanCheckpointSnapshot(
                $checkpoint,
                $currentRevision + 1,
                $now,
                $ttlSeconds === null ? $current?->expiresAt : $now + $ttlSeconds,
            );
            $this->writeSnapshot($snapshot, $currentRevision);

            return $snapshot;
        });
    }

    public function delete(string $folderId, ?int $expectedRevision = null, ?int $now = null): bool
    {
        self::assertFolderId($folderId);
        $now ??= time();
        self::assertClock($now);
        self::assertExpectedRevision($expectedRevision);

        return $this->transactional(function () use ($folderId, $expectedRevision, $now): bool {
            $current = $this->readSnapshot($folderId, $now, deleteExpired: true);
            $currentRevision = $current?->revision ?? 0;
            if ($expectedRevision !== null && $expectedRevision !== $currentRevision) {
                throw self::conflict($folderId, $expectedRevision, $currentRevision);
            }
            if ($current === null) {
                return false;
            }

            $statement = $this->pdo->prepare(
                'DELETE FROM ' . $this->tableName . ' WHERE folder_id = :folder_id AND revision = :revision',
            );
            $statement->execute([
                ':folder_id' => $folderId,
                ':revision' => $current->revision,
            ]);

            return $statement->rowCount() === 1;
        });
    }

    public function forgetExpired(?int $now = null): int
    {
        $now ??= time();
        self::assertClock($now);

        $statement = $this->pdo->prepare(
            'DELETE FROM ' . $this->tableName . ' WHERE expires_at IS NOT NULL AND expires_at <= :now',
        );
        $statement->execute([':now' => $now]);

        return $statement->rowCount();
    }

    /**
     * @return list<FolderScanCheckpointSnapshot>
     */
    public function snapshots(?int $now = null): array
    {
        $now ??= time();
        self::assertClock($now);
        $this->forgetExpired($now);

        $statement = $this->pdo->query(
            'SELECT folder_id, revision, updated_at, expires_at, payload FROM ' . $this->tableName . ' ORDER BY folder_id ASC',
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to query SQLite checkpoint snapshots');
        }

        $snapshots = [];
        foreach ($statement->fetchAll() as $row) {
            $snapshots[] = $this->snapshotFromRow($row);
        }

        return $snapshots;
    }

    private function createSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ' . $this->tableName . ' ('
            . 'folder_id TEXT PRIMARY KEY NOT NULL, '
            . 'revision INTEGER NOT NULL CHECK (revision > 0), '
            . 'updated_at INTEGER NOT NULL CHECK (updated_at >= 0), '
            . 'expires_at INTEGER NULL CHECK (expires_at IS NULL OR expires_at >= updated_at), '
            . 'payload TEXT NOT NULL'
            . ')',
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS ' . $this->tableName . '_expires_at_idx '
            . 'ON ' . $this->tableName . ' (expires_at)',
        );
    }

    private function readSnapshot(string $folderId, int $now, bool $deleteExpired): ?FolderScanCheckpointSnapshot
    {
        $statement = $this->pdo->prepare(
            'SELECT folder_id, revision, updated_at, expires_at, payload FROM ' . $this->tableName . ' WHERE folder_id = :folder_id',
        );
        $statement->execute([':folder_id' => $folderId]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        $snapshot = $this->snapshotFromRow($row);
        if ($snapshot->folderId() !== $folderId) {
            throw new \RuntimeException('SQLite checkpoint row folder mismatch for ' . $folderId);
        }
        if ($snapshot->isExpired($now)) {
            if ($deleteExpired) {
                $this->deleteRow($folderId, $snapshot->revision);
            }

            return null;
        }

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function snapshotFromRow(array $row): FolderScanCheckpointSnapshot
    {
        $payloadText = $row['payload'] ?? null;
        if (!is_string($payloadText)) {
            throw new \RuntimeException('SQLite checkpoint payload column must be text');
        }

        try {
            $payload = json_decode($payloadText, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('SQLite checkpoint payload is not valid JSON', 0, $exception);
        }

        $snapshot = FolderScanCheckpointPayloadCodec::snapshotFromPayload($payload, 'SQLite checkpoint');
        $revision = self::rowInt($row, 'revision');
        $updatedAt = self::rowInt($row, 'updated_at');
        $expiresAt = self::rowNullableInt($row, 'expires_at');
        if ($snapshot->revision !== $revision || $snapshot->updatedAt !== $updatedAt || $snapshot->expiresAt !== $expiresAt) {
            throw new \RuntimeException('SQLite checkpoint row metadata does not match payload for ' . $snapshot->folderId());
        }

        return $snapshot;
    }

    private function writeSnapshot(FolderScanCheckpointSnapshot $snapshot, int $previousRevision): void
    {
        $payload = json_encode(
            FolderScanCheckpointPayloadCodec::snapshotToPayload($snapshot),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        if ($previousRevision === 0) {
            $statement = $this->pdo->prepare(
                'INSERT INTO ' . $this->tableName
                . ' (folder_id, revision, updated_at, expires_at, payload)'
                . ' VALUES (:folder_id, :revision, :updated_at, :expires_at, :payload)',
            );
            try {
                $statement->execute($this->snapshotParams($snapshot, $payload));
            } catch (\PDOException $exception) {
                $actualRevision = $this->readSnapshot($snapshot->folderId(), $snapshot->updatedAt, deleteExpired: false)?->revision ?? 0;
                throw self::conflict($snapshot->folderId(), $previousRevision, $actualRevision);
            }

            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE ' . $this->tableName
            . ' SET revision = :revision, updated_at = :updated_at, expires_at = :expires_at, payload = :payload'
            . ' WHERE folder_id = :folder_id AND revision = :previous_revision',
        );
        $params = $this->snapshotParams($snapshot, $payload);
        $params[':previous_revision'] = $previousRevision;
        $statement->execute($params);
        if ($statement->rowCount() === 1) {
            return;
        }

        $actualRevision = $this->readSnapshot($snapshot->folderId(), $snapshot->updatedAt, deleteExpired: false)?->revision ?? 0;
        throw self::conflict($snapshot->folderId(), $previousRevision, $actualRevision);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function snapshotParams(FolderScanCheckpointSnapshot $snapshot, string $payload): array
    {
        return [
            ':folder_id' => $snapshot->folderId(),
            ':revision' => $snapshot->revision,
            ':updated_at' => $snapshot->updatedAt,
            ':expires_at' => $snapshot->expiresAt,
            ':payload' => $payload,
        ];
    }

    private function deleteRow(string $folderId, int $revision): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM ' . $this->tableName . ' WHERE folder_id = :folder_id AND revision = :revision',
        );
        $statement->execute([
            ':folder_id' => $folderId,
            ':revision' => $revision,
        ]);
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function transactional(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $callback();
        }

        $this->pdo->beginTransaction();
        try {
            $result = $callback();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $throwable;
        }
    }

    private static function assertFolderId(string $folderId): void
    {
        if ($folderId === '') {
            throw new \InvalidArgumentException('SQLite checkpoint store requires a folder ID');
        }
    }

    private static function assertClock(int $now): void
    {
        if ($now < 0) {
            throw new \InvalidArgumentException('SQLite checkpoint store clock must not be negative');
        }
    }

    private static function assertExpectedRevision(?int $expectedRevision): void
    {
        if ($expectedRevision !== null && $expectedRevision < 0) {
            throw new \InvalidArgumentException('SQLite checkpoint expected revision must not be negative');
        }
    }

    private static function assertTtl(?int $ttlSeconds): void
    {
        if ($ttlSeconds !== null && $ttlSeconds < 0) {
            throw new \InvalidArgumentException('SQLite checkpoint TTL must not be negative');
        }
    }

    private static function conflict(string $folderId, int $expectedRevision, int $actualRevision): FolderScanCheckpointConflictException
    {
        return new FolderScanCheckpointConflictException(
            sprintf(
                'SQLite checkpoint revision conflict for %s: expected %d, actual %d',
                $folderId,
                $expectedRevision,
                $actualRevision,
            ),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function rowInt(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \RuntimeException('SQLite checkpoint row field must be an integer: ' . $key);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function rowNullableInt(array $row, string $key): ?int
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return null;
        }

        return self::rowInt($row, $key);
    }
}

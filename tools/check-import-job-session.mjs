#!/usr/bin/env node

import assert from 'node:assert/strict';
import {
  cancelImportMutationDurably,
  createImportJobSession,
  createPlaygroundPersistence,
  recoverImportMutation,
} from '../pandoc-showcase/import-job-session.mjs';

class MemoryStorage {
  constructor() { this.values = new Map(); }
  getItem(key) { return this.values.has(key) ? this.values.get(key) : null; }
  setItem(key, value) { this.values.set(key, String(value)); }
  removeItem(key) { this.values.delete(key); }
}

const storage = new MemoryStorage();
let now = 1_000_000;
const session = createImportJobSession({
  storage,
  storageKey: 'active',
  maxAgeMs: 5_000,
  now: () => now,
});

session.remember({ jobId: 'job_123456789', status: 'converting' });
assert.deepEqual(session.load(), {
  version: 1,
  jobId: 'job_123456789',
  status: 'converting',
  cancellationRequested: false,
  updatedAt: now,
});
session.requestCancellation('job_123456789');
assert.equal(session.load()?.cancellationRequested, true, 'Cancellation intent must survive a reload.');
session.remember({ jobId: 'job_123456789', status: 'ready_to_convert' });
assert.equal(session.load()?.cancellationRequested, true, 'A later active snapshot must not clear cancellation intent.');
session.remember({ jobId: 'job_123456789', status: 'complete' });
assert.equal(session.load(), null, 'A terminal import must clear its resume pointer.');
session.remember({ jobId: 'job_123456789', status: 'converting' });
assert.equal(session.load(), null, 'A stale in-flight poll must not resurrect a completed import pointer.');

const cancelledSession = createImportJobSession({
  storage: new MemoryStorage(),
  storageKey: 'cancelled',
  now: () => now,
});
cancelledSession.remember({ jobId: 'job_cancelled123', status: 'converting' });
cancelledSession.remember({ jobId: 'job_cancelled123', status: 'cancelled' });
assert.equal(cancelledSession.load(), null, 'A cancelled import must not be offered for resume.');

session.remember({ jobId: 'job_987654321', status: 'queued' });
now += 5_001;
assert.equal(session.load(), null, 'An expired pointer must not offer a stale resume action.');

let mutationCalls = 0;
let statusCalls = 0;
const recoveredWithoutReplay = await recoverImportMutation({
  async mutate() {
    mutationCalls += 1;
    throw new Error('response lost');
  },
  async readStatus() {
    statusCalls += 1;
    return statusCalls < 3
      ? { jobId: 'job_123456789', status: 'converting' }
      : { jobId: 'job_123456789', status: 'ready_to_convert' };
  },
  delay: async () => {},
});
assert.equal(recoveredWithoutReplay.status, 'ready_to_convert');
assert.equal(mutationCalls, 1, 'A committed mutation must not be replayed while status is progressing.');
assert.equal(statusCalls, 3);

mutationCalls = 0;
statusCalls = 0;
const replayedAfterBoundedChecks = await recoverImportMutation({
  async mutate() {
    mutationCalls += 1;
    if (mutationCalls === 1) throw new Error('worker disappeared');
    return { jobId: 'job_123456789', status: 'ready_to_convert' };
  },
  async readStatus() {
    statusCalls += 1;
    return { jobId: 'job_123456789', status: 'converting' };
  },
  maxMutationRetries: 1,
  statusChecksPerRetry: 2,
  delay: async () => {},
});
assert.equal(replayedAfterBoundedChecks.status, 'ready_to_convert');
assert.equal(statusCalls, 2);
assert.equal(mutationCalls, 2, 'A stuck worker may be retried only after bounded status checks.');

let cancellationRequested = false;
mutationCalls = 0;
statusCalls = 0;
let cancelCalls = 0;
const cancelledAfterUncertainMutation = await recoverImportMutation({
  async mutate() {
    mutationCalls += 1;
    cancellationRequested = true;
    throw new Error('advance response lost while the user cancelled');
  },
  async readStatus() {
    statusCalls += 1;
    return { jobId: 'job_123456789', status: 'converting' };
  },
  shouldCancel: () => cancellationRequested,
  async cancel() {
    cancelCalls += 1;
    return { jobId: 'job_123456789', status: 'cancelled' };
  },
  delay: async () => {},
});
assert.equal(cancelledAfterUncertainMutation.status, 'cancelled');
assert.equal(mutationCalls, 1, 'Cancellation during an uncertain response must not replay /advance.');
assert.equal(statusCalls, 0, 'Cancellation intent must take precedence over advance-recovery polling.');
assert.equal(cancelCalls, 1, 'The next mutation after an uncertain /advance must be /cancel.');

const cancellationOrder = [];
cancelCalls = 0;
statusCalls = 0;
const cancelledAfterLockCollision = await cancelImportMutationDurably({
  async cancel() {
    cancelCalls += 1;
    cancellationOrder.push('cancel');
    if (cancelCalls === 1) {
      const collision = new Error('Import request failed (409).');
      collision.status = 409;
      throw collision;
    }
    return { jobId: 'job_123456789', status: 'cancelled' };
  },
  async readStatus() {
    statusCalls += 1;
    cancellationOrder.push('status');
    return { jobId: 'job_123456789', status: 'converting' };
  },
  delay: async () => {},
});
assert.equal(cancelledAfterLockCollision.status, 'cancelled');
assert.deepEqual(cancellationOrder, ['cancel', 'status', 'cancel']);
assert.equal(cancelCalls, 2, 'A 409 lock collision must retry /cancel after observing active status.');
assert.equal(statusCalls, 1);

await assert.rejects(
  cancelImportMutationDurably({
    async cancel() {
      const forbidden = new Error('Cancellation is not permitted.');
      forbidden.status = 403;
      throw forbidden;
    },
    async readStatus() {
      throw new Error('A permanent cancellation failure must not be polled.');
    },
    delay: async () => {},
  }),
  /not permitted/,
  'Permanent client errors must preserve the saved intent without spinning forever.',
);

const persistenceStorage = new MemoryStorage();
const persistence = createPlaygroundPersistence({
  storage: persistenceStorage,
  storageKey: 'playground',
  devicePath: 'fixture/import-site',
});
const baseOptions = { iframe: {}, remoteUrl: 'https://example.test/remote.html' };
assert.strictEqual(persistence.startOptions(baseOptions), baseOptions);
let mounted = null;
await persistence.persist({
  async mountOpfs(descriptor) { mounted = descriptor; },
});
assert.equal(mounted.initialSyncDirection, 'memfs-to-opfs');
const restored = persistence.startOptions(baseOptions);
assert.equal(restored.shouldInstallWordPress, false);
assert.equal(restored.mounts[0].initialSyncDirection, 'opfs-to-memfs');
assert.equal(restored.mounts[0].mountpoint, '/wordpress');

console.log('Import job session checks passed.');

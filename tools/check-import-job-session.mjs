#!/usr/bin/env node

import assert from 'node:assert/strict';
import {
  cancelImportMutationDurably,
  createImportJobSession,
  createPlaygroundPersistence,
  recoverImportMutation,
  resetPlaygroundIframeForRetry,
  startPlaygroundWithSnapshotRecovery,
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
const legacyPersistenceStorage = new MemoryStorage();
legacyPersistenceStorage.setItem('legacy-playground', JSON.stringify({
  version: 1,
  devicePath: 'fixture/legacy-import-site',
}));
const legacyPersistence = createPlaygroundPersistence({
  storage: legacyPersistenceStorage,
  storageKey: 'legacy-playground',
  devicePath: 'fixture/legacy-import-site',
});
assert.equal(legacyPersistence.isPersisted(), true, 'Existing version 1 browser snapshots must remain restorable.');
assert.equal(legacyPersistence.startOptions({}).mounts[0].device.path, 'fixture/legacy-import-site');

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
assert.equal(restored.mounts[0].device.path, 'fixture/import-site');

const startAttempts = [];
const retryOrder = [];
let recoveryNotice = null;
const recoveredClient = await startPlaygroundWithSnapshotRecovery({
  persistence,
  options: baseOptions,
  async start(options) {
    startAttempts.push(options);
    retryOrder.push(`start-${startAttempts.length}`);
    if (startAttempts.length === 1) {
      throw new Error('Error connecting to the SQLite database.');
    }
    return { site: 'fresh' };
  },
  onRecovery(recovery, error) {
    retryOrder.push('recovery');
    recoveryNotice = { recovery, error };
  },
  async beforeRetry(recovery, error) {
    assert.strictEqual(recovery, recoveryNotice.recovery);
    assert.strictEqual(error, recoveryNotice.error);
    retryOrder.push('reset-start');
    await Promise.resolve();
    retryOrder.push('reset-complete');
  },
});
assert.deepEqual(recoveredClient, { site: 'fresh' });
assert.equal(startAttempts.length, 2, 'An invalid persisted SQLite site should retry exactly once.');
assert.equal(startAttempts[0].mounts[0].device.path, 'fixture/import-site');
assert.strictEqual(startAttempts[1], baseOptions, 'The retry must boot a fresh site without mounting the invalid snapshot.');
assert.equal(recoveryNotice.error.message, 'Error connecting to the SQLite database.');
assert.deepEqual(recoveryNotice.recovery, {
  abandonedDevicePath: 'fixture/import-site',
  devicePath: 'fixture/import-site-recovery-1',
  generation: 1,
});
assert.deepEqual(retryOrder, [
  'start-1',
  'recovery',
  'reset-start',
  'reset-complete',
  'start-2',
], 'The failed Playground iframe must finish tearing down before the fresh-site retry starts.');
assert.deepEqual(JSON.parse(persistenceStorage.getItem('playground')), {
  version: 2,
  state: 'fresh',
  devicePath: 'fixture/import-site-recovery-1',
  generation: 1,
}, 'The old OPFS path must be retained while the retry targets a fresh generation.');

const iframeListeners = new Map();
let iframeSource = 'https://playground.wordpress.net/remote.html';
const retryIframe = {
  addEventListener(type, listener) {
    iframeListeners.set(type, listener);
  },
  removeEventListener(type, listener) {
    if (iframeListeners.get(type) === listener) iframeListeners.delete(type);
  },
  get src() {
    return iframeSource;
  },
  set src(value) {
    iframeSource = value;
    queueMicrotask(() => iframeListeners.get('load')?.());
  },
};
await resetPlaygroundIframeForRetry(retryIframe, { timeoutMs: 100 });
assert.equal(retryIframe.src, 'about:blank');
assert.equal(iframeListeners.has('load'), false, 'The retry teardown must remove its iframe listener.');

const interruptedRecoveryPersistence = createPlaygroundPersistence({
  storage: persistenceStorage,
  storageKey: 'playground',
  devicePath: 'fixture/import-site',
});
assert.strictEqual(interruptedRecoveryPersistence.startOptions(baseOptions), baseOptions, 'Reloading during recovery must still boot fresh.');
mounted = null;
await interruptedRecoveryPersistence.persist({
  async mountOpfs(descriptor) { mounted = descriptor; },
});
assert.equal(mounted.device.path, 'fixture/import-site-recovery-1');
const recoveredPersistence = createPlaygroundPersistence({
  storage: persistenceStorage,
  storageKey: 'playground',
  devicePath: 'fixture/import-site',
});
const recoveredRestore = recoveredPersistence.startOptions(baseOptions);
assert.equal(recoveredRestore.mounts[0].device.path, 'fixture/import-site-recovery-1');
assert.equal(recoveredRestore.mounts[0].initialSyncDirection, 'opfs-to-memfs');

let transientAttempts = 0;
await assert.rejects(
  startPlaygroundWithSnapshotRecovery({
    persistence: recoveredPersistence,
    options: baseOptions,
    async start() {
      transientAttempts += 1;
      throw new Error('The Playground CDN is unavailable.');
    },
  }),
  /CDN is unavailable/,
  'A transient boot failure must not abandon a persisted site.',
);
assert.equal(transientAttempts, 1);
assert.equal(recoveredPersistence.isPersisted(), true);
assert.equal(recoveredPersistence.startOptions(baseOptions).mounts[0].device.path, 'fixture/import-site-recovery-1');

const freshPersistence = createPlaygroundPersistence({
  storage: new MemoryStorage(),
  storageKey: 'fresh-playground',
  devicePath: 'fixture/fresh-site',
});
let freshFailureAttempts = 0;
await assert.rejects(
  startPlaygroundWithSnapshotRecovery({
    persistence: freshPersistence,
    options: baseOptions,
    async start() {
      freshFailureAttempts += 1;
      throw new Error('Error connecting to the SQLite database.');
    },
  }),
  /SQLite database/,
  'A fresh-site database failure must surface instead of retrying forever.',
);
assert.equal(freshFailureAttempts, 1);

console.log('Import job session checks passed.');

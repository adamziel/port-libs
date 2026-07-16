#!/usr/bin/env node

import assert from 'node:assert/strict';
import {
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
  updatedAt: now,
});
session.remember({ jobId: 'job_123456789', status: 'complete' });
assert.equal(session.load(), null, 'A terminal import must clear its resume pointer.');
session.remember({ jobId: 'job_123456789', status: 'converting' });
assert.equal(session.load(), null, 'A stale in-flight poll must not resurrect a completed import pointer.');

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

const DEFAULT_MAX_JOB_AGE_MS = 7 * 24 * 60 * 60 * 1_000;

/**
 * Small durable pointer to a server-owned import. Source bytes and document
 * names deliberately stay out of Web Storage; WordPress remains the source of
 * truth and the client always refreshes the snapshot before resuming.
 */
export function createImportJobSession({
  storage,
  storageKey,
  maxAgeMs = DEFAULT_MAX_JOB_AGE_MS,
  now = () => Date.now(),
} = {}) {
  const key = String(storageKey || 'port-libs.active-import.v1');
  const terminalJobIds = new Set();

  const forget = (expectedJobId = '') => {
    const current = load();
    if (expectedJobId && current && current.jobId !== String(expectedJobId)) {
      return;
    }
    try { storage?.removeItem(key); } catch { /* Storage is an optional enhancement. */ }
  };

  const load = () => {
    let parsed;
    try {
      const encoded = storage?.getItem(key);
      if (!encoded) return null;
      parsed = JSON.parse(encoded);
    } catch {
      try { storage?.removeItem(key); } catch { /* Ignore unavailable storage. */ }
      return null;
    }
    const jobId = String(parsed?.jobId || '');
    const updatedAt = Number(parsed?.updatedAt || 0);
    const status = String(parsed?.status || '');
    const age = Math.max(0, now() - updatedAt);
    if (!/^[A-Za-z0-9_-]{12,128}$/.test(jobId)
      || !Number.isFinite(updatedAt)
      || updatedAt <= 0
      || age > Math.max(1, Number(maxAgeMs) || DEFAULT_MAX_JOB_AGE_MS)
      || terminalImportStatus(status)
    ) {
      try { storage?.removeItem(key); } catch { /* Ignore unavailable storage. */ }
      return null;
    }

    return {
      version: 1,
      jobId,
      status,
      cancellationRequested: parsed?.cancellationRequested === true,
      updatedAt,
    };
  };

  const remember = (snapshot) => {
    const jobId = String(snapshot?.jobId || '');
    const status = String(snapshot?.status || '');
    if (!/^[A-Za-z0-9_-]{12,128}$/.test(jobId)) return null;
    if (terminalImportStatus(status)) {
      terminalJobIds.add(jobId);
      forget(jobId);
      return null;
    }
    // A read-only poll sent before the terminal mutation can resolve after
    // its completion response. Never let that older snapshot recreate the
    // just-cleared Resume pointer during this page lifetime.
    if (terminalJobIds.has(jobId)) return null;
    const current = load();
    const record = {
      version: 1,
      jobId,
      status,
      cancellationRequested: current?.jobId === jobId && current.cancellationRequested === true,
      updatedAt: now(),
    };
    try { storage?.setItem(key, JSON.stringify(record)); } catch { /* The live import can continue. */ }
    return record;
  };

  const requestCancellation = (expectedJobId) => {
    const current = load();
    if (!current || current.jobId !== String(expectedJobId || '')) return null;
    const record = { ...current, cancellationRequested: true, updatedAt: now() };
    try { storage?.setItem(key, JSON.stringify(record)); } catch { /* The live cancellation can continue. */ }
    return record;
  };

  return { load, remember, forget, requestCancellation };
}

/**
 * Run a durable mutation, then consult server status before retransmitting an
 * uncertain request. This is intentionally conservative: while WordPress
 * still reports an in-flight worker, the browser waits instead of sending a
 * duplicate /advance request.
 */
export async function recoverImportMutation({
  mutate,
  readStatus,
  onSnapshot = () => {},
  onRecovery = () => {},
  isActive = () => true,
  shouldCancel = () => false,
  cancel = null,
  delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds)),
  maxMutationRetries = 2,
  statusChecksPerRetry = 3,
  statusCheckDelayMs = 400,
  inFlightStatus = 'converting',
} = {}) {
  if (typeof mutate !== 'function' || typeof readStatus !== 'function') {
    throw new TypeError('Durable import recovery requires mutate and readStatus callbacks.');
  }
  let lastError = null;
  const mutationRetries = boundedInteger(maxMutationRetries, 0, 10, 2);
  const statusChecks = boundedInteger(statusChecksPerRetry, 1, 20, 3);
  const baseDelay = boundedInteger(statusCheckDelayMs, 0, 60_000, 400);
  const cancelIfRequested = async () => {
    if (!shouldCancel()) return { requested: false, snapshot: null };
    if (typeof cancel !== 'function') throw importCancelledError();
    const snapshot = await cancel();
    onSnapshot(snapshot);
    return { requested: true, snapshot };
  };

  for (let mutationAttempt = 0; mutationAttempt <= mutationRetries; mutationAttempt += 1) {
    const beforeMutation = await cancelIfRequested();
    if (beforeMutation.requested) return beforeMutation.snapshot;
    if (!isActive()) throw importCancelledError();
    try {
      const snapshot = await mutate();
      onSnapshot(snapshot);
      const afterMutation = await cancelIfRequested();
      if (afterMutation.requested) return afterMutation.snapshot;
      return snapshot;
    } catch (error) {
      lastError = error;
    }
    const afterMutationError = await cancelIfRequested();
    if (afterMutationError.requested) return afterMutationError.snapshot;
    if (mutationAttempt >= mutationRetries || !isActive()) break;

    for (let statusAttempt = 1; statusAttempt <= statusChecks; statusAttempt += 1) {
      const beforeStatus = await cancelIfRequested();
      if (beforeStatus.requested) return beforeStatus.snapshot;
      if (!isActive()) throw importCancelledError();
      onRecovery({
        mutationAttempt: mutationAttempt + 1,
        maxMutationRetries: mutationRetries,
        statusAttempt,
        statusChecks,
        error: lastError,
      });
      if (baseDelay > 0) await delay(baseDelay * statusAttempt);
      const afterDelay = await cancelIfRequested();
      if (afterDelay.requested) return afterDelay.snapshot;
      try {
        const snapshot = await readStatus();
        onSnapshot(snapshot);
        const afterStatus = await cancelIfRequested();
        if (afterStatus.requested) return afterStatus.snapshot;
        if (String(snapshot?.status || '') !== String(inFlightStatus)) {
          return snapshot;
        }
      } catch (statusError) {
        lastError = statusError;
      }
    }
  }

  throw lastError instanceof Error ? lastError : new Error(String(lastError || 'Import request failed.'));
}

/**
 * Keep retrying a durable cancellation while another request owns the job
 * lock. Read-only status calls make every retry safe: terminal state wins,
 * while an active snapshot keeps the cancellation intent explicit.
 */
export async function cancelImportMutationDurably({
  cancel,
  readStatus,
  onSnapshot = () => {},
  onRetry = () => {},
  isActive = () => true,
  isRetryableError = retryableCancellationError,
  delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds)),
  retryDelayMs = 400,
  maxRetryDelayMs = 2_000,
} = {}) {
  if (typeof cancel !== 'function' || typeof readStatus !== 'function') {
    throw new TypeError('Durable import cancellation requires cancel and readStatus callbacks.');
  }
  const baseDelay = boundedInteger(retryDelayMs, 0, 60_000, 400);
  const maximumDelay = boundedInteger(maxRetryDelayMs, baseDelay, 60_000, Math.max(baseDelay, 2_000));
  let lastError = null;
  let attempt = 0;

  while (isActive()) {
    attempt += 1;
    try {
      const snapshot = await cancel();
      onSnapshot(snapshot);
      if (terminalImportStatus(snapshot?.status)) return snapshot;
    } catch (error) {
      lastError = error;
      if (!isRetryableError(error)) throw error;
    }
    if (!isActive()) break;

    try {
      const snapshot = await readStatus();
      onSnapshot(snapshot);
      if (terminalImportStatus(snapshot?.status)) return snapshot;
    } catch (error) {
      lastError = error;
      if (!isRetryableError(error)) throw error;
    }
    if (!isActive()) break;

    const waitMs = Math.min(maximumDelay, baseDelay * attempt);
    onRetry({ attempt, waitMs, error: lastError });
    if (waitMs > 0) await delay(waitMs);
  }

  throw lastError instanceof Error ? lastError : importCancelledError();
}

/**
 * Persist an embedded Playground in OPFS. This is what makes a saved job ID
 * useful after a GitHub Pages reload: both the WordPress option and its
 * checkpoint files survive with the same /wordpress tree.
 */
export function createPlaygroundPersistence({
  storage,
  storageKey = 'port-libs.playground-import-site.v1',
  devicePath = 'port-libs/playground-import-site-v1',
} = {}) {
  const key = String(storageKey);
  const basePath = String(devicePath).replace(/[^A-Za-z0-9_./-]/g, '-');
  const stored = readPersistenceRecord(storage, key, basePath);
  let path = stored?.devicePath || basePath;
  let generation = stored?.generation || 0;
  let persisted = stored?.state === 'persisted';

  const descriptor = (initialSyncDirection) => ({
    device: { type: 'opfs', path },
    mountpoint: '/wordpress',
    initialSyncDirection,
  });

  return {
    isPersisted() { return persisted; },
    startOptions(options) {
      if (!persisted) return options;
      return {
        ...options,
        // WordPress is already present in the saved mount. This deprecated
        // compatibility flag remains supported by the CDN client versions
        // used by the showcase and avoids downloading a second core tree.
        shouldInstallWordPress: false,
        mounts: [...(Array.isArray(options?.mounts) ? options.mounts : []), descriptor('opfs-to-memfs')],
      };
    },
    async persist(client, onProgress = () => {}) {
      if (persisted) return true;
      if (!client || typeof client.mountOpfs !== 'function') return false;
      onProgress('Saving this Playground in browser storage so the import can survive a reload…');
      await client.mountOpfs(descriptor('memfs-to-opfs'));
      try {
        storage?.setItem(key, JSON.stringify({
          version: 2,
          state: 'persisted',
          devicePath: path,
          generation,
        }));
        persisted = true;
      } catch {
        // The mount remains useful for this runtime, but without the pointer
        // a later page cannot safely assume it exists.
        return false;
      }
      return true;
    },
    quarantineInvalidSnapshot() {
      if (!persisted) return null;
      const abandonedDevicePath = path;
      generation += 1;
      path = `${basePath}-recovery-${generation}`;
      persisted = false;
      try {
        // Remember the unused recovery target before retrying. If the page is
        // reloaded during the fresh boot, it must not mount the known-bad tree
        // or overwrite it while synchronizing the replacement site.
        storage?.setItem(key, JSON.stringify({
          version: 2,
          state: 'fresh',
          devicePath: path,
          generation,
        }));
      } catch {
        try { storage?.removeItem(key); } catch { /* Storage is optional. */ }
      }
      return { abandonedDevicePath, devicePath: path, generation };
    },
    forget() {
      persisted = false;
      try { storage?.removeItem(key); } catch { /* Ignore unavailable storage. */ }
    },
  };
}

/**
 * A persisted WordPress tree can become unusable when an OPFS sync or browser
 * shutdown leaves its SQLite database incomplete. Retry that deterministic
 * restore failure once with a fresh OPFS generation. Other startup failures
 * retain the saved pointer and surface unchanged.
 */
export async function startPlaygroundWithSnapshotRecovery({
  persistence,
  options,
  start,
  onRecovery = () => {},
} = {}) {
  if (!persistence
    || typeof persistence.isPersisted !== 'function'
    || typeof persistence.startOptions !== 'function'
    || typeof persistence.quarantineInvalidSnapshot !== 'function'
    || typeof start !== 'function'
  ) {
    throw new TypeError('Playground snapshot recovery requires persistence and start callbacks.');
  }

  let recovered = false;
  for (;;) {
    const restoringSnapshot = persistence.isPersisted();
    try {
      return await start(persistence.startOptions(options));
    } catch (error) {
      if (recovered || !restoringSnapshot || !isInvalidPlaygroundSqliteSnapshot(error)) {
        throw error;
      }
      const recovery = persistence.quarantineInvalidSnapshot();
      if (!recovery) throw error;
      recovered = true;
      onRecovery(recovery, error);
    }
  }
}

export function terminalImportStatus(status) {
  return ['complete', 'failed', 'cancelled'].includes(String(status || ''));
}

function readPersistenceRecord(storage, key, basePath) {
  try {
    const parsed = JSON.parse(storage?.getItem(key) || 'null');
    if (parsed?.version === 1 && parsed?.devicePath === basePath) {
      return {
        state: 'persisted',
        devicePath: basePath,
        generation: 0,
      };
    }
    const generation = Number(parsed?.generation);
    if (parsed?.version !== 2
      || !['fresh', 'persisted'].includes(parsed?.state)
      || !Number.isSafeInteger(generation)
      || generation < 0
      || generation > 1_000_000
    ) {
      return null;
    }
    const expectedPath = generation === 0 ? basePath : `${basePath}-recovery-${generation}`;
    if (parsed.devicePath !== expectedPath) return null;
    return {
      state: parsed.state,
      devicePath: expectedPath,
      generation,
    };
  } catch {
    return null;
  }
}

function isInvalidPlaygroundSqliteSnapshot(error) {
  const messages = [error?.message, error?.cause?.message, String(error || '')];
  return messages.some((message) => String(message || '').includes('Error connecting to the SQLite database.'));
}

function boundedInteger(value, minimum, maximum, fallback) {
  const number = Number(value);
  return Number.isSafeInteger(number) && number >= minimum && number <= maximum ? number : fallback;
}

function importCancelledError() {
  const error = new Error('Import recovery was cancelled.');
  error.name = 'AbortError';
  return error;
}

function retryableCancellationError(error) {
  const status = Number(error?.status || 0);
  return !Number.isFinite(status)
    || status <= 0
    || [408, 409, 423, 425, 429].includes(status)
    || status >= 500;
}

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

    return { version: 1, jobId, status, updatedAt };
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
    const record = { version: 1, jobId, status, updatedAt: now() };
    try { storage?.setItem(key, JSON.stringify(record)); } catch { /* The live import can continue. */ }
    return record;
  };

  return { load, remember, forget };
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

  for (let mutationAttempt = 0; mutationAttempt <= mutationRetries; mutationAttempt += 1) {
    if (!isActive()) throw importCancelledError();
    try {
      const snapshot = await mutate();
      onSnapshot(snapshot);
      return snapshot;
    } catch (error) {
      lastError = error;
    }
    if (mutationAttempt >= mutationRetries || !isActive()) break;

    for (let statusAttempt = 1; statusAttempt <= statusChecks; statusAttempt += 1) {
      if (!isActive()) throw importCancelledError();
      onRecovery({
        mutationAttempt: mutationAttempt + 1,
        maxMutationRetries: mutationRetries,
        statusAttempt,
        statusChecks,
        error: lastError,
      });
      if (baseDelay > 0) await delay(baseDelay * statusAttempt);
      try {
        const snapshot = await readStatus();
        onSnapshot(snapshot);
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
  const path = String(devicePath).replace(/[^A-Za-z0-9_./-]/g, '-');
  let persisted = readPersistenceRecord(storage, key, path);

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
        storage?.setItem(key, JSON.stringify({ version: 1, devicePath: path }));
        persisted = true;
      } catch {
        // The mount remains useful for this runtime, but without the pointer
        // a later page cannot safely assume it exists.
        return false;
      }
      return true;
    },
    forget() {
      persisted = false;
      try { storage?.removeItem(key); } catch { /* Ignore unavailable storage. */ }
    },
  };
}

export function terminalImportStatus(status) {
  return ['complete', 'failed'].includes(String(status || ''));
}

function readPersistenceRecord(storage, key, devicePath) {
  try {
    const parsed = JSON.parse(storage?.getItem(key) || 'null');
    return parsed?.version === 1 && parsed?.devicePath === devicePath;
  } catch {
    return false;
  }
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

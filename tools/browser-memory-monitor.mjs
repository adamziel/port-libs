import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

const kibibyte = 1024;

export function parseProcessRows(output) {
  return String(output).split('\n').map((line) => {
    const match = line.match(/^\s*(\d+)\s+(\d+)\s+(\d+)\s+(.*)$/);
    return match ? {
      pid: Number(match[1]),
      ppid: Number(match[2]),
      rssBytes: Number(match[3]) * kibibyte,
      command: match[4],
    } : null;
  }).filter(Boolean);
}

export function parseSmapsRollup(contents) {
  const fields = new Map();
  for (const match of String(contents).matchAll(/^([A-Za-z_]+):\s+(\d+)\s+kB\s*$/gm)) {
    fields.set(match[1], Number(match[2]) * kibibyte);
  }
  if (!fields.has('Pss') || !fields.has('Rss')) {
    throw new Error('Linux smaps_rollup did not contain both Pss and Rss totals.');
  }
  return {
    pssBytes: fields.get('Pss'),
    rssBytes: fields.get('Rss'),
    privateBytes: (fields.get('Private_Clean') || 0) + (fields.get('Private_Dirty') || 0),
    swapPssBytes: fields.get('SwapPss') || 0,
  };
}

function selectedProcessRows(rows, rootPid, debuggingPort) {
  const roots = rootPid > 0
    ? rows.filter((row) => row.pid === rootPid)
    : rows.filter((row) => row.command.includes(`--remote-debugging-port=${debuggingPort}`)
      && !row.command.includes('--type='));
  if (roots.length === 0) {
    throw new Error(rootPid > 0
      ? `Could not find the Chrome root process ${rootPid}.`
      : `Could not find Chrome on remote debugging port ${debuggingPort}.`);
  }

  const included = new Set(roots.map((row) => row.pid));
  let changed = true;
  while (changed) {
    changed = false;
    for (const row of rows) {
      if (!included.has(row.pid) && included.has(row.ppid)) {
        included.add(row.pid);
        changed = true;
      }
    }
  }
  return rows.filter((row) => included.has(row.pid));
}

export function aggregateBrowserMemory({
  rows,
  rootPid = 0,
  debuggingPort = '',
  platform = process.platform,
  readSmaps = (pid) => readFileSync(`/proc/${pid}/smaps_rollup`, 'utf8'),
}) {
  const selected = selectedProcessRows(rows, rootPid, debuggingPort);
  if (platform !== 'linux') {
    const rssBytes = selected.reduce((sum, row) => sum + row.rssBytes, 0);
    return {
      measurementBytes: rssBytes,
      memoryMetric: 'summed-rss',
      pssBytes: 0,
      rssBytes,
      privateBytes: 0,
      swapPssBytes: 0,
      processCount: selected.length,
      exitedProcessCount: 0,
    };
  }

  let pssBytes = 0;
  let rssBytes = 0;
  let privateBytes = 0;
  let swapPssBytes = 0;
  let processCount = 0;
  let exitedProcessCount = 0;
  for (const row of selected) {
    let totals;
    try {
      totals = parseSmapsRollup(readSmaps(row.pid));
    } catch (error) {
      if (error?.code === 'ENOENT' && row.pid !== rootPid) {
        exitedProcessCount += 1;
        continue;
      }
      const reason = error instanceof Error ? error.message : String(error);
      throw new Error(`Could not read proportional memory for Chrome process ${row.pid}: ${reason}`);
    }
    pssBytes += totals.pssBytes;
    rssBytes += totals.rssBytes;
    privateBytes += totals.privateBytes;
    swapPssBytes += totals.swapPssBytes;
    processCount += 1;
  }
  if (processCount === 0) {
    throw new Error('Could not measure any live Chrome process.');
  }
  return {
    measurementBytes: pssBytes,
    memoryMetric: 'pss',
    pssBytes,
    rssBytes,
    privateBytes,
    swapPssBytes,
    processCount,
    exitedProcessCount,
  };
}

export function browserProcessTreeMemory(baseUrl, options = {}) {
  const debuggingPort = new URL(baseUrl).port;
  if (!debuggingPort && !(options.rootPid > 0)) {
    throw new Error('A Chrome PID or remote debugging port is required for memory measurement.');
  }
  let output;
  try {
    output = execFileSync('/bin/ps', ['-axo', 'pid=,ppid=,rss=,command='], {
      encoding: 'utf8',
      maxBuffer: 8 * 1024 * 1024,
    });
  } catch (error) {
    const reason = error instanceof Error ? error.message : String(error);
    throw new Error(`Could not inspect the Chrome process tree: ${reason}`);
  }
  return aggregateBrowserMemory({
    rows: parseProcessRows(output),
    rootPid: options.rootPid || 0,
    debuggingPort,
  });
}

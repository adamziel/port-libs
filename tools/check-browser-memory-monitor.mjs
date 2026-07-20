#!/usr/bin/env node

import assert from 'node:assert/strict';
import process from 'node:process';
import {
  aggregateBrowserMemory,
  browserProcessTreeMemory,
  parseProcessRows,
  parseSmapsRollup,
} from './browser-memory-monitor.mjs';

const kibibyte = 1024;
const smaps = ({ rss, pss, privateClean = 0, privateDirty = 0, swapPss = 0 }) => `
Private_Dirty: ${privateDirty} kB
Rss: ${rss} kB
SwapPss: ${swapPss} kB
Pss: ${pss} kB
Private_Clean: ${privateClean} kB
Referenced: 1 kB
`;

const rows = parseProcessRows(`
  100 1 800 chrome --remote-debugging-port=9222
  101 100 400 chrome --type=renderer
  102 1 900 unrelated
`);
assert.deepEqual(rows.map(({ pid, ppid, rssBytes }) => [pid, ppid, rssBytes]), [
  [100, 1, 800 * kibibyte],
  [101, 100, 400 * kibibyte],
  [102, 1, 900 * kibibyte],
]);

assert.deepEqual(parseSmapsRollup(smaps({
  rss: 120,
  pss: 60,
  privateClean: 10,
  privateDirty: 20,
  swapPss: 3,
})), {
  rssBytes: 120 * kibibyte,
  pssBytes: 60 * kibibyte,
  privateBytes: 30 * kibibyte,
  swapPssBytes: 3 * kibibyte,
});

const linux = aggregateBrowserMemory({
  rows,
  rootPid: 100,
  platform: 'linux',
  readSmaps: (pid) => pid === 100
    ? smaps({ rss: 800, pss: 400, privateDirty: 250 })
    : smaps({ rss: 400, pss: 200, privateDirty: 100 }),
});
assert.equal(linux.memoryMetric, 'pss');
assert.equal(linux.measurementBytes, 600 * kibibyte, 'Shared resident pages must not be counted once per Chrome process.');
assert.equal(linux.rssBytes, 1200 * kibibyte, 'Summed RSS remains available as conservative telemetry.');
assert.equal(linux.privateBytes, 350 * kibibyte);
assert.equal(linux.processCount, 2);

const nonLinux = aggregateBrowserMemory({ rows, rootPid: 100, platform: 'darwin' });
assert.equal(nonLinux.memoryMetric, 'summed-rss');
assert.equal(nonLinux.measurementBytes, 1200 * kibibyte);

const exited = new Error('gone');
exited.code = 'ENOENT';
const childExited = aggregateBrowserMemory({
  rows,
  rootPid: 100,
  platform: 'linux',
  readSmaps: (pid) => {
    if (pid === 101) throw exited;
    return smaps({ rss: 800, pss: 400 });
  },
});
assert.equal(childExited.processCount, 1);
assert.equal(childExited.exitedProcessCount, 1);

assert.throws(() => aggregateBrowserMemory({
  rows,
  rootPid: 100,
  platform: 'linux',
  readSmaps: () => {
    const denied = new Error('permission denied');
    denied.code = 'EACCES';
    throw denied;
  },
}), /Could not read proportional memory/);
assert.throws(() => aggregateBrowserMemory({ rows, rootPid: 999, platform: 'linux' }), /Could not find the Chrome root process/);
assert.throws(() => parseSmapsRollup('Rss: 10 kB\n'), /both Pss and Rss/);

const live = browserProcessTreeMemory('http://127.0.0.1:9222', { rootPid: process.pid });
assert.ok(live.measurementBytes > 0, 'The live process monitor must return a non-zero measurement.');
assert.ok(live.rssBytes >= live.pssBytes, 'Linux PSS cannot exceed the corresponding summed RSS snapshot.');
assert.ok(['pss', 'summed-rss'].includes(live.memoryMetric));

console.log('Browser memory monitor checks passed.');

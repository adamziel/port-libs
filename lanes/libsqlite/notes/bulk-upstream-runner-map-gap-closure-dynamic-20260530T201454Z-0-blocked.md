# bulk-upstream-runner-map-gap-closure-dynamic-20260530T201454Z-0

Status: blocked; no ready patch emitted for this bulk slice.

Base accepted HEAD: `c1a0d2c80ea721e0595b20a5cbe43c5043856066`.

## Attempted Scope

This slice inspected the current runner-map denominator state for a possible
bulk upstream runner-map gap closure using the hydrated SQLite checkout at:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/ext`

The current manifest already records:

- Denominator total: `1589`
- Current mapped denominator rows: `1472`
- Remaining denominator capacity: `117`
- Top-level hydrated `test/*.test` rows already mapped: `1189`
- Extension/nested hydrated `.test` rows admitted by the latest map-gap
  closure: `283`
- Hydrated mapped `.test` count after that closure: `1472`

## Blocker

The hard bulk floor for `bulk-upstream-*` slices requires one of:

- at least `1000` distinct focused PHP PASS cases;
- at least `5000` behavior assertions;
- a named blocker fix that unlocks at least `2000` PASS cases or `10000`
  assertions in the next batch;
- real mapped denominator movement with guarded upstream-runner evidence.

This runner-map slice cannot honestly satisfy that floor on the current base:

- the maximum possible mapped-denominator movement left is `117`, below the
  `1000`-row bulk floor;
- the existing `realUpstreamRunnerMapGapClosure()` helper only admits concrete
  hydrated upstream `.test` filenames, and the manifest has already advanced
  those real hydrated script rows to `1472`;
- the remaining `117` denominator rows are not a bulk of unclaimed real
  `.test` scripts; they need separate classification for non-`.test` harness,
  mptest, C helper, tool, or long-running release/all suite units;
- adding another `current-source-nextNNN` synthetic shard test would duplicate
  stale low-value runner-map admissions and would not meet the current
  throughput rules.

## Next Larger Batch

The next integrable runner-map batch should not be another numbered shard. It
should do one of the following:

- classify the remaining `117` non-`.test` denominator rows into explicit
  admitted, excluded, or blocked buckets with real upstream paths and guarded
  evidence;
- run a guarded `release`/`all` tier artifact when duplicate-runner gates are
  clear, then map the remaining suite-tier blocker state from that artifact;
- add behavior-backed PHP corpus tests from real upstream `.test` sections
  instead of trying to inflate denominator rows.

## Evidence Commands

- `php -r '...UPSTREAM_TEST_MANIFEST.json denominator summary...'`
  - Result: `total=1589`, `mapped=1472`, `closure_remaining=117`,
    `hydrated=1472`, `already=1189`, `admitted=283`.
- `find /home/claude/port-libs/.upstream-cache/libsqlite/test /home/claude/port-libs/.upstream-cache/libsqlite/ext -name '*.test' | wc -l`
  - Result: `1467` real `.test` paths under those hydrated roots.

## Dependency Closure

No new support component is needed. The blocker is runner admission scope, not
a missing PHP dependency: remaining movement needs guarded release/all or
non-`.test` denominator classification evidence.

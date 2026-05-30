# Bulk upstream suite denominator burnup dynamic blocker

Micro-slice: `bulk-upstream-suite-denominator-burnup-dynamic-20260530T200441Z-0`

Status: blocked, no ready throughput patch.

Accepted base: `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`

Attempted upstream section:

- Hydrated upstream SQLite checkout:
  `/home/claude/port-libs/.upstream-cache/libsqlite`
- Top-level upstream test scripts:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/*.test`
- Extension, nested extension, mptest, and tool `.test` paths recorded in the
  manifest `extensionHydratedScriptMapGapClosure` row.

Findings on this base:

- Current manifest denominator: `1472 / 1589` mapped.
- Remaining denominator capacity: `117` rows.
- Hydrated top-level `test/*.test` scripts: `1189`.
- Hydrated `.test` scripts across the upstream checkout: `1472`.
- Manifest `extensionHydratedScriptMapGapClosure` already admits the `283`
  real extension, nested extension, `mptest`, and tool `.test` paths:
  `1189 -> 1472` mapped.
- The remaining `117` denominator rows are not available as unmapped real
  hydrated `.test` script ids. They are non-`.test` harness, C helper, mptest,
  tool program, or tool-ish inventory units that need a different guarded
  evidence path.

Why this is blocked:

- The bulk floor requires at least `1000` distinct focused PASS cases, `5000`
  behavior assertions, a named blocker unlock worth at least `2000` PASS cases
  or `10000` assertions, or real mapped denominator movement with guarded
  upstream-runner evidence.
- This denominator-burnup path has only `117` remaining mapped rows, below the
  `1000` mapped-row floor.
- Adding another top-level or extension `.test` denominator batch would overlap
  already accepted hydrated script-map closure evidence.
- Inventing script ids for the remaining non-`.test` inventory units would
  violate the real-upstream and no-fabricated-denominator rules.
- This slice does not claim release/all parity, PHP PASS-line growth,
  metadata-only PASS inflation, or stale `next965-980` overlap.

Focused evidence run for the blocker shape:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBulkUpstreamSuiteDenominatorBurnupDynamicBlockedTest.php`

Next larger batch to try:

Build a guarded admission path for one remaining non-`.test` inventory family,
such as Tcl harness files, C helper programs, mptest support files, or tool
test programs. The admission must cite real hydrated upstream paths and parse a
real guarded runner or build artifact before moving mapped coverage. If the
goal is PASS-line growth instead, switch to a `real-upstream-corpus-*` behavior
batch over under-covered upstream SQL, pager, WAL, pragma, planner, JSON,
trigger, or VFS behavior.

Dependency closure: no new support component is needed for this blocker note.
The next denominator implementation may need a bounded artifact parser for the
specific non-`.test` inventory family it targets, but that should be activated
only with real guarded runner evidence.

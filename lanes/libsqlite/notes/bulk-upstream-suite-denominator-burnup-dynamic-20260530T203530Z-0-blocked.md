# Bulk upstream suite denominator burnup dynamic blocker

Micro-slice: `bulk-upstream-suite-denominator-burnup-dynamic-20260530T203530Z-0`

Status: blocked, no ready throughput patch.

Accepted base: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`

Attempted upstream section:

- Hydrated upstream SQLite checkout:
  `/home/claude/port-libs/.upstream-cache/libsqlite`
- Top-level upstream test scripts:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/*.test`
- Full hydrated upstream `.test` script inventory across the checkout.

Findings on this base:

- Current manifest denominator: `1472 / 1589` mapped.
- Remaining denominator capacity: `117` rows.
- Hydrated top-level `test/*.test` scripts: `1189`.
- Hydrated `.test` scripts across the upstream checkout: `1472`.
- The accepted manifest already records
  `extensionHydratedScriptMapGapClosure`, which closes the real hydrated
  `.test` script map from `1189` top-level scripts to `1472` total hydrated
  scripts.
- The remaining `117` denominator rows are not available as unmapped real
  hydrated `.test` script ids. They are non-`.test` harness, C helper, mptest,
  tool program, or tool-ish inventory units that need a different guarded
  evidence path.

Why this is blocked:

- The hard bulk floor requires at least `1000` distinct focused PASS cases,
  `5000` behavior assertions, a named blocker unlock worth at least `2000`
  PASS cases or `10000` assertions, or real mapped denominator movement with
  guarded upstream-runner evidence.
- The remaining denominator headroom is only `117` rows, below the mapped-row
  bulk floor.
- Adding another top-level or extension `.test` denominator batch would overlap
  the accepted hydrated script-map closure evidence.
- Generating `next965-980`-style rows would overlap the stale suite-shard
  warning and would not cite real hydrated upstream scripts.
- Inventing script ids for the remaining non-`.test` inventory units would
  violate the real-upstream and no-fabricated-denominator rules.

Focused evidence run for this blocker shape:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBulkUpstreamSuiteDenominatorBurnupDynamicBlockedTest.php`
- Result: `1 test files, 33 assertions, 0 failures`

Counts:

- Actual PHP PASS-line growth: `0`
- Behavior assertion growth: `0`
- Mapped denominator movement: `1472 / 1589 -> 1472 / 1589`
- Upstream runner pass/fail rows newly admitted: `0`

Next larger batch to try:

Build a guarded admission path for one remaining non-`.test` inventory family,
such as Tcl harness files, C helper programs, mptest support files, or tool
test programs. The admission must cite real hydrated upstream paths and parse
a real guarded runner or build artifact before moving mapped coverage. If the
goal is PASS-line growth instead, switch to a `real-upstream-corpus-*`
behavior batch over under-covered upstream SQL, pager, WAL, pragma, planner,
JSON, trigger, or VFS behavior.

Dependency closure: no new support component is needed for this blocker note.
The next denominator implementation may need a bounded artifact parser for the
specific non-`.test` inventory family it targets, but that should be activated
only with real guarded runner evidence.

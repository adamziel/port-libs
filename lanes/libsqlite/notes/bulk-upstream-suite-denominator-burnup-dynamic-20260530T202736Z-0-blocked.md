# Bulk upstream suite denominator burnup dynamic blocker

Micro-slice: `bulk-upstream-suite-denominator-burnup-dynamic-20260530T202736Z-0`

Status: blocked, no ready throughput patch.

Accepted base: `a5d711ea245dda1130ca2ff1ba1b791f9a863c2b`

Attempted upstream section:

- Hydrated upstream SQLite checkout:
  `/home/claude/port-libs/.upstream-cache/libsqlite`
- Real hydrated upstream `.test` paths across the checkout.
- Manifest `extensionHydratedScriptMapGapClosure` row.

Findings on this base:

- Current manifest denominator: `1472 / 1589` mapped.
- Remaining denominator capacity: `117` rows.
- Hydrated top-level `test/*.test` scripts: `1189`.
- Hydrated `.test` scripts across the upstream checkout: `1472`.
- The accepted manifest already maps the `283` real extension, nested
  extension, `mptest`, and tool `.test` paths that moved coverage
  `1189 -> 1472`.
- The remaining `117` denominator rows are not unmapped real hydrated `.test`
  script ids. They are non-`.test` harness, C helper, tool, or tool-ish
  inventory units requiring a separate guarded evidence path.

Why this is blocked:

- The bulk floor requires at least `1000` distinct focused PASS cases, `5000`
  behavior assertions, a named blocker unlock worth at least `2000` PASS cases
  or `10000` assertions, or real mapped denominator movement with guarded
  upstream-runner evidence.
- This denominator-burnup path has only `117` remaining mapped rows, below the
  `1000` mapped-row floor.
- Adding another top-level, extension, nested extension, `mptest`, or tool
  `.test` denominator batch would overlap already accepted hydrated
  script-map closure evidence.
- Inventing script ids for the remaining non-`.test` inventory units would
  violate the real-upstream and no-fabricated-denominator rules.
- This slice does not claim release/all parity, PHP PASS-line growth,
  metadata-only PASS inflation, or stale `next965-980` overlap.

Focused evidence run for the blocker shape:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBulkUpstreamSuiteDenominatorBurnupDynamicBlockedTest.php`

Next larger batch to try:

Build a guarded admission path for one remaining non-`.test` inventory family,
such as Tcl harness files, C helper programs, or tool test programs. The
admission must cite real hydrated upstream paths and parse a real guarded
runner, build, or tool artifact before moving mapped coverage. If the goal is
PASS-line growth instead, switch to a `real-upstream-corpus-*` behavior batch
over under-covered upstream SQL, pager, WAL, pragma, planner, JSON, trigger,
or VFS behavior.

Dependency closure: no new support component is needed for this blocker note.
The next denominator implementation may need a bounded artifact parser for the
specific non-`.test` inventory family it targets, but that should be activated
only with real guarded runner evidence.

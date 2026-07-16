# bulk-upstream-runner-map-gap-closure-dynamic-20260530T202830Z-0 blocked

Status: blocked by exhausted real `.test` runner-map gaps and remaining
denominator capacity below the hard bulk floor.

## Attempted upstream section

- Hydrated upstream test directory:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test`
- SQLite upstream commit recorded in `UPSTREAM_TEST_MANIFEST.json`:
  `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- Runner-map API queried:
  `SQLiteUpstreamSuiteEvidence::upstreamRunnerMapGapClosurePlan(...)`
- Requested bulk floor: `1000` real `.test` runner-map candidates.

## Audit result

- Current accepted base: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`
- Current PHP PASS lines: `612306`
- Current mapped denominator: `1472 / 1589`
- Remaining denominator capacity: `117`
- Real top-level upstream `test/*.test` scripts found: `1189`
- Hydrated `.test` scripts found under upstream cache: `1335`
- Already selected or mapped scripts reported by the manifest/runner audit:
  `1472`
- New top-level `.test` runner-map candidates: `0`
- Before/after PHP PASS lines for this slice: `612306 -> 612306`
- Before/after PHP assertions for the focused evidence test:
  `5249 -> 5249`
- Before/after mapped denominator rows: `1472 / 1589 -> 1472 / 1589`
- Before/after upstream runner pass/fail rows: `0 -> 0` new rows; no fresh
  runner launched because the generated candidate set is empty.

The plan status was `exhausted`, with next gate:

`top-level hydrated .test runner-map rows are already mapped; target the remaining non-.test harness, helper, mptest, and tool denominator units with separate guarded evidence`

## Blocker

This bulk slice cannot honestly satisfy any ready handoff gate:

- It cannot add `1000` distinct focused TestRunner PASS cases.
- It cannot add `5000` new behavior assertions.
- It cannot unlock `2000` next-batch PASS cases or `10000` assertions by a
  behavior/runner fix.
- It cannot move real mapped denominator coverage with `.test` runner-map
  evidence, because the real top-level `.test` map is exhausted and only `117`
  denominator units remain.

Publishing a ready patch here would require fabricated denominator rows or
metadata-only PASS inflation, both explicitly rejected by the current
supervisor floor.

## Next larger batch

The next countable runner closure batch should target the remaining `117`
non-`.test` denominator units as a separate guarded evidence class, not another
top-level `.test` runner-map closure. The likely inventory buckets are:

- `testDirectoryTclHarnessFiles`: `32`
- `testDirectoryCPrograms`: `33`
- `srcTestCOrHeaderHelpers`: `47`
- `mptestFiles`: `6`
- `toolTestPrograms`: `4`
- `toolTestishFiles`: `76`

Because those buckets are not ordinary top-level `.test` scripts, they need a
source-neutral admission rule that ties each row to a real hydrated upstream
file, a runnable guarded command when one exists, or an explicit non-runnable
support-file evidence rule. That adapter is the missing blocker before the
remaining denominator can close without fake script ids.

## Verification

- `php -r 'require "tools/bootstrap.php"; ... upstreamRunnerMapGapClosurePlan("/home/claude/port-libs/.upstream-cache/libsqlite/test", 1000) ...'`
  - Result: `status=exhausted`, `real_script_count=1189`,
    `already_selected_script_count=1472`, `candidate_count=0`,
    `remaining_denominator=117`, `runnable=true`.
- `find /home/claude/port-libs/.upstream-cache/libsqlite/test -maxdepth 1 -name '*.test' | wc -l`
  - Result: `1189`.
- `find /home/claude/port-libs/.upstream-cache/libsqlite -path '*/test/*' -name '*.test' | wc -l`
  - Result: `1335`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php`
  - Result: `1 test files, 5249 assertions, 0 failures`.

## Dependency closure

No new external dependency is needed. The missing support is a bounded
lane-local denominator adapter for real non-`.test` upstream harness, C helper,
mptest, and tool-test files, reusing the hydrated SQLite checkout and existing
guarded runner/provenance gates.

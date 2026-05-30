# bulk-upstream-runner-map-gap-closure-dynamic-20260530T194735Z-0 blocked

Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`.

Attempted section: runner-map gap closure for hydrated upstream SQLite test
scripts under `/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Result: blocked for this bulk slice. The current accepted manifest already
contains the real hydrated `.test` map-gap closure:

- mapped denominator before latest accepted closure: `1189 / 1589`
- mapped denominator after latest accepted closure: `1472 / 1589`
- mapped delta already accepted: `+283`
- remaining denominator: `117`
- latest mapped addition states the remaining rows are non-`.test` harness,
  C helper, mptest, tool, or tool-ish inventory units.

Focused plan evidence on this base reports the top-level hydrated `.test`
runner-map candidate set is exhausted:

- `SQLiteUpstreamRunnerMapGapClosurePlanTest.php` status: `exhausted`
- real hydrated script count: at least `1189`
- already selected/mapped script count: at least `1189`
- candidate `.test` scripts: `0`
- mapped delta available from this `.test` runner-map path: `0`

Hydrated upstream inventory observed for the next larger batch:

- `test/*.tcl` harness files: `31` observed locally (`32` in manifest
  inventory)
- `test/*.c` programs: `32` observed locally (`33` in manifest inventory)
- `src/test*` C/header helpers: `47`
- `mptest/*` files: `6`
- `tool/*` files observed in the cache scan: `95` (`4` tool programs and
  `76` tool-ish files in the manifest inventory)

This slice cannot satisfy the hard throughput floor by adding PASS lines,
assertions, or real mapped `.test` denominator rows without duplicating the
accepted 283-row hydrated-script closure or fabricating script ids. No
`lane-status.json` or manifest counters were changed.

Next larger batch to try: add a guarded non-`.test` denominator mapper that
enumerates the remaining harness/helper/tool classes from the hydrated SQLite
checkout, ties each row to an actual upstream file path and a runnable or
auditable gate, and only then moves the final `117` mapped denominator rows.
The mapper should explicitly separate `test/*.tcl`, `test/*.c`, `src/test*`,
`mptest/*`, and `tool/*` units rather than routing them through the exhausted
`.test` runner-map path.

Verification run:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamRunnerMapGapClosurePlanTest.php lanes/libsqlite/tests/SQLiteUpstreamRunnerMapGapClosureDynamicTest.php`
- result: `2 test files, 75 assertions, 0 failures`

Dependency closure: no new support component was added. The blocker is a
missing guarded mapper/admission contract for the remaining non-`.test`
upstream denominator units, not missing hydrated upstream files.

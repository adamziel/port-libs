# Bulk upstream suite denominator burnup dynamic 20260530T174230Z-0

Base accepted HEAD: `e12ceba2fd83282957420709bd781aee710bc7ca`

This slice replaces the stale synthetic `bulk-suite-b-###.test` denominator
rows with a real hydrated upstream corpus range. The focused row generator now
uses the first 1024 sorted files from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/*.test`, beginning at
`8_3_names.test` and ending at `upfrom3.test`.

The reusable admission helper now validates rows that provide real upstream
provenance:

- `upstream_path` must exist.
- The upstream basename must match one of the row `scripts`.
- `upstream_sha256` must match the hydrated upstream file.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBulkUpstreamSuiteDenominatorBurnupCurrentSourceBTest.php`
- Result: `1 test files, 4175 assertions, 0 failures`
- Actual TestRunner PASS lines: 15
- Guarded record movement: 1024 real upstream script rows, 1024 mapped/countable row candidates, 0 row blockers when provenance is valid

Countability note:

This should be treated as mapped denominator/tooling movement after integrator
review, not native behavior PASS-line growth. The lane-status `phpPass` value is
left unchanged. The patch is intentionally source-neutral and adds no
domain-specific API, fixture, or scenario.

Dependency closure:

No new support component is needed. The slice reuses the hydrated upstream
SQLite checkout, the existing focused PHP TestRunner, and the existing duplicate
broad-runner gate.

# real-upstream-corpus-date-affinity-dynamic-20260530T200427Z-0

Added `SQLiteRealUpstreamDateFloorCeilingMonthMatrixCorpusTest.php` as an additive real upstream date corpus slice.

Upstream source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `date-19.1..19.12`: `floor` normalizes day-31 month boundaries to the last valid day of the month.
- `date-19.21..19.32`: `ceiling` normalizes day-31 month boundaries through SQLite's overflow date normalization.

Focused behavior:
- 1,200 generated TestRunner cases over 1975 through 2024, twelve months, and both `floor` and `ceiling` policies.
- 6,003 focused assertions: source citation, 1,200 policy cases with five assertions each, and one generic application cutoff check.
- The generic application case uses `key_name` retention/billing rows and does not introduce domain-specific APIs or fixture names.

Non-overlap:
- Existing accepted date-affinity coverage already covers `date2.test` determinism/schema guards, `date2-331`, `date2-500`, `date3.test` auto/unixepoch boundaries, `date4.test`, `date5.test`, and sampled `date.test` floor/ceiling shift cases.
- This slice focuses on the broader upstream `date.test` section-19 day-31 floor/ceiling month-normalization matrix and claims PASS-line growth only, not mapped denominator growth.

Verification:
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateFloorCeilingMonthMatrixCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateFloorCeilingMonthMatrixCorpusTest.php`
- Generic no-domain API guard: not present in this worktree.
- `git diff --check -- lanes/libsqlite`

Dependency closure:
- No new support component is needed. The slice reuses existing native `SQLiteCoreScalarFunction` date/time parsing, `floor`/`ceiling` modifier handling, `strftime`, and SQLite-style type reporting.

# real-upstream-corpus-select-core-dynamic-20260531T024458Z-0

## Scope

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/select8.test`.
- Ported scenario: `select8-1.1`, `select8-1.2`, and `select8-1.3` grouped `SELECT DISTINCT artist,sum(timesplayed) AS total FROM songs GROUP BY lower(artist)` with `LIMIT`/`OFFSET`.
- Added 1,000 dynamic TestRunner cases: 250 seeded song tables times four `LIMIT`/`OFFSET` forms.
- Non-overlap: this extends the existing `SQLiteRealUpstreamSelectCoreDynamicTest.php` file into `select8.test`; the base file already covered select1/select2/select3/select5/select6/select7/selectC/selectE/selectF but had no select8 coverage.

## Behavior Fix

- Initial focused run exposed a real executor gap: grouped summaries only preserved invariant source columns, so selecting bare `artist` after `GROUP BY lower(artist)` failed when a group mixed lower/upper-case artist spellings.
- `SQLiteGroupedAggregate::summarize()` now carries first-row source column values into each grouped summary when those columns are not otherwise present. This matches SQLite's bare-column grouped aggregate behavior for the select8 corpus while preserving explicit aggregate and group-expression summary keys.

## Evidence

- Red-first evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicTest.php` failed with 1000 select8 failures before the grouped bare-column fix.
- Passing focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicTest.php`
- Passing result: `1 test files, 17410 assertions, 0 failures`.
- Expected PASS-line movement: `+1000` focused TestRunner cases, moving lane-local `phpPass` from `1726669` to `1727669`.

## Dependency Closure

- No new support component is needed. The slice reuses the native PHP SELECT parser/executor, grouped aggregate summarizer, numeric aggregate functions, scalar `lower()` expression evaluation, and existing TestRunner harness.

## Follow-Up

- Remaining SELECT core candidates include upstream `selectD.test` parenthesized name-resolution and `select9.test` compound SELECT variants. Avoid duplicating this select8 grouped DISTINCT aggregate LIMIT/OFFSET cluster.

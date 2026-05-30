# real-upstream-corpus-select-core-dynamic batch0

Base accepted HEAD: `49b5c4e4a088c53e02910590cc011ce37a3ffc52`

Micro-slice: `real-upstream-corpus-select-core-dynamic-20260530T185738Z-0`

## Upstream sources

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test`
  - `select6-1.2`: derived table count from a filtered SELECT.
  - `select6-1.3`: DISTINCT inside a derived table counted by the outer SELECT.
  - `select6-2.1`: copied table rows projected through a derived table and filtered by the outer SELECT.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select5.test`
  - `select5-1.*`: grouped aggregate result shape.
  - `select5-2.3`: grouped `HAVING count(*)` filtering.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test`
  - `select2-3.2c`: direct equality predicate lookup behavior.

## Added coverage

- New file: `lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicBatch0Test.php`
- Focused PASS cases: `1001`
- Focused behavior assertions: `5002`
- Expected lane-local `phpPass` delta: `+1001`
- Mapped denominator delta: `0`

The batch uses generic `items` and `copy_items` rows and does not add any WordPress-specific API or fixture surface.

## Non-overlap

This does not add metadata-only rows or generated fake upstream script ids. It extends the already accepted SELECT corpus family with a new batch file and dynamic thresholds over real upstream scenarios, while avoiding accepted select8/select9/selectA compound/limit coverage, select WHERE/GROUP prior batch text, JSON table source/cursor work, and status-only suite evidence.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicBatch0Test.php`
  - `1 test files, 5002 assertions, 0 failures`

Dependency closure: no new support component needed; this reuses the existing lane-local `SQLiteSelectSql` executor, derived table materialization, grouped aggregate, `HAVING`, and equality predicate support.

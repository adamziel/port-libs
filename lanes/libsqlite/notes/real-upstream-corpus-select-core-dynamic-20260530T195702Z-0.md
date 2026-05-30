# real-upstream-corpus-select-core-dynamic-20260530T195702Z-0

Added a real upstream SELECT-core dynamic batch for bounded `VALUES` row
sources, aggregate evaluation, and ordered LIMIT/OFFSET windows.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test`
- `selectG-100`: large `VALUES` input feeding count/sum/avg verification.
- `selectG-110`: large `VALUES` expression stress source, used here as the
  bounded source-truth family for `VALUES` row production without reproducing
  the expensive 100,000-row upstream stress size.

## Behavior

- Added `SQLiteRealUpstreamSelectGValuesDynamicCorpusTest.php` with 1,001
  dynamic `VALUES` source cases plus one source-citation case.
- Each dynamic case varies the `VALUES` payload, verifies `count(*)`,
  `sum(column1)`, `avg(column1)`, descending `ORDER BY`, `LIMIT`, `OFFSET`,
  source row count, and a result fingerprint through `SQLiteSelectSql`.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectGValuesDynamicCorpusTest.php`
  - `1 test files, 5008 assertions, 0 failures`
  - `1002` distinct TestRunner PASS cases

## Non-Overlap

This slice owns the residual `selectG.test` bounded `VALUES` source behavior.
It does not repeat accepted select1/select2/select3/select4/select5/select6/
select7/select8/select9/selectA/selectB/selectC/selectD batches, expression
`ORDER BY`, grouped SELECT text, compound collation/copy batches, JSON table
source/cursor/constraint work, or metadata-only upstream runner rows. Mapped
denominator remains unchanged because `selectG.test` is already present in the
hydrated upstream inventory.

## Dependency Closure

No new support component is needed. The existing bounded `SQLiteSelectSql`
`VALUES` source path, aggregate dispatch, sort, and limit/offset executor are
reused directly.

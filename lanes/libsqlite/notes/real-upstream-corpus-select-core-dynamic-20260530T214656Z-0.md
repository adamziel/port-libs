# real-upstream-corpus-select-core-dynamic-20260530T214656Z-0

Added `SQLiteRealUpstreamSelectCDerivedDistinctDynamicTest.php` as an additive real upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test`
- `selectC-4.2`: projection from a derived `SELECT DISTINCT a, b` subquery preserves the three distinct `(a,b)` rows even when the outer query only selects `a`.
- `selectC-5.3`: compound derived sources can be joined to host rows and ordered by the explicit outer result columns.

Focused PHP coverage:

- 1,002 distinct TestRunner PASS cases.
- 4,010 focused behavior assertions.
- Dynamic generic application row sets vary tenant ids, duplicate distinct keys, compound source numeric ranges, host rows, and ordered result fingerprints over 500 seeds.

Non-overlap:

- This slice extends the later `selectC.test` derived DISTINCT and compound-derived join sections. It does not repeat the accepted `selectC` alias-resolution batch, accepted `select1` through `selectB` core SELECT batches, `selectD` parenthesized joins, `selectH` literal-count coverage, grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/constraint work, B-tree/WAL/VFS storage clusters, or metadata-only upstream runner rows.
- Mapped denominator remains unchanged because `selectC.test` is already present in the hydrated upstream runner-map evidence.

Follow-up:

- The exact upstream `selectC-5.3` `SELECT * ... ORDER BY 1,2` shape still exposes a larger wildcard/ordinal planner gap after joins are attached to the source plan. This ready slice uses explicit outer columns for the same compound derived join result order and leaves the wildcard ordinal fix for a dedicated SELECT planner patch.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectCDerivedDistinctDynamicTest.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCDerivedDistinctDynamicTest.php`
  - Result: `1 test files, 4010 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. The batch reuses existing `SQLiteSelectSql` derived-table materialization, compound SELECT execution, DISTINCT handling, cross join execution, explicit projection, and result ordering behavior.

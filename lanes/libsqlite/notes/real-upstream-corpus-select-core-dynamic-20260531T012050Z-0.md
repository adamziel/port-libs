# real-upstream-corpus-select-core-dynamic-20260531T012050Z-0

Implemented an additive real upstream SELECT core corpus batch from hydrated
upstream SQLite:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`
- `select1-2.5.1`: nullable `count(*)`, `count(a)`, and `count(b)` over rows
  containing alternating NULL values.
- `select1-2.5.2`: nullable count behavior over the one-row `t4` shape.
- `select1-2.5.3`: nullable count behavior after a `WHERE` predicate matches no
  rows.

Added `SQLiteRealUpstreamSelect1NullableCountDynamicTest.php` with 1,054
distinct TestRunner PASS cases and 12,641 focused assertions. The dynamic matrix
varies nullable `a`/`b` row mixes, matching `b='abc'` filters, and no-match
numeric filters while checking the three upstream count projections.

Red-first evidence: the direct combined upstream projection
`SELECT count(*),count(a),count(b) ...` exposed the current bounded
`SQLiteSelectSql` aggregate-summary limitation:
`SQLite SELECT SQL GROUP BY supports one aggregate value column`. This handoff
keeps the admitted coverage green by executing the same three upstream aggregate
projections separately. The next larger SELECT-core unlock is to add multi
aggregate value-column summaries so the combined projection can be admitted
directly.

Non-overlap: this does not repeat the accepted `select1` correlated BETWEEN,
repeated wildcard, select2/select3/select4/select5/select6/select7/select8/
select9/selectA/selectB/selectC/selectD/selectE/selectF/selectG/selectH batches,
grouped SELECT text, expression ORDER BY, JSON table SELECT sources, or
metadata-only runner rows. The narrower behavior is nullable aggregate count
dispatch through parser-level SELECT `WHERE` filtering.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP SELECT parser, predicate filtering, and aggregate executor.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect1NullableCountDynamicTest.php`: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect1NullableCountDynamicTest.php`: `1 test files, 12641 assertions, 0 failures`.
- Focused PASS-line count: `1054`.

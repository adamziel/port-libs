# real-upstream-corpus-select-core-dynamic-20260531T042121Z-0

Added `SQLiteRealUpstreamSelectDDerivedAggregateLeftJoinDynamicTest.php` as an
additive real upstream SELECT core dynamic batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test`
- Scenario: `selectD-4.1`, a `LEFT JOIN` against a derived aggregate subquery
  whose source is an aliased parenthesized `INNER JOIN` group:
  `LEFT JOIN (SELECT count(*) AS cnt, x1.d FROM (t42 INNER JOIN t43 ON d=g) AS x1 ... GROUP BY x1.d)`.

Focused coverage:

- `1003` distinct TestRunner PASS cases.
- `6013` focused behavior assertions.
- One source-citation guard, one canonical `selectD-4.1` row assertion, `1000`
  dynamic seed cases varying left-side row ids, derived join keys, thresholds,
  grouped counts, and unmatched `LEFT JOIN` null-extension behavior, plus one
  dependency-closure guard.

Non-overlap:

- This owns the later `selectD-4.1` derived aggregate `LEFT JOIN` behavior.
- It does not repeat prior selectD parenthesized JOIN name-resolution/table-star
  coverage, accepted selectB derived compound coverage, grouped SELECT text,
  expression `ORDER BY`, JSON table SELECT source/cursor/constraint work, WAL,
  VFS, B-tree, PRAGMA, UPSERT, or metadata-only runner rows.
- Mapped denominator remains unchanged because `selectD.test` is already part
  of the hydrated upstream manifest coverage.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectDDerivedAggregateLeftJoinDynamicTest.php`
  - `1 test files, 6013 assertions, 0 failures`

Dependency closure:

- No new support component is needed; this reuses native `SQLiteSelectSql`
  support for parenthesized join groups, derived table aliases, aggregate
  `GROUP BY`, and `LEFT JOIN` null-extension.

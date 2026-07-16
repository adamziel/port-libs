# Real upstream SELECT core dynamic corpus

Slice: `real-upstream-corpus-select-core-dynamic-20260530T184455Z-0`

Base accepted HEAD: `7e63d4798cb030955a466f3272d59cba9c03648e`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select8.test`

Ported behavior:

- SELECT WHERE filtering over integer equality/range predicates.
- GROUP BY row partitioning with `sum()` and `count()` aggregate projection.
- HAVING aggregate predicates over grouped rows.
- ORDER BY direction and deterministic tie-break ordering.
- LIMIT/OFFSET slicing over grouped and non-grouped SELECT pipelines.

Focused test growth:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreWhereGroupDynamicCorpusTest.php`.
- The test defines independent PHP expected-row calculators instead of deriving expected output from `SQLiteSelectSql`.
- Selected PASS-line delta: `+4721`.
- Behavior assertions: `14164`.
- Mapped denominator movement: none; this is PHP focused PASS growth against already hydrated upstream SELECT source files.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreWhereGroupDynamicCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 14164 assertions, 0 failures
```

Non-overlap:

- Does not duplicate the existing `SQLiteRealUpstreamSelect8LimitOffsetDynamicTest.php` select8-only LIMIT/OFFSET batch or the pre-existing `SQLiteRealUpstreamSelectCoreDynamicCorpusTest.php` select1/select2/select3 coverage.
- Does not touch parser implementation, dashboard files, or mapped denominator artifacts.
- Uses generic `items` application rows only; no domain-specific libsqlite API or fixture names were added.

Dependency closure:

- No new support component is needed. The batch reuses existing native PHP `SQLiteSelectSql` and `SQLiteSelectQuery` behavior.

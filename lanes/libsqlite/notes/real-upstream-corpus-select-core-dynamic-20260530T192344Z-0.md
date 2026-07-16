# real-upstream-corpus-select-core-dynamic-20260530T192344Z-0

Base accepted HEAD: `de394d1a2a5407b1856e89f4b996c5ea3450f50d`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectE.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectF.test`

Behavior ported:

- `selectE.test` sections `1.0` through `1.3`: compound `EXCEPT` set comparison remains binary unless the result expression itself declares a collation, while final `ORDER BY ... COLLATE` only affects output ordering.
- `selectE.test` sections `2.1` and `2.2`: a left-arm `COLLATE nocase` result expression controls `EXCEPT` duplicate comparison even when the expression has no explicit alias and the final ordering is binary.
- `selectF.test` section `2`: compound `UNION ALL` final `ORDER BY 2, 1` preserves nullable copied result values instead of reusing a mutable source slot.

Implementation:

- `SQLiteSelectSql::compoundSelectCollations()` now assigns collations from unnamed collated result expressions to the generated compound output column, fixing the upstream `selectE-2.1`/`selectE-2.2` comparison gap.
- Added `SQLiteRealUpstreamSelectCompoundCollationDynamicTest.php` with independent expected-result calculators for compound comparison and ordering.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCompoundCollationDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 5155 assertions, 0 failures
```

Selected PASS-line growth:

- New focused PASS cases: `1287`.
- Behavior assertions: `5155`.
- Mapped denominator movement: none; this is PHP focused growth over already hydrated upstream SELECT files.

Non-overlap:

- This does not repeat existing `select3`, `select4`, `select8`, `selectB`, grouped SELECT text, expression `ORDER BY`, JSON table, WAL/VFS, B-tree, PRAGMA, or suite-evidence coverage.
- The test uses generic table names only and adds no domain-specific libsqlite APIs.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP `SQLiteSelectSql` and `SQLiteSelectCompound` implementation.

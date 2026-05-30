# Real Upstream SELECT Core Dynamic Corpus

Slice: `real-upstream-corpus-select-core-dynamic-20260530T162141Z-0`

Base accepted HEAD: `72e7cdb1ae891bd4c5cdf5658524a5a35974f525`

Added focused PHP coverage in `SQLiteRealUpstreamSelectCoreDynamicTest.php` for hydrated upstream SQLite files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test`

Covered upstream scenario ranges:

- `select1-1.4` through supported `select1-1.13` projection, wildcard, cross-product, qualified-column, alias self-join, and scalar min/max SELECT behavior.
- Supported `select1-2.0`, `select1-2.2`, `select1-2.4`, `select1-2.5`, `select1-2.7`, `select1-2.8`, `select1-2.10` through `select1-2.17.1` aggregate/scalar aggregate expression behavior.
- Supported `select1-3.1` through `select1-3.8` WHERE comparison and scalar function predicate behavior.
- Supported `select1-4.1` through `select1-4.13` ORDER BY column, expression, constant, and multi-term behavior that is not blocked by wildcard ordinal handling.
- `select3-1.0`, `select3-1.1`, `select3-2.1` through supported `select3-2.4`, supported `select3-4.1` through `select3-4.3`, `select3-6.1` through `select3-6.4`, and `select3-7.1`.
- Additional dynamic row-slice assertions derived from the exact `select3-1.0` upstream data distribution for each `log` bucket and first-N ordered prefixes.

Focused assertion count:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicTest.php`
- Result: `1 test files, 607 assertions, 0 failures`
- PASS-line delta: `+75` focused PHP PASS cases.

Non-overlap:

- This does not add fake denominator rows or metadata-only admissions.
- It avoids accepted date/VFS/window corpus coverage and old static SELECT helper-only smoke paths.
- It exercises parser-level `SQLiteSelectSql` execution against real upstream SELECT projection, join, WHERE, ORDER BY, aggregate, GROUP BY, HAVING, and dynamic row-distribution scenarios.

Exclusions/follow-up:

- The current port still lacks several upstream SELECT behaviors exposed while building this slice: `count()` with zero arguments, multiple aggregate result columns in one SELECT, aggregate min/max wrapped inside scalar `coalesce()`, repeated aggregate output aliases, SQLite REAL affinity for mixed `sum(a)`, `GROUP BY` output aliases/ordinals beyond currently supported cases, HAVING-without-GROUP row visibility, and wildcard ordinal ORDER BY.
- Those rows were not admitted in this batch; they are the next implementation-backed SELECT corpus targets.

Dependency closure:

- No new support component is needed. The slice reuses lane-local `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectProjection`, `SQLiteSelectExpression`, `SQLiteSelectPredicate`, and aggregate/window helpers.

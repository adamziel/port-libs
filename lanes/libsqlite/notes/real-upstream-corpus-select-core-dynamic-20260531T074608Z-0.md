# real-upstream-corpus-select-core-dynamic-20260531T074608Z-0

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`

Ported behavior:

- `select1-6.2` through `select1-6.7`: result-column names from `AS` aliases,
  quoted aliases, expression aliases, and joined-source aliases.
- `select1-6.3.1`: quoted projection aliases preserve non-identifier text,
  including a trailing space inside the quoted alias.

Implementation:

- `SQLiteSelectSql::expressionAlias()` now accepts quoted projection aliases
  without revalidating the unquoted text as a simple identifier. Unquoted aliases
  still use the existing simple-identifier guard.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect1ColumnNamesDynamic20260531Test.php`
- Result: `1 test files, 5354 assertions, 0 failures`
- PASS cases: `1004` (`1` upstream source citation, `1002` dynamic behavior
  cases, `1` non-overlap summary)

Non-overlap:

- This slice owns the `select1.test` column-name/quoted-alias cluster.
- It does not repeat accepted select1 wildcard/count/correlated BETWEEN/
  compound-IN batches, select4 compound/subquery/yield batches, selectD
  parenthesized/derived/USING joins, selectE/selectF compound collation/copy
  batches, grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/
  constraint work, or storage/VFS batches.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `SQLiteSelectSql` parser/executor and the hydrated upstream SQLite corpus.

Mapped denominator:

- Unchanged. `select1.test` is already part of the hydrated upstream manifest
  inventory; this patch adds focused PHP PASS-line growth and a parser behavior
  fix rather than new mapped-denominator rows.

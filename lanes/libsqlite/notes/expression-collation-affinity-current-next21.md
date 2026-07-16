# Expression Collation/Affinity Current Next21

Status delta: added 30 verified focused PASS lines in
`SQLiteExpressionCollationAffinityCurrentNext21Test.php`.

Behavior:
- simple `CASE` branch matching now uses SQLite-style scalar comparison instead
  of exact value keys;
- explicit `COLLATE BINARY`, `NOCASE`, or `RTRIM` on the CASE base or a `WHEN`
  expression participates in branch selection;
- NUMERIC/TEXT/BLOB casts keep SQLite storage-class comparison behavior for
  CASE branch matching; and
- SELECT projection dispatch reuses `SQLiteSelectExpression` for CASE so
  projection and predicate/window expression paths stay consistent.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteExpressionCollationAffinityCurrentNext21Test.php
```

Result: `1 test files, 30 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectCaseWindowCurrentNext18Test.php lanes/libsqlite/tests/SQLiteExpressionCollationAffinityCurrentNext21Test.php
```

Result: `2 test files, 100 assertions, 0 failures`.

```sh
php lanes/libsqlite/examples/application-expression-collation-affinity-current-next21.php
```

Result: copied `wp_options` rows bucketed with NOCASE simple CASE labels and
NUMERIC CAST branch matching.

Dashboard delta:
- `lane-status.json` `phpPass` moves from `7262` to `7292`.
- `benchmarkDenominator.mapped` is unchanged because this is focused PHP
  behavior coverage, not a newly mapped upstream inventory unit.

Dependency closure: no new support component is needed; this reuses
lane-local SELECT parsing, scalar expression evaluation, built-in collation
comparison, and CAST affinity handling.

Non-overlap: this avoids accepted SQL expression `ORDER BY`, Unicode GLOB,
SELECT subquery/GROUP BY text execution, JSON table source/cursor/hidden or
visible constraints, VFS/WAL transaction application, B-tree page/freelist
clusters, and standalone predicate-collation work. The slice is limited to
simple CASE branch matching semantics inside SELECT expressions.

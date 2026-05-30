# sql-dml-returning-conflict-current-next70

## Status delta

Adds bounded parser/executor support for `INSERT ... ON CONFLICT DO UPDATE ... RETURNING` expression projections over the final inserted or updated row. The slice keeps existing column, alias, and `*` projection behavior, rejects `excluded.*` in `RETURNING`, and fixes top-level binary-expression splitting around string literals so current-row concatenation assignments such as `wp_options.touched || '>' || excluded.touched` evaluate correctly.

## Focused evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningExpressionCurrentNext70Test.php
```

Expected focused result after this patch:

```text
1 test files, 53 assertions, 0 failures
```

New PASS-line delta: `+53`.

## Application smoke

`lanes/libsqlite/examples/application-upsert-returning-expression-current-next70.php` previews copied `wp_options` import rows using UPSERT conflict updates with `RETURNING` expressions such as `hits + 1 AS next_hits` and `option_name || ':' || touched AS label`, without requiring `ext/sqlite`.

## Non-overlap

This avoids accepted batch68 and batch69 surfaces: trigger/FK/recursive UPSERT/RETURNING savepoint behavior, `UPDATE FROM` current conflict behavior, `UPDATE/DELETE RETURNING ORDER BY/LIMIT`, INSERT SELECT conflict execution, JSONB CHECK admission, LIKE current/next ranges, VFS/WAL/pager/B-tree handoffs, and suite-denominator evidence. This slice is only non-trigger SQL DML RETURNING expression evaluation for current final rows after UPSERT conflict resolution.

## Dependency closure

No new support component is needed. The existing bounded `SQLiteUpsertReturningSql` and `SQLiteUpsertDoUpdateWherePlan` helpers are reused; no native extension, VFS, or external SQLite binary is required.

## Next task

If this is accepted, a later SQL DML slice can add additional parser-level expression forms for `RETURNING` only when backed by focused current-base tests, such as `CASE`, `CAST`, or scalar functions, without duplicating this arithmetic/concatenation/coalesce current-row cluster.

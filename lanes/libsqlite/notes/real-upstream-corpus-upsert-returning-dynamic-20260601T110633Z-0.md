# Real Upstream UPSERT ALTER RENAME Dynamic Corpus

## Source Truth

- Upstream SQLite file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/altercol.test`
- Ported sections:
  - `altercol.test` `9.4`: trigger bodies with `INSERT ... ON CONFLICT (_x_) WHERE _x_>10 DO UPDATE SET _x_ = _x_+1` and `DO NOTHING` must be rewritten when `_x_` is renamed.
  - `altercol.test` `16.1.3` through `16.1.6`: trigger body `ON CONFLICT(d) DO UPDATE SET f = excluded.f` must survive renaming target column `f` to `"big f"` and source column `c` to `"big c"`.
- Cross-check source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/altertab.test` `24.1`, which keeps UPSERT trigger bodies inside ALTER TABLE schema rewrite error paths.

## Red-First Evidence

Before this patch, the quoted renamed-column case from `altercol.test` `16.1.5` was rejected by the schema-text rewriter:

```bash
php -r 'require "tools/bootstrap.php"; use PortLibs\LibSqlite\SQLiteAlterTableRenamePlan; try { echo SQLiteAlterTableRenamePlan::renameColumnSql("CREATE TRIGGER tr1 AFTER INSERT ON t1 BEGIN INSERT INTO t2 VALUES(new.a,new.b,new.c) ON CONFLICT(d) DO UPDATE SET f=excluded.f; END", "t2", "f", "big f"), "\n"; } catch (Throwable $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }'
```

Output:

```text
InvalidArgumentException: SQLite ALTER TABLE rename new column name is malformed
```

## Implementation Delta

- `SQLiteAlterTableRenamePlan::renameColumnSql()` now accepts bounded column identifiers that require quoting, while keeping table names and malformed column names guarded.
- Unquoted schema tokens are rendered as double-quoted identifiers when the renamed column contains a supported space, matching the upstream `"big f"` / `"big c"` ALTER TABLE cases.
- No new support component was needed; the patch reuses the existing schema-text tokenizer and rewrite pass.

## Focused Verification

```bash
php -l lanes/libsqlite/src/SQLiteAlterTableRenamePlan.php
```

Result: no syntax errors.

```bash
php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertAlterRenameDynamicTest.php
```

Result: no syntax errors.

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertAlterRenameDynamicTest.php
```

Result: `1 test files, 5263 assertions, 0 failures` with 1006 focused PASS cases.

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAlterTableRenameTriggerViewCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertAlterRenameDynamicTest.php
```

Result: `2 test files, 5361 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
```

Result: `1 test files, 5 assertions, 0 failures`.

## Non-Overlap

This slice avoids the saturated direct UPSERT/RETURNING corpus already covered by `upsert1.test`, `upsert2.test`, `upsert3.test`, `upsert4.test`, `upsert5.test`, and `returning1.test` batches. It owns the adjacent real upstream ALTER TABLE schema-rewrite behavior for UPSERT trigger bodies with renamed conflict targets, `excluded` references, and `new` references.

## Dependency Closure

No new native PHP support component is required. The implementation extends the existing bounded `SQLiteAlterTableRenamePlan` column rewrite path and keeps the no-domain API guard green.

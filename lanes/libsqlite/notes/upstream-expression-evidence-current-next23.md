### 2026-05-27 upstream expression evidence current-next23

Scope: added `SQLiteUpstreamSuiteEvidence::upstreamExpressionEvidenceMatrix()`
to make the next expression-focused upstream Tcl subset explicit and
machine-readable without launching a duplicate broad runner from this isolated
worktree.

The matrix groups 15 concrete upstream `.test` scripts:

- `core-expression`: `expr.test`, `e_expr.test`, `func.test`, `func2.test`
- `affinity-cast-collation`: `cast.test`, `types2.test`, `collate1.test`,
  `collate2.test`
- `predicate-pattern`: `where.test`, `where2.test`, `like.test`, `in.test`
- `case-null-rowvalue`: `case.test`, `null.test`, `rowvalue.test`

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamExpressionEvidenceCurrentNext23Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 64 assertions, 0 failures
```

PASS delta: +64 focused PHP PASS lines. `lane-status.json` `phpPass` moves
from 8166 to 8230. `benchmarkDenominator.mapped` is unchanged because this
slice records runnable expression-subset evidence planning and missing-cache
gates, not a fresh hydrated upstream Tcl run.

Dependency closure: no new support component is needed. The evidence matrix
reuses lane-local manifest ledgers and the existing SQLite testfixture subset
command planner.

Non-overlap: this avoids accepted batch21 behavior clusters and does not
repeat SELECT SQL expression ORDER BY, GROUP BY/HAVING, subqueries, JSON table
source/cursor/constraint work, VFS/WAL/B-tree storage application, Unicode
GLOB, or release/all admission ledger reshaping. It is a distinct
upstream-suite expression subset readiness artifact.

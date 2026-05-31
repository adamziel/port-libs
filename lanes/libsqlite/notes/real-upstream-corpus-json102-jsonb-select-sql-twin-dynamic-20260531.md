# Real Upstream JSON102 JSONB SELECT SQL Twin Dynamic Corpus

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T224226Z-0`

Base accepted HEAD: `33a65237308053a0654b3629f3bffe8d77c73515`

## Source Truth

Hydrated upstream file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`

Ported upstream sections:

- `json102-120-4` and `json102-120-3` JSONB constructors nested through object construction.
- `json102-370-4`, `json102-380-4`, and `json102-400-8` JSONB mutation through `jsonb_set()`.
- `json102-290-4` and `json102-280b` JSONB extraction and scalar extraction.
- `json102-440-4`, `json102-450-4`, `json102-460-4`, `json102-445-6`, `json102-490-4`, and `json102-500-2` JSONB remove path order, huge-index no-op, member removal, and root removal.
- `json102-510b` through `json102-600b` JSONB type inspection.
- `json102-230b` JSONB array length.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102JsonbSelectSqlTwinDynamic20260531Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 12511 assertions, 0 failures
```

The test file contributes 502 focused TestRunner PASS cases: 500 dynamic upstream behavior cases plus upstream-source citation and dependency-closure evidence cases.

## Non-Overlap

This slice covers parser-level `SQLiteSelectSql` execution of real upstream JSONB twin rows from `json102.test`. It is not direct helper-only JSON coverage and does not repeat accepted JSON table cursor/source wiring, hidden/visible constraint extraction, JSON table host joins, JSON dynamic remove/patch/array-insert helper clusters, or JSON table planner metadata clusters.

## Dependency Closure

No new support component is needed. The slice reuses the existing `SQLiteSelectSql` parser/executor path and existing JSON1/JSONB helper implementations.

Root harness was not run for this isolated micro-slice.

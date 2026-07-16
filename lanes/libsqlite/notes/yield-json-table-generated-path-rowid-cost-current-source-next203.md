# JSON table generated path rowid cost current-source next203

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next203`

Status: focused PHP behavior growth for parser/current-source `json_tree()` generated-path rowid xColumn cache projection when the SELECT list asks for SQLite rowid aliases.

Implemented:

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext203()`.
- Normalizes `rowid`, `_rowid_`, and `oid` to the cached `id` xColumn for the accepted next196 generated-path rowid cache while preserving the originally requested alias columns in a separate alias projection tape.
- Records alias projection reuse, alias opcode, rowid alias values, alias cost class, transitions, fingerprints, and next-source replan reasons.
- Keeps stale next-source behavior conservative: alias projection reuse is allowed only when the underlying next196 xColumn cache is reusable and all alias rows are materialized.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext203Test.php
1 test files, 58 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next203.php --self-test
application-json-table-generated-path-rowid-cost-current-source-next203 self-test passed
```

Non-overlap:

This avoids accepted JSON table cursor/source wiring, hidden/visible constraints, generated-path rowid cost/cache/yield/xNext/xColumn layers through next196, and accepted batch183 next196 generated path/rowid/cost behavior. The new behavior is narrower: rowid-alias projection over an already admitted generated-path xColumn cache.

Dependency closure:

No new support component is needed. The slice reuses native PHP JSON table row generation, generated-path rowid xColumn cache materialization, and existing rowid alias semantics.

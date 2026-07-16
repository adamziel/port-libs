# JSON table generated path rowid cost current-source next202

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next202`

## Status

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext202()`.
- Extends the accepted generated-path rowid pinned-source layer with an `xNext` admission profile.
- Records source fingerprint matching, previous rowid, remaining rowids before `xNext`, emitted rowids, blocked rowids after `xNext`, EOF, opcode, cost class, and replan reasons.
- Prevents copied `wp_options` JSON diagnostics from advancing a pinned generated-path rowid cursor when the next source is unpinned or its source fingerprint is stale.

## Verification

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext202Test.php
php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next202.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext202Test.php
php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next202.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result:

```text
1 test files, 63 assertions, 0 failures
```

Application smoke:

```text
application-json-table-generated-path-rowid-cost-current-source-next202 self-test passed
```

## Non-Overlap

This does not repeat accepted JSON table cursor/source wiring, hidden/visible constraint extraction, generated path rowid xColumn cache through next196, pinned source next194, current-source resume next185, JSON dynamic joins, storage/VFS/B-tree/WAL surfaces, or accepted batch183 JSON generated path/rowid/cost behavior. The new behavior is specifically the `xNext` continuation decision after a generated-path rowid cursor has pinned a current-source row.

## Dependency Closure

No new support component is needed. This reuses native PHP JSON table row generation, generated-path rowid costing, pinned current-source fingerprints, and xColumn snapshot metadata.

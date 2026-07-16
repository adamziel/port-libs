# JSON table generated path rowid cost current-source next196

Status: focused PHP behavior growth for current-source JSON table generated-path rowid xColumn cache reuse.

Behavior:
- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext196()`.
- Builds on the accepted next191 xFilter recheck profile and admits an xColumn cache only when checkpoint rowids, accepted rowids, filter fingerprint, projection, and materialized projected rows all remain stable.
- Forces a next-source reprepare when copied `wp_options` JSON source generation/path changes make the xFilter checkpoint stale.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext196Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 55 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next196.php --self-test
application-json-table-generated-path-rowid-cost-current-source-next196 self-test passed
```

Non-overlap:
- Avoids accepted JSON table SELECT source/cursor wiring, hidden/visible constraint extraction, next175 cache-generation profiles, next185 resume checkpoints, next188 deleted-resume restart, next190 xColumn yield rows, and next191 xFilter recheck behavior.
- This slice is limited to the xColumn cache-admission layer above next191.

Dependency closure:
- No new support component is needed. The slice reuses native JSON table generated-path rowid planning, xFilter checkpoint rechecks, and xColumn projection materialization.

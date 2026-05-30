# PRAGMA index_xinfo / foreign-key current-source next166

## Behavior

This slice extends the current-source `PRAGMA index_xinfo` plus foreign-key
catalog bridge so derived `PRAGMA foreign_key_list(...)` rows preserve action,
`MATCH`, and deferrability metadata in the source hash and public page
summary.

It covers:

- `ON UPDATE` / `ON DELETE` action counts from catalog-derived FK rows.
- `MATCH` names from `PRAGMA foreign_key_list(...)`.
- DDL-only `DEFERRABLE INITIALLY DEFERRED`, `DEFERRABLE INITIALLY IMMEDIATE`,
  and `NOT DEFERRABLE` state.
- Source-id and FK-source hash changes when FK action DDL changes while table
  data and `index_xinfo` rows remain otherwise compatible.
- Existing parent-index admission, implicit parent primary-key resolution,
  `foreign_key_check` violations, pagination, stale cursor rejection, and
  table-valued `pragma_index_xinfo(...)` dispatch.

The Application smoke models copied multisite `wp_options` imports where action
semantics affect whether a resumable diagnostic is still describing the same
current catalog image.

## Focused evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
61 PASS lines
1 test files, 69 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next166.php --self-test
application-pragma-index-xinfo-foreignkey-current-source-next166 self-test passed
```

## Non-overlap

This does not repeat accepted next156-next163 `index_xinfo`/FK current-source
coverage for explicit parent columns, catalog-derived FK rows, implicit parent
primary keys, or root/quickcheck pagination. The new behavior is action,
`MATCH`, and deferrability metadata preservation in the FK-derived source and
resume contract.

## Dependency closure

No new support component is needed. The slice reuses the existing schema
catalog, `foreign_key_list`, `index_xinfo`, parent-index admission, and
`foreign_key_check` helpers.

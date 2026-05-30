# Attach Schema Shadowing Current Next16

This slice adds bounded attached-schema catalog alias behavior for SQLite's
per-database `sqlite_schema` / `sqlite_master` tables.

Behavior covered:

- Bare `sqlite_schema` and `sqlite_master` resolve to `main`, even when TEMP or
  attached user tables would otherwise win ordinary unqualified lookup.
- Bare `sqlite_temp_schema` and `sqlite_temp_master` resolve to `temp`.
- Schema-qualified `main.sqlite_schema`, `temp.sqlite_master`, and attached
  `site.sqlite_schema` / `archive.sqlite_master` resolve to that exact schema.
- Detached schemas no longer own catalog aliases.
- Ordinary `wp_options` lookup remains TEMP-first and attach-order preserving.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachSchemaShadowingCurrentNext16Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 46 PASS lines
# 1 test files, 62 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempSchemaCorpusTest.php lanes/libsqlite/tests/SQLiteAttachSchemaShadowingCurrentNext16Test.php
# Focused test run: 2 selected test files (root lock skipped)
# 72 PASS lines
# 2 test files, 121 assertions, 0 failures

php -l lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php
# No syntax errors detected in lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php
php -l lanes/libsqlite/tests/SQLiteAttachSchemaShadowingCurrentNext16Test.php
# No syntax errors detected in lanes/libsqlite/tests/SQLiteAttachSchemaShadowingCurrentNext16Test.php
php -l lanes/libsqlite/examples/application-attach-schema-shadowing-current-next16.php
# No syntax errors detected in lanes/libsqlite/examples/application-attach-schema-shadowing-current-next16.php

php lanes/libsqlite/examples/application-attach-schema-shadowing-current-next16.php
# unqualified_wp_options_schema=temp, unqualified_sqlite_schema_schema=main,
# temp_catalog_alias_schema=temp, site_catalog_alias_schema=site,
# site_catalog_alias_root=1

git diff --check -- lanes/libsqlite
# clean
```

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local attached-schema catalog and schema-record primitives.

Non-overlap: this does not repeat accepted temp object shadowing, schema PRAGMA
catalog wrapping, JSON table source/cursor/constraint work, VFS sync/lock/write
clusters, WAL checkpoint/savepoint/rollback clusters, B-tree page move/root
collapse/overflow release, Unicode GLOB, or SELECT SQL subquery/grouping/
expression ORDER BY behavior.

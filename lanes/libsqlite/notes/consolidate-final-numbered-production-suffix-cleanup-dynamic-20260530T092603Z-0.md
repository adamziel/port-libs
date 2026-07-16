# Final Numbered Production Suffix Cleanup Dynamic

Scope: consolidated the PRAGMA index_xinfo/foreign-key catalog `159` production
entry point and its private catalog parsing helpers to stable unsuffixed names.
Direct `next159` and implicit-parent `next162` tests/examples now call the
canonical helpers. Observable scenario names, status keys, dependency strings,
and error text were preserved.

Root-gate precheck on this accepted base:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 54 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext78Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext93Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext98Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext103Test.php
# 4 test files, 155 assertions, 0 failures
```

Focused verification:

```bash
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext159Test.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyImplicitParentCurrentSourceNext162Test.php
php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next159.php
php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-implicit-parent-current-source-next162.php
# all reported no syntax errors

php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext159Test.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyImplicitParentCurrentSourceNext162Test.php
# 2 test files, 151 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/libsqlite/tests | rg 'SQLitePragmaIndexXinfoForeignKey.*CurrentSourceNext.*Test\.php' | sort)
# 169 test files, 13929 assertions, 0 failures

php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next159.php
# status ok, next_ready true, delta_total_blockers -3

php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-implicit-parent-current-source-next162.php --self-test
# application-pragma-index-xinfo-foreignkey-implicit-parent-current-source-next162 self-test passed

git diff --check -- lanes/libsqlite
# clean
```

Dependency closure: no new support component is needed; this reuses the
existing native PRAGMA schema catalog, `PRAGMA foreign_key_list`, and
`PRAGMA index_xinfo` row materialization.

Non-overlap: this is limited to canonicalizing the existing PRAGMA
index_xinfo/foreign-key catalog helper surface. It does not repeat the
root-gate suite-evidence/window fixes, JSON table, VFS/WAL, B-tree, STAT4, or
release-runner consolidation work.

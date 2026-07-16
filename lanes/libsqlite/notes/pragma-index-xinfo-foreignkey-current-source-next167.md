# PRAGMA index_xinfo / foreign_key_list Current-Source Next167

- Adds a bounded current-source helper for PRAGMA `index_xinfo` plus derived PRAGMA `foreign_key_list` diagnostics that preserves upstream `on_update`, `on_delete`, and `match` metadata.
- Application smoke: copied `wp_options` rows repaired in the next source now report FK action changes alongside existing casefolded table/column handling and FK violation clearance.
- Non-overlap: reuses accepted next156/next161/next164 pagination, implicit-parent, and casefolding behavior; this slice only adds action/match metadata and action-change summaries.
- Dependency closure: no new support component is needed; existing `SQLitePragmaSchemaCatalog`, FK check, and `index_xinfo` helpers are reused.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 77 assertions, 0 failures

PASS-line delta verified with:
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php | tee /tmp/pragma167.out >/dev/null; rg -c '^PASS ' /tmp/pragma167.out; tail -1 /tmp/pragma167.out
69
1 test files, 77 assertions, 0 failures
```

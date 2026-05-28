# JSON Table Rowid Hidden Path Current Source Next138

This slice adds `SQLiteJsonTablePlan::currentSourceRowidHiddenPathNext138()`, a bounded current-source planner profile that composes accepted nested JSON table rowid scoping with hidden path/fullkey constraint tapes. It records path and rowid constraint signatures, intersected rowids, root-relative fullkeys, hidden path tape entries, cost class, current/next transitions, and replan reasons.

WordPress relevance: copied `wp_options` plugin settings often query nested JSON rule arrays by path and rowid-like cursor identity. The smoke models a plugin rule import where `json_tree()` stays pinned to the current hidden path/rowid tape while the next source changes JSON text and must prepare the next cursor source.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTablePlan.php

php -l lanes/libsqlite/tests/SQLiteJsonTableRowidHiddenPathCurrentSourceNext138Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableRowidHiddenPathCurrentSourceNext138Test.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableRowidHiddenPathCurrentSourceNext138Test.php
1 test files, 68 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-json-table-rowid-hidden-path-current-source-next138.php --self-test
wordpress-json-table-rowid-hidden-path-current-source-next138 self-test passed

git diff --check -- lanes/libsqlite
<no output>
```

Dashboard delta: `phpPass` +68, from `59517` to `59585`, from the exact focused assertion count. Mapped upstream coverage remains conservative because this composes already mapped JSON table hidden path, rowid, and current-source planner behavior.

Dependency closure: no new support component is needed; this reuses the existing native JSON path, JSON tree, and JSON table planner components.

Non-overlap: avoids accepted JSON table SELECT source/cursor, hidden constraint extraction, visible constraint pushdown, nested path rowid next133, generated hidden cost next136, hidden rowid order next135, and batch135 hidden-rowid ORDER behavior by covering their uncovered composition: current-source hidden path/fullkey constraint tapes intersected with rowid aliases and root-relative fullkeys.

# PRAGMA index_xinfo / foreign-key current-source next253

This slice adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253`, layered
on the accepted next250 PRAGMA/FK page. It reports `SET NULL` and
`SET DEFAULT` foreign-key actions whose child columns are generated columns
visible through `PRAGMA table_xinfo` but hidden from `PRAGMA table_info`.

Behavior:

- joins `PRAGMA foreign_key_list` action rows to generated child columns from
  `table_xinfo`;
- flags `SET NULL` against generated `NOT NULL` child columns;
- flags `SET DEFAULT` against generated `NOT NULL` child columns whose default
  resolves to `NULL`;
- preserves nullable generated child actions as non-blocking rows;
- includes the generated-child action summaries in source hashing and resume
  cursor validation.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253Test.php`
  - `1 test files, 57 assertions, 0 failures`
  - `49` focused PASS lines
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-generated-child-action-current-source-next253.php`
  - reports `current_generated_child_action_blockers: 2`,
    `next_generated_child_action_blockers: 0`, and `repaired: true`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext253Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-generated-child-action-current-source-next253.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-generated-child-action-current-source-next253.php`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Expected dashboard movement: `phpPass +49` PASS lines from `134837` to
`134886` (`57` assertions). Mapped upstream coverage remains unchanged because
this is focused PHP behavior over the already mapped PRAGMA `index_xinfo` /
`foreign_key_list` family.

Non-overlap:

- avoids accepted next249/next250 generated child-column visibility coverage by
  checking action semantics for generated child columns rather than visibility
  alone;
- avoids accepted next213 and next247 action/default checks by requiring
  `table_xinfo` generated-column rows that `table_info` omits;
- avoids accepted parent UNIQUE, collation, rowid-alias, generated-parent,
  deferral, match-name, and child-index PRAGMA/FK clusters.

Dependency closure:

No new support component is needed. The slice reuses `SQLiteSchemaRecord`,
`SQLitePragmaSchemaCatalog::tableInfo(..., true)`, accepted
`foreign_key_list` extraction, and the current-source PRAGMA pagination chain.

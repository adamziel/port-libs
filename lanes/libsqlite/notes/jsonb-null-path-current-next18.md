# JSONB NULL path current-next18

- Behavior: SQLite JSON mutation functions ignore NULL path/value pairs, while
  JSON remove functions return SQL NULL if any remove path is NULL. This slice
  wires that behavior through native PHP SQL argument dispatch for `json_set`,
  `json_insert`, `json_replace`, `jsonb_set`, `jsonb_insert`,
  `jsonb_replace`, `json_remove`, and `jsonb_remove`.
- Evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbNullPathCurrentNext18Test.php`
  passed with `1 test files, 25 assertions, 0 failures` and 20 PASS lines.
- `lane-status.json` `phpPass`: `6121 -> 6141` from the exact verified PASS-line
  delta in this isolated worktree.
- Upstream mapping: no denominator movement. Local `sqlite3` oracle checks
  confirmed text JSON behavior for NULL mutation/remove paths; JSONB dispatch
  mirrors the same SQL path semantics in the native codec.
- Application smoke: `php lanes/libsqlite/examples/application-jsonb-null-path-current-next18.php`
  reports copied `active_plugins` JSONB settings where optional NULL path
  filters are skipped during mutation and return SQL NULL for removal.
- Non-overlap: avoids accepted JSON table SQL NULL path handling, JSON hidden
  and visible constraint pushdown, JSON table cursor/source wiring, JSONB
  mutation current-index paths, and reverse-path extraction/index work.
- Dependency closure: no new support component is needed; this reuses the
  existing native JSONB codec, JSON mutation/remove dispatch, and SELECT SQL
  expression evaluator.

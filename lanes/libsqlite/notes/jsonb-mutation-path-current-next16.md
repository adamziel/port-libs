# JSONB mutation current-path next16

- Scope: focused upstream-backed JSON mutation path coverage for `json_array_insert()` / `jsonb_array_insert()` current array indexes from SQLite `json109.test`, plus nested path creation from `json101.test` through both text JSON and JSONB dispatch.
- Non-overlap: avoids accepted JSON table cursor/source/hidden/visible constraint work, JSON aggregate/window work, malformed JSONB planner work, and earlier broad JSON path mutation corpus cases by isolating current-index array insertion and nested mutation creation behavior.
- Application smoke: `examples/application-jsonb-mutation-current-path.php` applies copied `wp_options.active_plugins` JSONB queue edits with `$.queue[#-1]`, `$.queue[#]`, and nested metadata creation.
- Dependency closure: no new support component needed; this reuses the existing native PHP JSONB encoder/decoder, JSON5 parser, and mutation dispatchers.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbMutationPathCurrentNext16Test.php` => `1 test files, 82 assertions, 0 failures` with 58 PASS lines.
  - `php lanes/libsqlite/examples/application-jsonb-mutation-current-path.php` => passed and printed the copied `active_plugins` JSONB queue with pre-last insertion, append insertion, and nested metadata creation.
  - `php -l lanes/libsqlite/src/SQLiteJsonMutation.php && php -l lanes/libsqlite/tests/SQLiteJsonbMutationPathCurrentNext16Test.php && php -l lanes/libsqlite/examples/application-jsonb-mutation-current-path.php` => no syntax errors.
  - `git diff --check -- lanes/libsqlite` => passed.

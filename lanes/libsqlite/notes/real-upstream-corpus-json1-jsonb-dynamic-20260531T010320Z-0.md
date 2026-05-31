# real-upstream-corpus-json1-jsonb-dynamic-20260531T010320Z-0

Base accepted HEAD: `db598d2f37de4eb8809eabdfe8470ae863639e6e`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`

Behavior ported:

- `json107-1.1..2.1`: text-looking BLOB compatibility for `json_valid()`,
  `->`, `->>`, `json_extract()`, `json_type()`, `json_array_length()`,
  `json()`, and `json_tree()` when routed through the SELECT expression
  dispatcher.
- `json109-1.1..2.8`: `json_array_insert()` and `jsonb_array_insert()`
  current-index, append, reverse-index, and out-of-range behavior, including
  SELECT expression dispatch.
- `json105-1.*..6.*`: dynamic reverse array path remove and insert behavior
  through SELECT expression JSON/JSONB function dispatch.

Implementation delta:

- `SQLiteSelectExpression` now dispatches `json_array_insert()` and
  `jsonb_array_insert()` to the existing native `SQLiteJsonArrayInsert`
  helper. The red-first failure was `Unsupported SQLite core scalar function:
  json_array_insert`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicExpansion20260531Test.php`
  passed with `1 test files, 7953 assertions, 0 failures`.
- New focused PASS-line growth: `+1002`.
- `lanes/libsqlite/lane-status.json` `phpPass` moved from `1408188` to
  `1409190` for the new focused PASS lines.

Non-overlap:

- This is not metadata admission and does not add fake upstream script ids.
- It avoids accepted JSON table cursor/source/hidden/visible constraint work,
  JSON table host joins, JSON102 operator-only rows, JSON103 aggregate/window
  expansion, JSON501/502 JSON5 behavior, and prior direct helper-only
  json105/json107/json109/jsonb01 dynamic rows.
- The new behavior is specifically SELECT expression dispatch for
  `json_array_insert()`/`jsonb_array_insert()` plus expanded JSON1/JSONB
  parity assertions grounded in the upstream JSON files above.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `SQLiteJsonArrayInsert`, `SQLiteJsonValidity`, `SQLiteJsonExtract`,
  `SQLiteJsonInspection`, `SQLiteJsonTree`, `SQLiteJsonCanonical`, and
  `SQLiteSelectExpression` components.

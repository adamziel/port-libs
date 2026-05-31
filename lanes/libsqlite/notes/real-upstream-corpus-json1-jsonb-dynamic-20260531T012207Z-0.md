# real-upstream-corpus-json1-jsonb-dynamic-20260531T012207Z-0

Base accepted HEAD: `9c01a66e5dc81444d443e06defaf90851a98b56e`.

Ported a focused real-upstream JSON1/JSONB dynamic cluster from the hydrated
SQLite checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Upstream scenarios: `json101-21.1-correct` through `json101-21.27`
- Added focused coverage for NULL propagation through `json_valid`,
  `json_error_position`, `json`, `json_extract`, `json_insert`, `->`, `->>`,
  `json_patch`, `json_remove`, `json_replace`, `json_set`, `json_type`,
  `json_quote`, `json_each`, `json_tree`, `json_group_array`, and
  `json_group_object`, plus JSONB parity for the applicable constructor,
  mutation, patch, remove, and aggregate paths.

Focused evidence:

- Before: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicTest.php`
  passed with `1 test files, 721 assertions, 0 failures`.
- After: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicTest.php`
  passed with `1 test files, 758 assertions, 0 failures`.
- Focused assertion delta: `+37` real upstream behavior assertions.

Non-overlap:

- This does not repeat accepted JSON table cursor/source/hidden/visible
  constraint coverage, JSONB remove/path matrix coverage, JSON aggregate
  window/order/distinct clusters, or json101 constructor/mutation cases already
  present in the dynamic file.
- No new support component is needed; the slice reuses existing JSON helper
  implementations.

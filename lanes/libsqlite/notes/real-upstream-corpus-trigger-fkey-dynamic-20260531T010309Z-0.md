# Real Upstream Corpus Trigger/FK Dynamic

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T010309Z-0`
Base accepted HEAD: `db598d2f37de4eb8809eabdfe8470ae863639e6e`

Added a bounded native trigger `UPDATE FROM` plan plus
`SQLiteRealUpstreamTriggerFkeyDynamicTriggerUpdateFromTest.php`, a real
upstream corpus test over generic target/source rows.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerupfrom.test`
  - `triggerupfrom-1.0..1.3`: `AFTER INSERT` trigger runs `UPDATE ... FROM`
    against the newly inserted row.
  - `triggerupfrom-2.1..2.2`: non-temp triggers cannot reference attached
    database objects, while TEMP triggers can.
  - `triggerupfrom-2.3..2.4`: `BEFORE DELETE` trigger program can run
    `UPDATE ... FROM`, with persisted schema errors caught on reload.
  - `triggerupfrom-3.0`: attached-schema trigger resolves unqualified source
    tables in the trigger schema.
  - `triggerupfrom-4.2..4.3`: `INSTEAD OF UPDATE` view triggers receive OLD
    and NEW rows from `UPDATE ... FROM`.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerUpdateFromTest.php`
  - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerUpdateFromTest.php`
  - `1 test files, 4231 assertions, 0 failures`
  - `4231` focused PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed.

Expected lane-local movement: `+4231` focused PASS lines, from `1408188` to
`1412419` if accepted. Mapped coverage remains `1589 / 1589`.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local trigger/FK dynamic plan surface and generic row-array
execution primitives.

Non-overlap: this does not repeat accepted trigger7, trigger8, trigger9,
triggerA/B/C/D/E/F/G, fkey1..fkey8, RETURNING/UPSERT, JSON, WAL, pager, VFS,
B-tree, PRAGMA, SELECT grouped/order/subquery, or source-neutral cleanup
clusters. The new surface is specifically upstream `triggerupfrom.test`
trigger-program `UPDATE FROM` behavior.

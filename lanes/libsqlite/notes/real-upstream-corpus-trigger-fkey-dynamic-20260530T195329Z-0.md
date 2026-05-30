# real-upstream-corpus-trigger-fkey-dynamic-20260530T195329Z-0

Status: ready for integration from accepted base `a279204339e8bc1ec8d0d4db06bea5b6a6d043b5`.

Added a focused real-upstream trigger/FK dynamic corpus batch:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test`.
- Ported sections: `fkey5-1.*` through `fkey5-8.*` plus the same file's missing-parent and WITHOUT ROWID FK-check contracts.
- Focused PHP file: `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyForeignKeyCheckDynamicTest.php`.
- Focused growth: 1,003 distinct TestRunner PASS cases and 4,009 assertions.

Coverage shape:

- 125 dynamic seeds over eight real upstream-derived FK-check scenarios.
- Integer parent-key affinity violations.
- Binary text parent-key collation violations.
- NOCASE text parent-key matches.
- Composite binary parent-key column-order checks.
- Swapped composite child/parent column-order checks.
- NOCASE plus RTRIM composite parent-key checks.
- WITHOUT ROWID child violations with NULL rowid output.
- Missing parent-table violations where NULL child keys remain satisfied.

Non-overlap:

- This does not repeat accepted trigger/FK dynamic batches for `fkey1` replace-cascade, `fkey2` recursive/deferred/nocase/composite actions, `fkey3` self-reference, `fkey4` deferred autocommit, `fkey6` action timing, `e_fkey` action matrix, trigger RAISE/view timing, trigger2 row-timing, RETURNING/UPSERT, or PRAGMA foreign_key_list catalog rows.
- It uses the existing generic `SQLitePragmaForeignKeyCheck` behavior and generic application table/column names only.
- It adds no generated fake upstream IDs, metadata-only admission records, compatibility wrappers, or domain-specific APIs.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyForeignKeyCheckDynamicTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyForeignKeyCheckDynamicTest.php` -> `1 test files, 4009 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` -> clean.

Dependency closure:

- No new support component is needed. This reuses the lane-local `SQLitePragmaForeignKeyCheck` implementation and existing affinity/collation comparison support.

Next task:

- Continue trigger/FK real-upstream corpus only on a non-overlapping upstream section, such as unported `fkey5` table-valued pragma/schema argument behavior or another distinct `trigger*.test` section that can satisfy the current real-corpus handoff floor.

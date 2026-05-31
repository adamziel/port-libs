# real-upstream-corpus-trigger-fkey-dynamic-20260531T154555Z-0

Implemented a source-neutral real upstream trigger/FK dynamic batch for
`tool/genfkey.test`, the upstream generated-trigger foreign-key tool corpus
that is listed in the libsqlite upstream manifest but was not covered by the
accepted `fkey2.test fkey2-genfkey.*` compatibility batch.

## Changed Files

- `lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicGenfkeyTool20260531Test.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T154555Z-0.md`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/tool/genfkey.test`
- Sections: `genfkey-4.1..4.X`, `genfkey-5.1..5.5`, and
  `genfkey-6.1..6.7`

## Behavior Added

- Added `SQLiteDynamicTriggerForeignKeyPlan::genfkeyToolSchemaQuotePlan()` for:
  - generated-trigger schema diagnostics for missing referenced columns,
    implicit composite-primary-key mapping, implicit missing primary keys, and
    non-unique parent references;
  - quoted table-name preservation for generated triggers over `"t.3"`-style
    parent tables;
  - quoted composite-column cascade behavior over columns such as
    `"a.1 first"` and `"b.2 second"`;
  - generated restrict-trigger rollback when quoted parent or child key updates
    would create an orphan.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicGenfkeyTool20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicGenfkeyTool20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicGenfkeyTool20260531Test.php`
  - `1 test files, 62008 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicGenfkeyCompatibility20260531Test.php`
  - `1 test files, 55008 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

Focused TestRunner PASS cases: `+1003` from one upstream-source citation test,
1000 seeded generated-trigger variants, one invalid-input guard, and one
ownership/count test. Focused behavior assertions: `62008`.

## Non-overlap

This does not repeat the accepted `fkey2.test fkey2-genfkey.*` built-in FK
compatibility block. That accepted slice models built-in NO ACTION/CASCADE/SET
NULL behavior against the old generated-trigger compatibility cases inside
`test/fkey2.test`. This slice owns the separate upstream
`tool/genfkey.test` tool corpus covering schema-diagnostic output and quoted
identifier generated-trigger execution.

It also avoids accepted `fkey1` through `fkey8`, `fkey_malloc`,
`e_droptrigger`, `e_fkey`, `temptrigger`, `trigger1` through `triggerG`, and
`triggerupfrom` dynamic batches.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local native
trigger/FK planner and the hydrated upstream SQLite source cache. Mapped
coverage stays `1589 / 1589`; this is PASS-line and assertion growth over an
already mapped upstream manifest entry.

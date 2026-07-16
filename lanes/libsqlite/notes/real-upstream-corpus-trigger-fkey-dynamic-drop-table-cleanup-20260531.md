# Real Upstream Trigger/FK Dynamic Drop-Table Cleanup

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T034546Z-0`
- Base accepted HEAD: `ca2d3c3a4732734353ce27d70067c3ae40d81496`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_droptrigger.test`
- Upstream sections: `e_droptrigger.test` evidence block `R-37808-62273`, `do_test 4.1` through `4.4`.
- Behavior added: `SQLiteDynamicTriggerForeignKeyPlan::dropTableTriggerCleanupPlan()` models `DROP TABLE` automatically removing trigger definitions associated with the dropped table while preserving unrelated trigger schema rows.
- Focused PHP coverage: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicDropTableCleanupTest.php`, 1 file / 3606 assertions / 0 failures.
- Non-overlap: extends existing `e_droptrigger.test` coverage beyond `DROP TRIGGER` schema resolution and fired-program removal into the distinct `DROP TABLE` automatic trigger cleanup evidence block. It does not repeat `triggerD`, `triggerE`, `temptrigger`, fkey7/fkey8 action, or accepted trigger/FK wide-rowid matrix coverage.
- Dependency closure: no new support component is needed; this reuses the existing generic trigger catalog modeling in `SQLiteDynamicTriggerForeignKeyPlan`.

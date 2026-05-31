real-upstream-corpus-trigger-fkey-dynamic-target-class-20260531

- Base accepted HEAD: 140040354d7e1605b310a7ab46633d1e6e437f9b.
- Upstream source: /home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test.
- Ported section: trigger1-1.12 through trigger1-1.14 target-class validation for real trigger creation.
- Behavior added: SQLiteDynamicTriggerForeignKeyPlan::triggerCreationTargetDiagnostic() models INSTEAD OF trigger rejection on tables, BEFORE/AFTER trigger rejection on views, valid BEFORE/AFTER table triggers, and valid INSTEAD OF view triggers.
- Focused PHP movement: SQLiteRealUpstreamTriggerFkeyDynamicTargetClassTest.php adds 3005 distinct TestRunner PASS cases and 16509 assertions.
- Non-overlap: avoids accepted trigger9 view-rowid, trigger2 batch, trigger7 qualified-name/update-of/drop-trigger, triggerA view routing, triggerC rowid/self-mutation, triggerD alias, trigger8 large-body, triggerupfrom, fkey action/defer/restrict, and upsert/returning trigger clusters.
- Dependency closure: no new support component is needed; this reuses the existing dynamic trigger/FK plan class and upstream hydrated Tcl corpus.
- Verification:
  - php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php
  - php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTargetClassTest.php
  - php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTargetClassTest.php

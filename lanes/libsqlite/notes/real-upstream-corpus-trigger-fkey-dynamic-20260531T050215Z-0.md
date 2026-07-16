# real-upstream-corpus-trigger-fkey-dynamic-20260531T050215Z-0

Added focused real-upstream trigger/FK dynamic coverage from hydrated SQLite upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
- Scenarios: `triggerC-11.1.1` through `triggerC-11.3.3`, plus `triggerC-11.4`

The new `SQLiteUpstreamTriggerFkeyDynamicPlan::triggerCDefaultValuesInsert()` batch models DEFAULT VALUES row images visible to BEFORE INSERT, AFTER INSERT, and INSTEAD OF INSERT view triggers. This is distinct from the accepted `triggerC` recursion-depth, rowid-mutation, affinity-timing, constant-loop, and trigger-expression/view-delete slices.

Dependency closure: no new support component is needed. The slice reuses the existing generic upstream trigger/FK dynamic helper and hydrated upstream SQLite test cache.

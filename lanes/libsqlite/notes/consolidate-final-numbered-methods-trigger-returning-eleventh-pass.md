2026-05-29 trigger/RETURNING method consolidation eleventh pass

- Scope: `SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan` current-source view UPSERT handoff tail.
- Consolidation: renamed the remaining `Next256` production entry/helper methods for the trigger handoff path to descriptive stable names, with direct trigger test/example callers updated to the stable entrypoint.
- Compatibility: result payload keys, scenario strings, dependency markers, and assertions remain unchanged so existing behavior coverage is preserved while production method names stop carrying the worker number.
- Verification: focused lint/test/example/diff-check commands were run in the isolated worktree; see worker handoff.
- Dependency closure: no new support component needed; this reuses the existing native recursive view UPSERT materialization and handoff receipt behavior.

2026-05-29 row-value window numbered-method consolidation pass

- Renamed row-value window production entrypoints `executeNext248`, `executeNext250`, `executeNext251`, and `executeNext252` to descriptive canonical methods on `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`.
- Renamed their direct private helper methods in the same production class to descriptive unsuffixed names.
- Updated direct focused tests, Application examples, and downstream production callers to the canonical methods.
- Repaired the row-value window dependency on the consolidated savepoint method by calling `executeSubquerySavepointRollbackRetry` instead of the removed numbered savepoint entrypoint.
- No new support component is needed; this is consolidation-only and preserves existing returned keys/status strings for compatibility with focused tests.

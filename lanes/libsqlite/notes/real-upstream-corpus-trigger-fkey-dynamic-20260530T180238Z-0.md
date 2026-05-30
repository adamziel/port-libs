# Real Upstream Trigger/FK Dynamic Corpus

Base accepted HEAD: `f66597de21a7c168178b6eec67c6e12b5daf324d`.

Added `SQLiteRealUpstreamTriggerFkeySavepointDeferredCorpusTest.php` from the hydrated upstream SQLite checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Scenario range: `fkey2-2-test 20` through `fkey2-2-test 60`, covering deferred foreign-key checks across `BEGIN`, nested `SAVEPOINT`, `ROLLBACK TO`, `RELEASE`, and failed commit boundaries.

Focused assertion/PASS case count for the new file is 3027. The coverage is non-overlapping with the existing dynamic trigger/FK corpus files because it focuses on deferred savepoint boundary state, rollback image restoration, and WAL-frame truncation diagnostics for outstanding deferred violations rather than recursive cascade, schema lifecycle, visible trigger order, or defer-foreign-keys pragma cases.

Dependency closure: no new support component is needed. The batch reuses the native `SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan` helper and exercises its savepoint rollback and deferred foreign-key commit-check behavior.

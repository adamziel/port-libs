# real-upstream-corpus-trigger-fkey-dynamic-20260530T214804Z-0

Status: ready, focused real upstream trigger/FK corpus growth.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test`
- Sections ported: `fkey8-2.1.0..2.1.2`, `fkey8-2.2.0..2.2.1`, and `fkey8-2.3.0..3.1`.

Behavior added:

- `SQLiteDynamicTriggerForeignKeyPlan::withoutRowidReplaceForeignKeyCounter()` models WITHOUT ROWID `INSERT OR REPLACE` implicit deletes against deferred FK counters.
- Covers parent replacement after a prior parent delete leaving a deferred violation, child replacement clearing a deferred violation before commit, and an AFTER DELETE trigger that performs a replacement while the original FK failure remains visible at commit.
- The helper reports implicit delete rows, trigger effects, deferred counter deltas, rollback images, and violation rows using generic parent/child names.

Focused PHP coverage:

- Added `SQLiteRealUpstreamTriggerFkeyDynamicFkey8ReplaceCorpusTest.php`.
- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey8ReplaceCorpusTest.php`
- Result: `1 test files, 4295 assertions, 0 failures`.

Non-overlap:

- This does not repeat accepted trigger7 qualified-name/update-pruning/drop-trigger coverage, trigger3 RAISE coverage, fkey2 broad action matrices, fkey6 defer-foreign-keys coverage, fkey7 OR FAIL/read-set coverage, or the already accepted fkey8 statement-journal classification for sections `fkey8-1.*` and `fkey8-7.*`.
- This slice owns the later `fkey8` WITHOUT ROWID replacement/counter sections only.

Dependency closure:

- No new support component is needed. The implementation reuses existing lane-local row-array FK/trigger planning helpers and adds one bounded generic helper for the upstream `fkey8` replacement counter behavior.

Root harness:

- Not run - isolated micro-slice.

# real-upstream-corpus-trigger-fkey-dynamic-20260531T052045Z-0

Status: focused real-upstream trigger/FK dynamic corpus growth.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
- Ported section: `triggerC-2.1.1..2.1.7`

Behavior covered:

- Recursive `AFTER INSERT` trigger with `WHEN new.a>0` inserts descending rows
  `10..0`.
- Recursive `AFTER INSERT` trigger with `RAISE(IGNORE)` at `new.a==2` stops
  that branch and leaves rows `10..2`.
- Recursive `BEFORE INSERT` trigger with `WHEN new.a>0` inserts rows in
  ascending trigger-before-statement order `0..10`.
- Recursive `BEFORE INSERT` trigger with `RAISE(IGNORE)` at `new.a==2` leaves
  rows `3..10`.
- Unbounded recursive `BEFORE INSERT` and self-conflicting `INSERT OR IGNORE`
  `BEFORE INSERT` triggers report `too many levels of trigger recursion`.
- Self-conflicting `INSERT OR IGNORE` `AFTER INSERT` trigger leaves only the
  original seed row.

Changed files:

- `lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
- `lanes/libsqlite/tests/SQLiteUpstreamTriggerFkeyDynamicRealCorpusTest.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T052045Z-0.md`

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamTriggerFkeyDynamicRealCorpusTest.php`
- Result: `1 test files, 1498 assertions, 0 failures`
- Focused assertion delta: `+149` assertions in the existing real trigger/FK
  corpus file.

Non-overlap:

- Does not repeat accepted triggerC affinity/default-values behavior,
  triggerG recursive SELECT behavior, triggerB view/name behavior, triggerD
  rowid alias handling, triggerE variables, trigger9 view old-row behavior,
  fkey2 deferred transaction behavior, fkey7 read/zeroblob/OR FAIL behavior,
  fkey8 statement-journal behavior, or source-neutral cleanup.

Dependency closure:

- No new support component is needed. This reuses the existing lane-local
  real-upstream trigger/FK dynamic plan helper and hydrated upstream SQLite
  checkout as source truth.

Next:

- Continue with a non-overlapping upstream trigger/FK subsection, preferably
  later `triggerC.test` recursive UPDATE/DELETE sections or `fkey3.test`
  self-referential composite parent cases, if they are not already accepted.

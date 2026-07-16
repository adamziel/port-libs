# real-upstream-corpus-trigger-fkey-dynamic trigger1 late regression

Base accepted HEAD: `4492e9529d6540daf2941a27323f36260b8cf64c`

This isolated slice ports a real upstream `trigger1.test` late-regression
cluster into focused PHP TestRunner coverage. Owned upstream sections:

- `trigger1.test` `trigger1-17.0`: trigger-maintained PRIMARY KEY row keeps
  `PRAGMA integrity_check` at `ok`.
- `trigger1.test` `trigger1-18.0` and `trigger1-18.1`: `BEFORE UPDATE`
  writes do not change the source row image used by the outer UPDATE
  assignments.
- `trigger1.test` `trigger1-19.0` and `trigger1-19.1`: WITHOUT ROWID
  `BEFORE UPDATE` trigger reads do not expire values needed by the outer
  statement.
- `trigger1.test` `trigger1-20.1`: TEMP trigger can be dropped after its
  attached schema is detached.
- `trigger1.test` `trigger1-21.1`: recursive delete trigger effects during
  `REPLACE` leave only the replacement row.
- `trigger1.test` `trigger1-22.10`: window/subquery expressions inside trigger
  programs preserve register validity while a TEMP trigger rewrites one row to
  a blob.
- `trigger1.test` `trigger1-23.1`: syntax error in trigger body creation is
  reported and does not install a trigger.
- `trigger1.test` `trigger1-24.1` and `trigger1-24.2`: `RAISE()` accepts a
  dynamic expression message using `NEW` row values.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
  passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1LateRegression20260531Test.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1LateRegression20260531Test.php`
  passed with `1 test files, 5135 assertions, 0 failures` and `3842` PASS
  lines.

Expected selected movement: `+3842` focused PASS lines. This does not change
mapped denominator coverage, which is already complete at `1589 / 1589`.

Dependency closure: no new support component is needed. The slice reuses the
existing trigger/FK dynamic corpus planner and the hydrated upstream SQLite
checkout only for source citation.

Non-overlap: this owns `trigger1.test` late regression sections
`trigger1-17.0` through `trigger1-24.2`; it avoids the already accepted
triggerC recursive insert, fkey2/fkey5/fkey6/fkey8, triggerG, JSON, pager/WAL,
VFS, B-tree, and SELECT corpus clusters.

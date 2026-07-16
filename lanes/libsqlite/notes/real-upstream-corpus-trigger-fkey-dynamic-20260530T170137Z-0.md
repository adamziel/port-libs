# real-upstream-corpus-trigger-fkey-dynamic-20260530T170137Z-0

Base accepted HEAD: `45c7c0b7038266bad342ad051199ea41c2a0cb28`.

This slice extends the existing real-upstream trigger/FK dynamic corpus with
upstream `fkey6.test` cases:

- `fkey6-3.2.1..3.2.6`: `PRAGMA defer_foreign_keys` delays RESTRICT update
  enforcement until commit, commit still rejects outstanding violations, and
  the pragma resets after COMMIT/ROLLBACK.
- `fkey6-3.3.1..3.3.4`: deferred RESTRICT delete can be repaired by an AFTER
  DELETE trigger that reinserts the parent row before commit; without deferral
  the RESTRICT check remains immediate.

Focused delta:

- Before this slice, `SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`
  contained 3190 focused assertions.
- After this slice, it passes with `1 test files, 4498 assertions,
  0 failures`.
- Honest focused PASS/assertion delta: `+1308`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local trigger/FK dynamic planner and adds bounded native PHP
transaction/foreign-key behavior modeling.

Non-overlap: this does not duplicate existing `fkey1` replace-cascade,
`fkey2` recursive cascade/RESTRICT/composite mapping, or `trigger1` schema and
AFTER trigger statement-preservation coverage in the same focused corpus file.

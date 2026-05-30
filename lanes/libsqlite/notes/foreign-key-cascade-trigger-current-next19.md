# Foreign-key cascade trigger current next19

Status: focused current-source PHP test growth for cascaded child-table DELETE
trigger behavior.

This slice adds `SQLiteForeignKeyCascadeTriggerPlan`, a bounded row-array model
for SQLite-style `ON DELETE CASCADE` where child rows deleted by the foreign-key
action fire child-table DELETE triggers and may cascade into a grandchild table.
It covers BEFORE/AFTER child trigger ordering, trigger audit rows reading OLD
child values, trigger rewrites/deletes of grandchild rows before FK cascade
work, stable row ordering, multiple parent deletes, and malformed input guards.

Application smoke:

```sh
php lanes/libsqlite/examples/application-foreign-key-cascade-trigger-current-next19.php
```

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteForeignKeyCascadeTriggerCurrentNext19Test.php
```

Expected dashboard movement: `phpPass` increases by the exact focused PASS-line
delta from this new test file. No upstream denominator units were newly mapped.

Non-overlap: this does not repeat accepted deferred parent-key cascade,
parent-side trigger/FK interaction, FK SET DEFAULT recursion, trigger order
update/delete, or recursive trigger conflict coverage. It focuses only on
triggers caused by FK-cascaded child DELETE rows and optional grandchild cascade
materialization.

Dependency closure: no new support component is needed; the slice reuses the
existing lane-local row-array FK and trigger modeling style.

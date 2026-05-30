# Deferred Composite Foreign-Key RESTRICT Corpus

This slice adds upstream-style composite foreign-key behavior to
`SQLiteForeignKeyDeferredCascadePlan` without repeating the accepted
single-column deferred cascade/restrict corpus or foreign-key ON UPDATE action
batch.

Covered behavior:

- Composite parent/child key normalization for `ON DELETE` and `ON UPDATE`.
- Deferred `RESTRICT` raises immediately for a full composite child reference.
- SQLite's composite NULL rule: any NULL in the child key suppresses the FK
  check and is ignored by RESTRICT, CASCADE, SET NULL, SET DEFAULT, and
  violation reporting.
- Composite `CASCADE`, `SET NULL`, `SET DEFAULT`, and `NO ACTION` behavior for
  parent deletes and updates.
- Composite rollback-preview preservation and malformed composite-key guards.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteForeignKeyDeferredCompositeRestrictNext10Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS composite deferred restrict delete raises before commit
PASS composite deferred restrict delete allows unreferenced composite parent
PASS composite deferred restrict delete removes only unreferenced parent row
PASS composite deferred restrict delete preserves child rows when no match exists
PASS composite deferred restrict delete counts only parent change
PASS composite deferred no action records two full-key delete violations
PASS composite deferred no action ignores partial null child site
PASS composite deferred no action ignores partial null child option
PASS composite deferred cascade deletes both matching child rows
PASS composite deferred cascade records composite child keys
PASS composite deferred cascade leaves partial null children untouched
PASS composite deferred cascade reports parent plus child changes
PASS composite deferred set null clears first child key column
PASS composite deferred set null clears second child key column
PASS composite deferred set null preserves unrelated composite child key
PASS composite deferred set default writes supplied vector default
PASS composite deferred set default records vector default
PASS composite deferred set default preserves child row count
PASS composite deferred update restrict raises before commit
PASS composite deferred update restrict allows unreferenced composite parent
PASS composite deferred update restrict records old composite key
PASS composite deferred update restrict records new composite key
PASS composite deferred update cascade rewrites first child key column
PASS composite deferred update cascade rewrites second child key column
PASS composite deferred update cascade records old composite child key
PASS composite deferred update cascade records new composite child key
PASS composite deferred update cascade leaves partial null child site unchanged
PASS composite deferred update cascade leaves partial null child option unchanged
PASS composite deferred update set null clears both child columns
PASS composite deferred update set default writes vector default
PASS composite deferred update no action reports missing parent after update
PASS composite deferred update no action counts only parent update
PASS composite deferred update no action ignores partial null children
PASS composite deferred rollback preview restores composite parent keys
PASS composite deferred update rollback preview restores composite child keys
PASS composite deferred key count mismatch is rejected
PASS composite deferred duplicate parent columns are rejected
PASS composite deferred malformed child column is rejected
PASS composite deferred delete key missing second column is rejected
PASS composite deferred update key missing new second column is rejected

1 test files, 40 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-foreign-key-deferred-composite-restrict.php
{
    "restrict_delete": "blocked-before-deferred-commit",
    "cascade_remaining_meta": [
        12,
        13
    ],
    "partial_null_child_preserved": true,
    "deferred_parent_key": [
        1,
        "active_plugins"
    ],
    "cascade_child_keys": [
        [
            1,
            "active_plugins"
        ],
        [
            1,
            "active_plugins"
        ]
    ],
    "changes": 3
}
```

Dashboard delta:

- `phpPass`: `3236 -> 3276` from 40 newly passing focused PASS cases.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP corpus
  coverage, not a newly mapped upstream inventory unit.

Dependency closure: no new support component is needed. The patch extends the
existing row-array deferred foreign-key planner.

# Real Upstream Corpus: UPSERT RETURNING Dynamic Target Follow-Up

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T172440Z-0`
Base: `99dfad49eb8b3659a920d2be780c5f32d787d8ac`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- Ported sections: `1.1` through `1.8`, `2.1` through `2.9`, `3.1` through `3.6`, `4.1` through `4.3`, and `5.0`.

## Behavior Covered

- Primary-key and UNIQUE conflict routing across rowid, explicit primary-key, and WITHOUT ROWID variants.
- `DO NOTHING` suppresses RETURNING rows while still recording the matched conflict arm.
- `DO UPDATE` RETURNING rows use the post-update row image.
- Row-value style assignments can rewrite multiple columns, including a UNIQUE column.
- Secondary UNIQUE conflicts during a conflict update raise an error instead of silently replacing another row.
- Reordered conflict targets match the same unique constraint, while duplicate, over-wide, or expression-mismatched targets are rejected.
- Expression-index target behavior is represented through generated expression-key columns.
- Partial-index style target gates preserve the upstream distinction between target matching and predicate gating.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTargetTest.php
```

Result:

```text
1 test files, 535 assertions, 0 failures
```

PASS-line delta: `+153` focused PASS lines.

## Non-Overlap

This does not repeat the already accepted real upstream dynamic files covering `upsert2.test`, `upsert3.test`, `upsert5.test`, `returning1.test`, or the prior `upsert4.test` sections 6 through 9. This slice owns the earlier `upsert4.test` target-analysis and partial-index conflict behavior only.

## Dependency Closure

No new support component is needed. The tests reuse the existing native `SQLiteUpsertDoUpdateWherePlan` conflict-arm and RETURNING projection behavior.

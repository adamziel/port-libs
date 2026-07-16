# real-upstream-corpus-upsert-returning-dynamic-20260531T020926Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`

Ported behavior:

- `upsert4.test` section 8 target table named `excluded`:
  - without an INSERT target alias, `excluded.w` resolves to the target table row;
  - with `INSERT INTO excluded AS x1`, `excluded.w` and `excluded.x` resolve to the UPSERT pseudo-row;
  - quoted conflict target column `"a b"` / `[a b]` remains part of the unique target;
  - false `WHERE excluded.w != ...` suppresses update and RETURNING yield;
  - true `WHERE excluded.x = ...` updates from the current target row and yields the changed row.
- `returning1.test` RETURNING projection behavior:
  - quoted column names are dequoted;
  - table wildcard `excluded.*` in RETURNING is rejected.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicExcludedAliasTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 8007 assertions, 0 failures
```

PASS-line movement:

- Added `1004` focused TestRunner PASS cases:
  - 1 parser/quoted-conflict-target case;
  - 1000 deterministic upstream-backed excluded-table alias cases;
  - 1 RETURNING table-wildcard rejection case;
  - 1 upstream source coverage citation case;
  - 1 dependency-closure case.
- `lane-status.json` `phpPass` moves from `1630123` to `1631127` if accepted.

Non-overlap:

- Current accepted coverage already includes UPSERT target-first, scope-matrix, tail, catch-all, trigger-old-value, omitted-target DO NOTHING, and no-target RETURNING row-stream behavior.
- This slice specifically owns upstream `upsert4.test` section 8 table-name-vs-pseudo-table `excluded` binding with RETURNING projection, which is distinct from prior conflict-arm priority, omitted-target, and general yield-stream batches.

Dependency closure:

- No new support component needed.
- Reuses the existing native `SQLiteUpsertReturningSql` parser/executor for target aliases, quoted identifiers, pseudo-table resolution, predicates, and RETURNING projection.

Root harness:

- Not run; isolated micro-slice.

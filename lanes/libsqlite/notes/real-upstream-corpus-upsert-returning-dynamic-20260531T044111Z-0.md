# Real Upstream UPSERT RETURNING Dynamic Slice

- Session: `port-dev-sqlite-yield-dyn-real-upsert-20260531T044111Z`
- Base accepted HEAD: `0b81729d69877023d4b2607c8a1ffc5fac25bee0`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
- Upstream section: `upsert1-1300`

## Behavior

The slice extends the existing port of `upsert1.test` section `upsert1-1300`.
That upstream case verifies that duplicate rows from an INSERT SELECT UPSERT
pass the correct current row image as `old.y` to a BEFORE UPDATE trigger.

The new focused tests add 1000 distinct interleaved duplicate-source cases.
Each case uses three target keys, six source rows, varied long text payload
sizes, and non-sorted source order. Assertions verify:

- first occurrence per key inserts and second occurrence updates;
- trigger old-image comparisons match for every duplicate key;
- final target rows sort by key after statement execution;
- RETURNING rows preserve statement/source order, not target sort order.

## Focused Evidence

Before this slice, the focused file passed with:

`1 test files, 7008 assertions, 0 failures`

After this slice:

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerOldValueDynamicTest.php`

Result:

`1 test files, 15008 assertions, 0 failures`

PASS-line movement in this focused file is `+1000` (`1007` to `2007`).
Assertion movement is `+8000`.

## Non-Overlap

This does not edit production source and does not repeat the accepted
UPSERT conflict-target WHERE or excluded-alias conflict slices. It stays inside
the already assigned `upsert1.test` `upsert1-1300` trigger old-image behavior
and widens dynamic coverage with interleaved duplicate source ordering.

## Dependency Closure

No new support component is needed. The slice reuses the existing native
`SQLiteUpsertTriggerOldValuePlan` focused behavior model and the repo-local
TestRunner.

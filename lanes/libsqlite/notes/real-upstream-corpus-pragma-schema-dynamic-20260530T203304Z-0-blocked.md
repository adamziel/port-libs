# real-upstream-corpus-pragma-schema-dynamic-20260530T203304Z-0 blocked

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T203304Z-0`
Base accepted HEAD: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`

## Attempted upstream scope

Source truth inspected:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma6.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema6.test`

## Blocker

This worktree already contains accepted or ready lane-local coverage for the
high-yield PRAGMA/schema surfaces that would make this slice large enough:

- PRAGMA table-info/index-info/index-list/index-xinfo/foreign-key/table-list,
  function-list/module-list/collation-list, and schema/user/data-version rows
  are covered by the existing real-upstream PRAGMA dynamic tests and notes.
- `schema.test` and `schema2.test` create/drop/attach/detach/rollback schema
  invalidation are already covered by existing dynamic invalidation batches.
- `schema3.test` multi-client stale schema-cache refresh is already covered by
  existing dynamic schema-cache batches.
- `schema4.test` same-name trigger/object and rename behavior is already
  covered by an existing real-upstream schema4 batch.
- `schema5.test` legacy CREATE TABLE constraint syntax and `schema6.test`
  rowid/without-rowid layout families are already covered, including a prior
  1,000-case `schema6-110`/`schema6-130` handoff.

The only immediately visible remaining file in this domain is `pragma6.test`,
whose upstream content is a three-test quick-check/integrity-check corruption
fixture around a hex database image plus one temp table. Hand-porting that
alone would be far below the active real-upstream hard floor of 1,000 distinct
focused TestRunner PASS cases or 5,000 behavior assertions, and it would
overlap the existing integrity/quick-check and generated-column PRAGMA
coverage unless paired with a broader file-image corruption parser batch.

## Next larger batch

The next useful batch should not be another small PRAGMA/schema hand-port. It
should first add or reuse a bounded SQLite hexdb fixture importer for real
upstream corruption fixtures, then admit a larger non-overlapping corpus across
`pragma6.test`, `schemafault.test`, and adjacent integrity/quick-check fault
fixtures. That batch can plausibly meet the floor by exercising decoded page
images, generated-column schema records, malformed record payloads,
quick-check/integrity-check diagnostics, and OOM/fault classification without
inventing fake upstream script IDs.

## Verification

No production PHP or PHP tests were changed in this blocked handoff.

- `git diff --check -- lanes/libsqlite`

## Dependency closure

No new support component was added. A future acceptable batch likely needs the
smallest native PHP support component for bounded upstream hexdb fixture
decoding, with activation gated by focused `pragma6.test` quick-check and
integrity-check assertions.

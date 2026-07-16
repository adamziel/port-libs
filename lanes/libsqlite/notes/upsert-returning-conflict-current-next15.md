# UPSERT RETURNING Conflict Current Next15

Slice: `yield-sqlite-upsert-returning-conflict-current-next15`

## Behavior

- Extended `SQLiteUpsertDoUpdateWherePlan::execute()` with optional secondary unique-constraint validation.
- Preserves the existing conflict-target behavior by default.
- Aborts incoming inserts that miss the UPSERT conflict target but collide with another current UNIQUE constraint.
- Aborts `DO UPDATE` result rows that would collide with another current UNIQUE constraint.
- Allows SQLite-style `NULL` values in unique columns to remain non-conflicting.
- Keeps skipped `DO UPDATE WHERE` rows out of `RETURNING` and out of secondary unique checks when the update does not run.

## Focused Verification

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningConflictCurrentTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS upsert returning conflict current clean returning names include updates and insert
PASS upsert returning conflict current clean returning autoload values use excluded values
PASS upsert returning conflict current clean returning slot values use excluded values
PASS upsert returning conflict current clean updated rows include current conflicts only
PASS upsert returning conflict current clean inserted rows include non-conflict row
PASS upsert returning conflict current clean changes match returning row count
PASS upsert returning conflict current clean before preserves current autoload values
PASS upsert returning conflict current clean after keeps table rows plus append
PASS upsert returning conflict current clean update can keep null unique value
PASS upsert returning conflict current clean update with null unique does not conflict
PASS upsert returning conflict current clean projection reports current changed rows
PASS upsert returning conflict current clean projection callable sees update revisions
PASS upsert returning conflict current repeat first row inserts
PASS upsert returning conflict current repeat second row updates inserted current row
PASS upsert returning conflict current repeat update may change secondary unique autoload
PASS upsert returning conflict current repeat update may change secondary unique slot
PASS upsert returning conflict current repeat after has one row for repeated option name
PASS upsert returning conflict current repeat changes include insert and update
PASS upsert returning conflict current repeat returning order preserves statement order
PASS upsert returning conflict current repeat revision uses current inserted revision
PASS upsert returning conflict current skip omits skipped conflicting update from returning
PASS upsert returning conflict current skip records skipped incoming conflict
PASS upsert returning conflict current skip avoids secondary unique conflict because update does not run
PASS upsert returning conflict current skip still inserts later safe row
PASS upsert returning conflict current skip changes count excludes skipped row
PASS upsert returning conflict current insert conflicting autoload aborts against current row
PASS upsert returning conflict current insert conflicting slot aborts against current row
PASS upsert returning conflict current insert null secondary unique autoload is allowed
PASS upsert returning conflict current insert second null secondary unique autoload is allowed
PASS upsert returning conflict current update conflicting autoload aborts against current row
PASS upsert returning conflict current update conflicting slot aborts against current row
PASS upsert returning conflict current update may keep its own secondary autoload
PASS upsert returning conflict current update may keep its own secondary slot
PASS upsert returning conflict current update may set secondary unique to null
PASS upsert returning conflict current later update conflicts with earlier inserted autoload
PASS upsert returning conflict current later insert conflicts with earlier updated slot
PASS upsert returning conflict current missing current secondary unique column aborts when checked
PASS upsert returning conflict current missing incoming secondary unique column aborts when checked
PASS upsert returning conflict current unique constraints validate list shape
PASS upsert returning conflict current unique constraints validate non-empty list
PASS upsert returning conflict current unique constraints validate nested list
PASS upsert returning conflict current unique constraints validate nested columns
PASS upsert returning conflict current unique constraints default preserves prior single constraint behavior
PASS upsert returning conflict current composite secondary unique detects current conflict
PASS upsert returning conflict current composite secondary unique allows partial null
PASS upsert returning conflict current composite secondary unique catches update result conflict
PASS upsert returning conflict current projection still validates returning rows after current conflict checks

1 test files, 47 assertions, 0 failures
```

## Status Delta

- `phpPass`: `4362 -> 4409` from 47 newly passing focused PHP cases.
- `benchmarkDenominator.mapped`: unchanged; this is focused native PHP behavior coverage, not a newly hydrated upstream inventory unit.

## Application Smoke

`application-upsert-returning-conflict-current.php` previews copied `wp_options` UPSERT RETURNING rows while rejecting a `DO UPDATE` result that would collide with another current UNIQUE option column.

## Non-Overlap

This slice builds on the accepted UPSERT `DO UPDATE WHERE` and multi-row RETURNING helpers without repeating accepted INSERT SELECT conflict handling, INSERT OR REPLACE delete-before-insert planning, UPDATE FROM current conflict behavior, trigger conflict inheritance, SELECT/JSON/VFS/WAL/B-tree clusters, or the recently accepted rollback/sync/VFS/B-tree/SQL text clusters.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded row-array UPSERT executor and adds current-row unique-constraint validation inside that native PHP helper.

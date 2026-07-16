# real-upstream-corpus-pragma-schema-dynamic-20260531T054341Z-0

Session: `port-dev-sqlite-yield-dyn-real-pragma-20260531T054341Z`

Source base: `4492e9529d6540daf2941a27323f36260b8cf64c`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema5.test`
  - `schema5-1.1`: legacy adjacent table constraints `PRIMARY KEY(a) UNIQUE(a) CONSTRAINT one`
  - `schema5-1.3`: named `PRIMARY KEY`, `CHECK`, and `UNIQUE` constraints chained in one table-constraint definition
  - `schema5-1.5` through `schema5-1.7`: `UNIQUE(a) CONSTRAINT one` plus `PRIMARY KEY(b,c) CONSTRAINT two`

## Change

- Expanded `SQLiteRealUpstreamPragmaSchemaDynamicLegacyConstraintsTest.php`.
- The test ports the schema/PRAGMA catalog side of upstream `schema5.test` legacy CREATE TABLE syntax using generic `legacy_*` application table names.
- The corpus verifies table-info column order, primary-key ordinals, autoindex visibility, autoindex origins, and index-info/index-xinfo columns for 300 variants.
- The tracked base file already contained 901 focused PASS cases; this handoff raises the file to 1201 focused PASS cases for a non-overlapping +300 PASS-line delta.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicLegacyConstraintsTest.php`
  - `1 test files, 6004 assertions, 0 failures`
  - `1201` focused PASS lines
  - `+300` PASS lines over the tracked base file

## Non-Overlap

This does not repeat the accepted `pragma.test`, `pragma3.test`, `pragma4.test`, `pragma5.test`, or schema invalidation batches. It adds real upstream `schema5.test` legacy table-constraint parsing coverage over the existing PRAGMA schema catalog surface.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local schema-record parser and PRAGMA schema catalog primitives.

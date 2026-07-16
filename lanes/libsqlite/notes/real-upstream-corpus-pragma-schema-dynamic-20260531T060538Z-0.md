# Real Upstream Corpus: PRAGMA Schema Dynamic Version/List

Base accepted HEAD: `5a0bbcc53e4d53b976a73e07fed57fd92e934f80`

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T060538Z-0`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-8.1.3` through `pragma-8.1.18`: `schema_version` assignment, defensive-mode no-op behavior, attached-schema isolation, and schema reload pressure after schema-cookie changes.
  - `pragma-8.2.*`: `user_version` read/write behavior independent from `schema_version`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`
  - `pragma3-100` through `pragma3-170`: `data_version` read-only writes, same-connection commit stability, and other-connection commit movement.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test`
  - `1.0` through `3.1`: virtual PRAGMA metadata rowsets for `pragma_function_list`, `pragma_module_list`, and `pragma_pragma_list`.

## Local Coverage

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicVersionList20260531Test.php`.
- Adds `1,001` focused TestRunner PASS cases.
- Adds `20,004` focused assertions.
- Expected selected `phpPass` movement: `2408856 -> 2409857`.
- Mapped denominator movement: none; mapped inventory remains `1589 / 1589`.

## Non-Overlap

This slice avoids the accepted PRAGMA shadowing, pragma4 join corpus, active runtime, schema5 legacy constraints, attached integrity, cache-spill, and object-name-collision batches. It focuses on upstream version-cookie/data-version and virtual PRAGMA list behavior.

## Dependency Closure

No new support component is needed. The slice reuses existing generic `SQLitePragmaSchemaDataVersion`, `SQLitePragmaDataVersionTracker`, `SQLiteAttachedSchemaCatalog`, and virtual PRAGMA metadata support.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicVersionList20260531Test.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicVersionList20260531Test.php`
- Pending final lane checks in this handoff: `SQLiteNoDomainSpecificApiTest.php` and `git diff --check -- lanes/libsqlite`.

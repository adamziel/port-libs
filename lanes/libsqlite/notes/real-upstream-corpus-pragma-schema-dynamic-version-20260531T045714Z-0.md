# real-upstream-corpus-pragma-schema-dynamic-20260531T045714Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T045714Z-0`

Base accepted HEAD: `d470482ec8f04bd52049cae518f9a06a2103fe0c`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- Ported sections:
  - `pragma-8.1.1` through `pragma-8.1.4`: `PRAGMA schema_version` assignment/readback and defensive-mode write ignore.
  - `pragma-8.1.5` through `pragma-8.1.10`: schema changes advance the schema cookie and stale prepared statements observe schema expiry.
  - `pragma-8.1.11` through `pragma-8.1.18`: attached schemas keep independent `schema_version` cookies.
  - `pragma-8.2.1` through `pragma-8.2.4`: `PRAGMA user_version` reads/writes independently from `schema_version`.

## Focused Movement

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicVersionCorpusTest.php`.
- Focused PASS-line delta: `1001` (`1000` dynamic behavior cases plus one source/non-overlap/dependency citation case).
- Focused assertion count: `15008`.
- Non-overlap: this does not repeat accepted PRAGMA table-info/index/table-list/foreign-key/schema-cache shadowing, pragma5 virtual rows, pragma6 integrity/quick_check, schema2 prepared expiry, schema object-name collision, or schema join-matrix batches. It owns real upstream `pragma.test` 8.1/8.2 schema/user-version dynamic behavior.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicVersionCorpusTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicVersionCorpusTest.php`
  - `1 test files, 15008 assertions, 0 failures`

## Dependency Closure

No new support component is needed. This reuses the lane-local `SQLitePragmaSchemaDataVersion` model for schema/data/user-version state, defensive schema-version writes, local/external commit counters, and attached-schema isolation.

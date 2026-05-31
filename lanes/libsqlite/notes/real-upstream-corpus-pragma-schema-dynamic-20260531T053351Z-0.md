# real-upstream-corpus-pragma-schema-dynamic-20260531T053351Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T053351Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- Upstream sections: `pragma-22.2`, `pragma-22.3.1`, `pragma-22.3.2`, `pragma-22.3.3`, `pragma-22.4.1`, `pragma-22.4.2`, and `pragma-22.4.3`.

Behavior ported:

- Added `SQLitePragmaAttachedIntegrityCheck`, a bounded native PHP helper for `PRAGMA [schema].integrity_check` and `PRAGMA [schema].quick_check` over attached database images.
- Unqualified `PRAGMA integrity_check` checks all supplied attached schemas and labels each corrupt schema with the upstream-style `*** in database NAME ***` prefix.
- Schema-qualified checks target only the named schema, so a clean `main` stays `ok` when `aux` is corrupt, and vice versa.
- Quoted schema names and numeric limits are parsed for the attached integrity path.

Focused PHP coverage:

- Added `SQLiteRealUpstreamPragmaSchemaAttachedIntegrityDynamicTest.php`.
- 1,000 dynamic behavior PASS cases plus one upstream source-citation PASS case.
- 6,004 focused assertions.

Non-overlap:

- This does not repeat accepted PRAGMA table-info/defaults/index metadata, schema invalidation/reload, schema6 equivalence, pragma6 generated-schema integrity, table-valued PRAGMA, runtime list, data-version, page-count, cache-spill, or temp/main shadowing batches.
- The new surface is specifically upstream `pragma.test` 22 attached-schema `integrity_check` targeting and corrupt-schema labeling.

Dependency closure:

- No new support component is needed. The slice reuses the existing native `SQLitePragmaIntegrityCheck`, record/page encoders, and SQLite database image parser, adding only the bounded attached-schema dispatcher needed by the upstream PRAGMA behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaAttachedIntegrityCheck.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaAttachedIntegrityDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaAttachedIntegrityDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

# real-upstream-corpus-pragma-schema-dynamic-20260531T005301Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  `pragma-8.1.1` through `pragma-8.1.18` for `schema_version` reads/writes,
  defensive-mode write suppression, and attached-schema isolation.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  `pragma-8.2.1` through `pragma-8.2.15` for `user_version` reads/writes,
  transaction rollback restoration, attached-schema isolation, and negative
  values.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma2.test`
  `pragma2-5.1` through `pragma2-5.3` for `cache_size` plus `cache_spill`
  `YES`, `NO`, and negative-threshold behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  `pragma-7.3` for per-schema `lock_status` rows.

Patch:

- Added `SQLiteRealUpstreamPragmaSchemaDynamicRuntimeMatrixTest.php`.
- The file adds 1,001 focused TestRunner cases: 250 schema-version attached
  schema variants, 250 user-version rollback variants, 250 cache-spill
  schema-isolation variants, 250 lock-status/detach variants, and one source
  citation case.
- The batch reuses existing `SQLitePragmaRuntimeState`; no new support
  component is required.

Non-overlap:

- This slice does not repeat table-valued PRAGMA list/table-info batches,
  PRAGMA data_version batches, PRAGMA schema catalog row batches, trusted
  schema policy batches, or pager/PRAGMA cache_spill storage batches. It owns
  runtime dynamic PRAGMA state interactions across attached schemas and
  transactions.

Verification:

- Red-first focused run before correction:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicRuntimeMatrixTest.php`
  produced `1 test files, 8255 assertions, 250 failures`; the failures showed
  the attached-schema `cache_spill=YES` expectation needed to use the attached
  schema's own cache size.
- Focused passing run:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicRuntimeMatrixTest.php`
  produced `1 test files, 9255 assertions, 0 failures` and 1,001 PASS lines.
- PHP lint:
  `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicRuntimeMatrixTest.php`
  passed.

# real-upstream-corpus-pragma-schema-dynamic-generated-names-20260531

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T055251Z-0`

Base accepted HEAD: `db171f640e25dd929585c8e1b7a1c804219fdfee`

Upstream source sections:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/gencol1.test`
  `gencol1-21.1`: `pragma_table_xinfo()` preserves generated-column declared
  names and types with mixed keyword casing/whitespace, and treats
  `Always default(...)` as an ordinary column.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  `pragma-6.2.2`: schema PRAGMAs preserve DEFAULT expression text, NULL,
  empty-string defaults, and composite primary-key ordinals.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  `pragma-6.8`: duplicate table-level PRIMARY KEY entries keep the first
  occurrence's ordinal and skip the repeated ordinal.

Added PHP focused corpus:

- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicGeneratedNamesTest.php`
- 1000 distinct generated/default/composite schema variants plus one source
  citation case.
- Focused new-file result: 1 file / 8003 assertions / 0 failures / 1001 PASS
  lines.
- Related PRAGMA schema family result: 5 files / 63017 assertions /
  0 failures.

Non-overlap:

- This does not repeat the accepted PRAGMA shadowing, join, fourth-thousand, or
  fifth-thousand batches. It specifically covers `gencol1-21.1` declared-name
  and declared-type behavior for mixed generated-column syntax and ordinary
  `Always default(...)` columns, then combines that with real upstream
  `pragma-6.2.2` and `pragma-6.8` default/primary-key metadata checks.

Dependency closure:

- No new support component is needed. The existing
  `SQLitePragmaSchemaCatalog` schema parser and table-valued PRAGMA path are
  reused.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicGeneratedNamesTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicGeneratedNamesTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicShadowingTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicJoinCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicFourthThousandTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicFifthThousandTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicGeneratedNamesTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`

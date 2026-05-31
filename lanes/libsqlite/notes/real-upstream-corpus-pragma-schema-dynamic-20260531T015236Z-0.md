# real-upstream-corpus-pragma-schema-dynamic-20260531T015236Z-0

Status: blocked by overlap with accepted PRAGMA/schema corpus coverage.

Accepted base inspected: `5355cb7ecea35e8be7c9099c3c6dbf4e5ec09d23`.

Attempted upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma6.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragmafault.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema6.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schemafault.test`

Existing non-overlapping coverage found in this worktree already owns the
useful PRAGMA/schema clusters for this slice:

- `SQLiteRealUpstreamPragmaPagerStateDynamicTest.php` covers
  `pragma.test` pager PRAGMA families `pragma-1.*`, `pragma-2.*`, and `pragma-4.*`.
- `SQLiteRealUpstreamPragmaSchemaDynamicFollowupTest.php`,
  `SQLiteRealUpstreamCorpusPragmaSchemaDynamicQuotedSchemaTest.php`, and the
  thousand-row PRAGMA schema dynamic files cover `pragma.test` schema-query
  PRAGMAs `pragma-6.*`, `pragma-7.*`, and `pragma-8.*`.
- `SQLiteRealUpstreamPragma2CacheSpillDynamicTest.php` and
  `SQLiteRealUpstreamPragmaCacheSpillDynamicTest.php` cover the
  `pragma2.test` freelist and `cache_spill` families.
- `SQLiteRealUpstreamPragma3DataVersionDynamicTest.php`,
  `SQLiteRealUpstreamPragmaDataVersionConnectionLocalDynamicTest.php`, and
  related data-version tests cover `pragma3.test`.
- `SQLiteRealUpstreamPragmaSchemaDynamicPragma4Test.php` and
  `SQLiteRealUpstreamPragmaSchemaDynamicSeventhThousandTest.php` cover
  `pragma4.test` table-valued PRAGMA joins and RIGHT JOIN behavior.
- `SQLiteRealUpstreamCorpusPragmaSchemaDynamicPragma5VirtualRowsTest.php` and
  `SQLiteRealUpstreamPragmaRuntimeListDynamicTest.php` cover `pragma5.test`
  virtual PRAGMA rowsets.
- `SQLiteRealUpstreamPragma6IntegrityGeneratedDynamicTest.php` covers the
  `pragma6.test` generated-column integrity/quick-check scenario.
- `SQLiteRealUpstreamPragmaSchemaDynamicSchemaTailTest.php`,
  `SQLiteRealUpstreamPragmaSchema2TempDynamicTest.php`,
  `SQLiteRealUpstreamSchema3MulticlientDynamicCorpusTest.php`,
  `SQLiteRealUpstreamPragmaSchema4DynamicTest.php`,
  `SQLiteRealUpstreamPragmaSchemaLegacyCreateDynamicTest.php`, and
  `SQLiteRealUpstreamPragmaSchemaDynamicSchema6Test.php` cover the practical
  schema.test/schema2.test/schema3.test/schema4.test/schema5.test/schema6.test
  behavior clusters.
- `SQLiteRealUpstreamPragmaFaultIntegrityDynamicTest.php` and
  `SQLiteRealUpstreamPragmaSchemaFaultDynamicTest.php` cover the fault-facing
  PRAGMA/schema integrity surfaces already suitable for the PHP port.

Why no ready patch:

The current hard handoff floor for `real-upstream-corpus-*` slices requires at
least 1,000 distinct focused TestRunner PASS cases, 5,000 behavior assertions,
a blocker fix that unlocks at least 2,000 PASS cases or 10,000 assertions, or
guarded mapped-denominator movement. The remaining PRAGMA/schema ideas found
in this inspection are either already accepted in the files above, require
corrupt binary database/testfixture behavior already represented by focused
fault tests, or would be another generated loop around existing static
catalog metadata. That would violate the real-upstream corpus rule and the
hard floor.

Next larger batch to try:

Pivot the next real-corpus worker out of the saturated PRAGMA/schema dynamic
surface and into a current red full-run cluster. The lane status names
expression, pragma, and rowvalue-in-subquery failures as known-red broad
parity clusters. For PRAGMA specifically, the useful next attempt is not
another schema-catalog metadata batch; it is a reduced failing full-run
`pragma*.test` runner case with exact failure output, followed by the shared
behavior fix that admits that case and its dependent release/all rows.

Verification:

- No PHP source changed.
- No focused behavior test run, because this is a blocked note and not a ready
  behavior patch.

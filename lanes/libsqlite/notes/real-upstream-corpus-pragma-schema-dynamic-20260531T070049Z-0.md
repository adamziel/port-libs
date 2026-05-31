# Real Upstream Corpus PRAGMA Schema Dynamic

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T070049Z-0`

Base accepted HEAD: `b596d6a43afd4ccaf50904f879de33fed9b5b7f3`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test`
- Sections `pragma5-1.0` through `pragma5-1.2`: `pragma_function_list` table metadata, builtin `upper`, and application-defined external functions.
- Sections `pragma5-2.0` through `pragma5-2.1`: `pragma_module_list` table metadata and `fts5` module visibility.
- Sections `pragma5-3.0` through `pragma5-3.1`: `pragma_pragma_list` table metadata and `pragma_list` visibility.

Patch content:

- Added `SQLiteRealUpstreamPragmaSchemaDynamicIntrospectionTest.php`.
- Adds 1001 distinct focused TestRunner PASS cases.
- Adds 21005 focused behavior assertions.
- Reuses existing generic `SQLitePragmaSchemaCatalog` introspection behavior; no new production support component was required.

Non-overlap:

- This extends the PRAGMA/schema dynamic corpus into `pragma5.test` introspection virtual-table metadata and rowsets.
- It does not repeat existing `pragma4.test` schema-qualified table/index/FK/table-list shadowing or join corpus files.
- It adds no WordPress-specific APIs, examples, or source names.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicIntrospectionTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicIntrospectionTest.php`
  - Result: `1 test files, 21005 assertions, 0 failures`

Dependency closure:

- No new dependency or support-library component is needed. The existing native PHP PRAGMA schema catalog already exposes the needed introspection rowsets.

Next:

- Continue PRAGMA/schema dynamic corpus with remaining upstream sections only if they are non-overlapping with existing `pragma4.test` and accepted schema invalidation/table-list coverage.

# real-upstream-corpus-btree-index-dynamic-20260530T233656Z-0

- Base accepted HEAD: `d7c5d7f50d0d0c3f24c91125036d23912559b628`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr3.test`.
- Upstream sections covered: `indexexpr3-1.1` through `indexexpr3-2.5`.
- Focused PHP coverage: `SQLiteRealUpstreamBtreeIndexExpr3JsonCoveringDynamicTest.php` adds 1000 distinct dynamic TestRunner cases plus 3 guard/check cases, for 1003 PASS lines and 18099 behavior assertions.
- Behavior ported: JSON expression-index payload reuse for `json_extract(j,'$.x')`, covering-index planner distinctions for `i1`/`i2`, zero Function opcode reads when the expression value is covered, and retained Function opcode execution for nested `json_insert()` composition.
- Non-overlap: this targets `indexexpr3.test` JSON expression-index covering behavior. It does not repeat accepted `indexexpr1`/`indexexpr2`, `index2`, `index3`, `index5`, `index6`, `index7`, `index8`, `index9`, `indexA`, `autoindex*`, `indexedby`, indexfault, B-tree page relocation/root-collapse/interior merge, overflow freeblock/freelist release, JSON table, WAL, VFS, or source-neutral cleanup surfaces.
- Expected dashboard movement: PASS-line growth only, from `1157667` to `1158670` locally for this lane patch. Mapped denominator remains `1589 / 1589`.
- Dependency closure: no new support component needed; this reuses lane-local B-tree/index dynamic corpus expression-index and JSON planner detail helpers.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExpr3JsonCoveringDynamicTest.php` - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExpr3JsonCoveringDynamicTest.php` - `1 test files, 18099 assertions, 0 failures`, 1003 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` - not present on this accepted base.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` - clean.

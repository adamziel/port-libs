# real-upstream-corpus-expression-affinity-dynamic-20260531T015605Z-0

Added `SQLiteRealUpstreamExpressionAffinity3RealJoinDynamicTest.php` as a real upstream expression/affinity dynamic corpus shard.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`
- `affinity3-100` through `affinity3-142`: REAL column affinity is preserved through left/right-join and nested-view row production, so `apr / 100` remains fractional REAL.
- `affinity3-200` through `affinity3-260`: UNION-derived id-map join semantics preserve the text-id match and avoid the historical automatic-index affinity regression.

Focused coverage:

- 1,200 oracle-backed dynamic REAL-affinity projection cases over upstream join/view shapes, automatic-index on/off shapes, REAL/text/blob numeric inputs, `apr / 100`, `typeof()`, NULL predicates, sign predicates, and round-trip multiplication.
- 16 focused UNION id-map join predicate cases matching the upstream `idmap`/`mzed` automatic-index on/off scenarios.
- 1 ownership/citation guard case.
- Expected focused TestRunner growth: 1,217 PASS cases from real upstream behavior; mapped denominator remains unchanged at `1589 / 1589`.

Non-overlap:

- This avoids accepted REAL CAST prefix, generic expression real arithmetic, `affinity2.test` comparison matrix, `types2.test` storage-affinity predicate matrix, date affinity, Unicode GLOB, LIKE/GLOB predicate, SELECT expression ORDER BY, grouped SELECT text, JSON, WAL, VFS, B-tree, trigger, and suite-evidence surfaces.
- The slice specifically owns `affinity3.test` REAL join/view affinity and UNION id-map affinity behavior.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinity3RealJoinDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinity3RealJoinDynamicTest.php`
- No no-domain API guard file was present in this worktree.
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses the lane-local SELECT expression executor, REAL insert-affinity coercion helper, and the local `sqlite3` oracle pattern already used by adjacent real upstream expression-affinity corpus tests.

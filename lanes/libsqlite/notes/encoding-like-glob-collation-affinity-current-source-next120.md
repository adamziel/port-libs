# encoding-like-glob-collation-affinity-current-source-next120

Status: focused PHP behavior growth for REAL-to-text affinity in SELECT `LIKE`/`GLOB` predicates.

Behavior:

- `SQLiteSelectPredicate` now formats REAL operands with SQLite-style text affinity before `LIKE`/`GLOB`: integer-valued reals keep a `.0` suffix and exponent notation uses lowercase `e` with two-digit exponent widths.
- This fixes copied `wp_options` SELECT previews where REAL option values such as `1.0`, `0.000001`, and `1.0e20` must match SQLite text forms `1.0`, `1.0e-06`, and `1.0e+20`, not PHP's compact `1`, `1.0E-6`, or `1.0E+20` forms.
- Added Application smoke coverage for numeric option diagnostics that query REAL `option_value` data with `LIKE` and `GLOB` without ext/sqlite.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectPredicateRealAffinityLikeGlobCurrentSourceNext120Test.php`
- Result: `1 test files, 50 assertions, 0 failures` with 50 PASS lines.
- `php lanes/libsqlite/examples/application-select-real-affinity-like-glob-current-source-next120.php --self-test`
- Result: `application-select-real-affinity-like-glob-current-source-next120 self-test passed`

Non-overlap:

- Avoids accepted Unicode GLOB ranges, malformed UTF-16 LIKE/GLOB guards, numeric affinity comparisons, expression ORDER BY, JSON table constraints/cursors/sources, VFS/WAL/B-tree application clusters, and accepted SELECT subquery/grouped text dispatch.
- The new surface is REAL text-affinity spelling used specifically by SELECT `LIKE`/`GLOB` predicate execution.

Dependency closure:

- No new support component is needed. This reuses the existing native SELECT predicate and pattern-matching helpers.

Next:

- Continue with non-overlapping encoding/collation surfaces such as collation-aware predicate planning or malformed-text comparison edges outside accepted Unicode GLOB and UTF-16 guard work.

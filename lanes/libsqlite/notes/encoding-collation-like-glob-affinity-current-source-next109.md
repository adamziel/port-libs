# encoding-collation-like-glob-affinity-current-source-next109

Status: focused PHP behavior growth for parser/executor SELECT predicate affinity and RTRIM collation.

Behavior:
- `SQLiteSelectPredicate` now applies SQLite-style text coercion for scalar `LIKE` and `GLOB` operands, including integer, real, boolean, and BLOB operands.
- SELECT predicate `RTRIM` collation now trims only trailing ASCII space, preserving tabs/newlines as distinct text bytes.
- Parser-level `SQLiteSelectSql` inherits the behavior for copied `wp_options` previews.

Application smoke:
- `lanes/libsqlite/examples/application-select-like-glob-affinity-current-source-next109.php --self-test`

Non-overlap:
- Avoids accepted Unicode GLOB range handling, UTF-16 malformed record guards, UTF-16/RTRIM current-source cursor handoffs, LIKE/GLOB range cursor wrappers, JSON table source/cursor/constraint clusters, VFS/WAL/B-tree application clusters, and SELECT SQL subquery/group/order clusters.
- The new surface is executor predicate coercion and SELECT-level RTRIM space-only comparison for LIKE/GLOB/affinity behavior.

Dependency closure:
- No new support component is needed. The slice reuses existing native PHP SELECT parsing, scalar predicate evaluation, SQLite LIKE/GLOB matchers, and BLOB value support.

Verification:
- `php -l lanes/libsqlite/src/SQLiteSelectPredicate.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteSelectPredicate.php`
- `php -l lanes/libsqlite/tests/SQLiteSelectPredicateLikeGlobAffinityCurrentSourceNext109Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteSelectPredicateLikeGlobAffinityCurrentSourceNext109Test.php`
- `php -l lanes/libsqlite/examples/application-select-like-glob-affinity-current-source-next109.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-select-like-glob-affinity-current-source-next109.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectPredicateLikeGlobAffinityCurrentSourceNext109Test.php`
  - `1 test files, 53 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-select-like-glob-affinity-current-source-next109.php --self-test`
  - `application-select-like-glob-affinity-current-source-next109 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - passed with no output
- SQLite oracle spot-check:
  - `sqlite3 ':memory:' "select x'706c7567696e3a626c6f62' like 'plugin:%', x'706c7567696e3a626c6f62' glob 'plugin:*', 42 like '4%', 4.5 glob '4*', 'cache\t' = 'cache' collate rtrim, 'cache  ' = 'cache' collate rtrim;"`
  - `1|1|1|1|0|1`

Dashboard delta:
- Expected `phpPass` movement: `42491 -> 42544` (`+53` focused PASS lines).
- Mapped upstream coverage remains `604 / 1589`; this is focused PHP behavior over already mapped encoding/collation/predicate inventory.

# real-upstream-corpus-select-core-dynamic-20260530T202416Z-0

- Base accepted HEAD: `a5d711ea245dda1130ca2ff1ba1b791f9a863c2b`.
- Source truth: hydrated upstream SQLite files `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`, `select2.test`, and `select5.test`.
- Added `SQLiteRealUpstreamSelectCoreDynamicThousandTest.php` with 1,000 distinct focused TestRunner cases over real upstream SELECT core behavior:
  - `select1.test` `select1-3.*` predicates and `select1-4.*` ordering, dynamically varying range bounds, descending/secondary ordering, and LIMIT.
  - `select1.test` `select1-2.*` aggregates, dynamically varying category and score filters for `count`, `sum`, `min`, and `max`.
  - `select5.test` `select5-1.*` grouped aggregate/HAVING behavior, dynamically varying group keys and count thresholds.
  - `select2.test` `select2-4.*` join truth predicates, dynamically varying join thresholds, expression ordering, and LIMIT.
- Non-overlap: this does not repeat accepted static SELECT batches or metadata-only runner rows. It adds a new real-corpus dynamic thousand-case PHP test file with generic table names (`items`, `tags`) and no domain-specific API names.
- Dependency closure: no new support component is needed; this reuses existing `SQLiteSelectSql` parser/executor behavior.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicThousandTest.php` => `1 test files, 5000 assertions, 0 failures`.
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicThousandTest.php` => no syntax errors.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `1 test files, 3 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` => not present on this base; nearest generic guard above was run.
  - `git diff --check -- lanes/libsqlite` => passed.

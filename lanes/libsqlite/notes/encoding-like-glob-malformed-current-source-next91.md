# encoding-like-glob-malformed-current-source-next91

- Behavior: adds `SQLiteMalformedLikeGlobSourceNextPlan` for current-source to next-source LIKE/GLOB cursor repair when copied `wp_options.option_name` bytes are malformed. Malformed UTF-8/UTF-16 current rows are diagnosed and excluded from the reusable source cursor, repaired next rows can enter the rowset, newly malformed next rows exit the rowset, and reprepare reasons distinguish source-name, malformed-text, and matched-rowset changes.
- Application smoke: `lanes/libsqlite/examples/application-option-name-malformed-like-glob-source-next91.php --self-test` reports repaired option-name bytes across a `main.wp_options` schema-cookie boundary without requiring `ext/sqlite`.
- Focused evidence:
  - `php -l lanes/libsqlite/src/SQLiteMalformedLikeGlobSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteMalformedLikeGlobSourceNext91Test.php`
  - `php -l lanes/libsqlite/examples/application-option-name-malformed-like-glob-source-next91.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteMalformedLikeGlobSourceNext91Test.php`
  - Result: `1 test files, 62 assertions, 0 failures`, with 49 `PASS` lines.
  - `php lanes/libsqlite/examples/application-option-name-malformed-like-glob-source-next91.php --self-test`
  - Result: `application-option-name-malformed-like-glob-source-next91 self-test passed`
- Dashboard delta: expected `phpPass` +49 from focused PASS lines. `benchmarkDenominator.mapped` is unchanged; this is current-source PHP behavior over already mapped encoding/collation LIKE/GLOB inventory, not a new upstream row.
- Non-overlap: avoids accepted Unicode GLOB ranges, UTF-16 malformed record guards, malformed pattern matching, LIKE/GLOB current-source collation planning, UTF-16 LIKE/GLOB source cursors, VFS/WAL/B-tree/JSON/SQL executor clusters, and current batch88 surfaces. The new surface is malformed current-source row admission/repair at the LIKE/GLOB cursor reuse boundary.
- Dependency closure: no new support component is needed; this reuses the native PHP encoding source cursor and LIKE/GLOB collation helpers, adding only lane-local malformed-source classification.

# B-tree Index Balance Current Next19

- Behavior: applies SQLite index-leaf sibling balancing through the parent interior divider, so a copied `wp_options` autoload-index delete can move records between siblings and rewrite the selected parent separator instead of only previewing leaf redistribution.
- Non-overlap: avoids accepted table/index page relocation, root collapse, overflow freelist release, bulk overflow freeblocks, index-interior merge, and earlier leaf redistribution summary-only helpers. This slice is the parent-divider application layer for index leaf balance.
- Evidence:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexBalanceCurrentNext19Test.php` => `1 test files, 144 assertions, 0 failures`, with 29 PASS lines.
  - `php lanes/libsqlite/examples/application-index-leaf-balance-apply.php` prints the `application-index-leaf-balance-apply` smoke with the parent divider rewritten to `["yes","autoload_c",13]`.
  - PHP lint passed for the changed PHP files.
  - `git diff --check -- lanes/libsqlite` passed.
- Dependency closure: no new support component is needed; this reuses existing native PHP page, record, database-image, and index b-tree primitives.

# PRAGMA index_xinfo / foreign-key current-source next194

## Behavior

- Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` for child-side partial-index diagnostics.
- The slice reports when `PRAGMA index_xinfo` shows an FK child helper index is partial, while preserving SQLite behavior that `PRAGMA foreign_key_check` correctness does not require a child index.
- The current Application-shaped case has an autoload-only `wp_options(slug, locale, blog_id, option_id)` partial index. The next-source repair replaces it with a full child index, clearing the diagnostic without adding a foreign-key blocker.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 57 assertions, 0 failures`
  - 50 PASS lines

## Application Smoke

- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next194.php --self-test`
  - verifies partial child index diagnostics clear after the full child-index repair.

## Non-Overlap

- Avoids accepted next184 parent sort-order diagnostics, next185 null child keys, next188/next189/next190 parent partial/expression/rejected UNIQUE behavior, and batch177 next190 PRAGMA coverage.
- This patch is child-index partial-rowset diagnostics only; it does not alter parent-key admission, FK violation counting, or status blocking.

## Dependency Closure

- No new support component is needed. The slice reuses the existing schema catalog, `PRAGMA index_list`, `PRAGMA index_xinfo`, and foreign-key list helpers already present under `lanes/libsqlite/src`.

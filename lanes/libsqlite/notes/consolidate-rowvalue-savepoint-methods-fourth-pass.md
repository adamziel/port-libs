# Row-Value Savepoint Numbered Method Consolidation Fourth Pass

- Scope: `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan`.
- Consolidated numbered entry/helper suffixes `Next156`, `Next158`, `Next160`, `Next161`, `Next164`, `Next165`, `Next166`, `Next167`, `Next168`, `Next170`, `Next172`, `Next173`, `Next178`, and `Next180` into descriptive scenario identifiers.
- Direct row-value savepoint tests and WordPress examples now call the descriptive entrypoints.
- Dependency closure: no new support component is needed; this is production identifier consolidation only.

Verification:

- `php -l` for changed PHP files: pass.
- `php tools/run-tests.php` for the changed row-value savepoint tests: 17 test files, 1094 assertions, 0 failures.
- Changed WordPress examples with `--self-test`: pass.
- `git diff --check -- lanes/libsqlite`: pass.

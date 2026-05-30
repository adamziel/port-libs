# real-upstream-corpus-pragma-schema-dynamic-20260530T165126Z-0

- Base accepted HEAD: `9dc20dce32143ddf9ade7c84c6244ce48fb3e470`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
    - `pragma4-5.0`: `PRAGMA table_info` reports unquoted scalar `DEFAULT` values without trailing `/* ... */` or `-- ...` comments.
    - `pragma4-6.0`: table-valued `pragma_table_list()`, `pragma_foreign_key_list()`, and `pragma_table_info()` can be composed to resolve a child table's referenced parent primary key.
- Behavior fix: `SQLitePragmaSchemaCatalog::defaultValueEnd()` now stops unquoted default values before a top-level SQL comment, while preserving comment markers inside quoted strings and parenthesized expressions.
- Focused PHP coverage: extended `SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php` from 183 to 298 selected PASS lines and from 1367 to 2607 assertions.
  - Added 80 dynamic variants for upstream `pragma4.test` case `5.0` covering quoted defaults, negative numeric defaults followed by line comments, signed numeric defaults followed by block comments, parenthesized expressions containing comment markers, quoted comment markers, and blob literal defaults.
  - Added 35 dynamic variants for upstream `pragma4.test` case `6.0` covering composed `pragma_table_list`, `pragma_foreign_key_list`, and `pragma_table_info` rows for child-to-parent primary-key resolution.
- Non-overlap: this slice extends the existing PRAGMA/schema dynamic real-corpus file but does not repeat the prior `pragma.test` 6.2 primary-key ordinal fix, `pragma4.test` 4.2-4.5 table-valued coverage, or `pragma5.test` function/module-list coverage. It claims PASS-line growth only, not mapped denominator growth.
- Dependency closure: no new support component is needed; this reuses the existing schema-record parser and PRAGMA catalog helper.
- Verification:
  - `php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php` -> no syntax errors
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php` -> no syntax errors
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php` -> `1 test files, 2607 assertions, 0 failures`
  - `git diff --check -- lanes/libsqlite` -> clean

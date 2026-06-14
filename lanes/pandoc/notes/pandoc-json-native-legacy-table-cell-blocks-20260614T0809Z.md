# Pandoc JSON/native legacy table cell block slice 2026-06-14

Hook: `plib-ne43e`, JSON/native AST constructor completeness core blocker slice.

## Summary

- Added per-cell legacy Table block-list provenance for 5-field Pandoc Table readers in both `PandocJsonReader` and `NativeReader`.
- JSON and native writers now reuse unchanged legacy cell block payloads when rebuilding the table as the current 6-field Table constructor.
- Edited cells regenerate canonical block payloads and drop stale legacy cell block sidecars.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` - 1 file, 3246 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` - 46 files, 82132 assertions, 0 failures.

No Pandoc binary, JSON filter runner, Cabal/Haskell runner, browser renderer, Node tooling, external validator, online service, live provider test, or live-service provider test was executed.

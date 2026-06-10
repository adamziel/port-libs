# Shared ZIP/OPC Internal Target Suffix Validation

Slice: `plib-sd7i`, shared ZIP/OPC package core blocker.

This slice hardens the shared OPC internal target resolver used by package
relationship handling. `OpcPackagePath::resolveInternalTarget()` now validates
query and fragment suffix percent escapes before returning a resolved package
reference, rejecting malformed escapes and percent-encoded control bytes rather
than letting direct callers preserve unsafe suffixes.

Focused coverage extends OPC relationship and package-path tests for malformed
query and fragment escapes plus encoded NUL/DEL bytes. Signature relationship
transform coverage now verifies that a malformed `ContentType=%ZZ` reference is
reported as an invalid reference URI and is not materialized into a relationship
part or selected relationships.

Verification:

- `php -l lanes/pandoc/src/OpcPackagePath.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 test file, 3986 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 61958 assertions, 0 failures

No Pandoc, Cabal/Haskell runners, office suites, TeX/PDF engines, browser
renderers, zip/unzip, external validators, online services, live provider
tests, or live-service provider tests were invoked.

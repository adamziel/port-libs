# Shared ZIP Raw General-Purpose Flag Policy

Slice: `plib-6pov`, shared ZIP/OPC package core blocker.

This slice adds raw central-directory general-purpose flag policy preflight for
native ZIP package handoff. `ZipPackage::generalPurposeFlagPolicyPreflight()`
now reports unsupported flag bits, local-header flag mismatches, data descriptor
flags, deflate option flags, and deflate option flags used without deflated
compression before `ZipPackage::fromString()` is allowed to instantiate entries.

`ZipPackage::rawStrictImportPreflight()` now includes that summary as
`generalPurposeFlags` and emits stable diagnostics for unsupported flags,
strict-review data descriptor/deflate-option flags, local-header flag spoofing,
and stored-entry deflate option misuse. This preserves review provenance for
packages that construction later blocks.

Verification:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 test file, 3032 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 61985 assertions, 0 failures

No Pandoc, Cabal/Haskell runners, office suites, TeX/PDF engines, browser
renderers, zip/unzip, external validators, online services, live provider
tests, or live-service provider tests were invoked.

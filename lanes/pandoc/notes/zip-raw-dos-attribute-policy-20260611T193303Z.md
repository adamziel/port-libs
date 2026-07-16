# Shared ZIP/OPC raw DOS attribute policy

2026-06-11 core-blocker slice for `plib-yxkyv`, rebased on `origin/main`
`0ba6b0e01`.

`ZipPackage::dosAttributePolicyPreflight()` now scans central-directory DOS
attribute bits before package construction. This keeps hidden, system, and
volume-label entry provenance available when another raw ZIP policy, such as a
central/local header name mismatch, prevents `ZipPackage::fromString()` from
instantiating the package.

`ZipPackage::rawStrictImportPreflight()` now includes the `dosAttributes`
summary and reports `hidden-system-or-volume-label-entries` from raw bytes,
without exposing package payloads through external tools.

Verification:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`: 1 test
  file, 3219 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 65674
  assertions, 0 failures.

# ZIP Package Raw Extra-Field Policy Preflight

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T055907Z`
Base accepted HEAD: `7ed2f69b027c00a8c9af1b63d2dfcdebbab97ac6`

## Behavior

`ZipPackage::extraFieldPolicyPreflight()` now scans EOCD, central-directory, and
local-header raw extra fields before package instantiation. It reports duplicate
raw extra-field IDs, central-only/local-only ID splits, central/local value
mismatches, and unavailable local headers without decoding the semantic extra
field payloads.

`ZipPackage::rawStrictPreflight()` now includes this policy summary as
`extraFields` and promotes deterministic diagnostics such as
`duplicate-extra-field-ids`, `central-local-extra-field-id-mismatch`, and
`central-local-extra-field-value-mismatch`. This keeps WordPress and Office
package import queues informed even when package construction is blocked by
unsupported flags or another preflight failure.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php` passed.
- `php -l lanes/pandoc/tests/ZipPackageTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with
  `1 test files, 2687 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  passed.

Focused movement: +1 PHP PASS line and +39 focused assertions in
`ZipPackageTest.php`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP EOCD,
central-directory, local-header, and raw strict ZIP preflight readers. No
Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, TeX/PDF
engine, browser renderer, online service, live provider test, or live-service
provider test was executed.

## Non-overlap

This does not repeat earlier ZIP package coverage for malformed extra-field
structure, Unicode path extra fields, ZIP64 extra fields, central-directory
recovery/signatures, platform metadata, timestamps, symlink rejection, file
comments, local-header mismatch/span checks, data descriptors, compression, or
encryption. It adds only raw central/local extra-field ID and value policy
diagnostics for non-instantiating archives.

## Next

Wire raw strict ZIP diagnostics into DOCX, EPUB, or ODT reader import reports,
or choose a non-overlapping central-directory repair-plan handoff. Keep follow-up
work native PHP and external-tool free.

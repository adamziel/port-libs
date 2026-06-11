# EPUB3 package spine itemref linear provenance

Bead: `plib-1bhb8`

Base: current `origin/main` at `a478c52c18`.

## Slice

- `EpubPackage` now parses OPF spine `itemref@linear` through a compact
  package-level report matching the EPUB reader path.
- Each spine item preserves whether `linear` was specified, the raw token,
  the normalized reading-order boolean, and validity.
- Invalid tokens remain treated as linear/readable, but now produce
  `invalid-spine-linear-value` diagnostics on the item, package spine
  validation, and WordPress import review summaries.
- This keeps EPUB3 package preflight native PHP only: no Pandoc, EPUBCheck,
  zip/unzip, browser renderer, external validator, online service, or live
  provider invocation.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  passed: `1 test files, 1328 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: `44 test files, 64810 assertions, 0 failures`.

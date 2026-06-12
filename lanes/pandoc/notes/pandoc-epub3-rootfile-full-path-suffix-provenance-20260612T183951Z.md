# EPUB3 Rootfile Full-Path Suffix Provenance

Slice: `plib-gp8u1` / EPUB3 package ingestion.

## Scope

Compact `EpubPackage` ingestion now accepts OCF `META-INF/container.xml`
rootfile `full-path` values that include query or fragment suffixes. It loads
the stripped OPF package part from the ZIP while preserving the canonical target
with suffix, query value, and fragment value for package review.

## Implementation

- `parseContainerXml()` strips query and fragment components only for package
  part lookup.
- Rootfile records now expose `target`, `fullPathHasQuery`, `fullPathQuery`,
  `fullPathHasFragment`, and `fullPathFragment`.
- Rootfile validation reports `fullPathSuffixItems` plus query/fragment
  diagnostics.
- `summary()` and WordPress package-validation metadata receive the same
  normalized rootfile target/part split.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 2210 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 72830 assertions, 0 failures

## Accounting

- `phpPass`: `3258 -> 3259`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `3258 -> 3259`
- Added `mappedEpubRootfileFullPathSuffixCases=1`
- Added `epubRootfileFullPathSuffixAssertions=22`

## Boundaries

No Pandoc, EPUBCheck, office suite, TeX/browser engine, zip/unzip, Jupyter,
Node tooling, external validator, online service, live provider test, or
live-service provider test was executed.

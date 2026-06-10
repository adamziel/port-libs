# Pandoc EPUB3 manifest href suffix slice 2026-06-10T123617Z

Slice: `plib-at3t` / EPUB3 package ingestion.

This slice keeps OPF manifest `href` query and fragment suffixes visible for
review while continuing to load package bytes from the stripped package part.
Manifest items now expose the resolved target plus `hrefHasQuery`,
`hrefQuery`, `hrefHasFragment`, and `hrefFragment` metadata. Compact package
validation emits query/fragment diagnostics and surfaces `hrefSuffixItems`
through the WordPress import package-validation summary.

The focused fixture covers a stylesheet with a query suffix and a spine XHTML
item with both query and fragment suffixes. It verifies that reading order and
stylesheet handoff still use stripped package paths, while reviewer metadata
keeps the original suffix provenance.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 file, 898 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 61470 assertions, 0 failures

Accounting:

- `phpPass`: `3015 -> 3016`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `3165 -> 3166`
- Added `mappedEpubManifestHrefSuffixCases=1`
- Added `epubManifestHrefSuffixAssertions=26`

No Pandoc, EPUBCheck, office suite, TeX/browser engine, zip/unzip, Jupyter,
Node tooling, external validator, online service, live provider test, or
live-service provider test was executed.

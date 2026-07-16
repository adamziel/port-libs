# pandoc-epub3-package-core-current-base-20260608T140639Z

Base accepted HEAD: `8444e1a550388666a4d5c9e80d9b200e6335422b`

## Scope

Implemented one bounded EPUB3 package-core behavior: OPF manifest items with
different ids but hrefs that normalize to the same local package part are now
reported as duplicate package-part aliases for import preflight. The reader does
not reject the EPUB, does not fetch remote resources, and preserves the selected
spine/nav/NCX handoff.

This avoids overlapping recent EPUB3 slices for OCF sidecars, metadata
refinements, resource-property/media-type classification, nav/NCX targets, CFI
targets, remote resources, bindings/fallbacks, cover assets, SMIL overlays, and
encryption/obfuscated fonts.

## Implementation

- `EpubReader` now annotates each local manifest item with
  `duplicatePackagePart`, `duplicatePackagePartIds`, `duplicatePackagePartHrefs`,
  and `duplicatePackagePartTargets`.
- `importReport.manifest` now includes `itemsByPart`,
  `duplicatePackagePartCount`, `duplicatePackageItemCount`,
  `duplicatePackageParts`, `duplicatePackagePartItems`, `diagnostics`, and
  `diagnosticCount` while retaining the existing `count`, `items`,
  `missingItems`, and `externalItems` fields.
- The WordPress EPUB handoff smoke includes a duplicate NCX package-part alias
  and verifies the preflight report.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 2390 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed on missing `duplicatePackagePartCount` with
  `1 test files, 2392 assertions, 1 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 2408 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed with `epub3 package handoff self-test ok`.

## Dependency Closure

No new native PHP support component is needed. This reuses the existing
EPUB/OPF reader, `ZipPackage`, `OpcPackagePath` target normalization, and
WordPress EPUB package handoff example. No Pandoc, Cabal/Haskell runner,
zip/unzip, browser renderer, external converter, online service, live provider
test, or live-service provider test was executed.

Next bounded EPUB3 work should avoid duplicate manifest-part reporting and focus
on non-overlapping package behaviors such as publication-resource relation
diagnostics, remaining media-overlay edge cases, or alternate rendition policy.

# EPUB Manifest Fallback Inventory Roles

Slice: `pandoc-epub-manifest-fallback-inventory`

Date: 2026-06-15

## Summary

This slice keeps EPUB3 package ingestion native to PHP and extends
`EpubPackage` package inventory review metadata so ZIP entries participating in
OPF manifest fallback graphs are visible at inventory time.

The inventory now records fallback and fallback-style participation per package
entry:

- `manifestFallbackRoles`
- `manifestFallbackSourceIds`
- `manifestFallbackChainForIds`
- `manifestFallbackTerminalForIds`
- `manifestFallbackStyleSourceIds`
- `manifestFallbackMissingSourceIds`
- `manifestFallbackStyleChainForIds`
- `manifestFallbackStyleTerminalForIds`

Aggregate inventory part-name lists identify fallback sources, style sources,
missing-fallback sources, content terminals, and style terminals. The same
inventory is propagated through the WordPress import review packet.

## Scope

The change is limited to `lanes/pandoc` and reuses existing OPF fallback
preflight and ZIP package provenance. It does not invoke Pandoc, EPUBCheck,
zip/unzip, ZipArchive, browser renderers, external validators, online services,
live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - Result: 1 file, 2770 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 46 files, 86579 assertions, 0 failures

## Accounting

- `phpPass`: 3670 -> 3671
- Mapped upstream manifest cases: 3705 -> 3706
- `mappedEpubManifestFallbackInventoryCases`: 1
- `epubManifestFallbackInventoryAssertions`: 26

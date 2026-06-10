# EPUB3 Package Metadata Inheritance Current Base Slice

Date: 2026-06-10 UTC
Bead: plib-gvnq

## Scope

This slice covers compact EPUB3 package ingestion in native PHP. `EpubPackage` now preserves inherited `xml:lang` and `dir` context from OPF package, metadata, and collection ancestors when building DC metadata records, OPF meta records, metadata links, and collection metadata.

The focused regression uses a bounded in-memory `ZipPackage` fixture and verifies:

- package-root `xml:lang` and `dir` inheritance on title, subject, modified metadata, alternate-script refinements, and metadata links;
- child-level language/direction overrides;
- collection-root context inherited into nested collection metadata;
- WordPress import summary metadata handoff parity.

## Accounting

- `benchmarkDenominator.mapped`: 3140 -> 3141.
- `mappedEpub3PackageCoreCases`: 8 -> 9.
- `epub3PackageCoreAssertions`: 152 -> 174.
- `phpPass`: 2979 -> 2980.
- `phpFail`: 0.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 file / 774 assertions / 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files / 60362 assertions / 0 failures

No Pandoc, EPUBCheck, zip/unzip, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were executed.

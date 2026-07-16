# Pandoc EPUB Duplicate Manifest Property Tokens - 2026-06-15

Slice: `plib-t5num` / EPUB3 package ingestion.

`EpubPackage` now reports duplicate OPF manifest item `properties` tokens in
the resource-property vocabulary review. The duplicate diagnostics are attached
to each item's `propertyVocabulary`, aggregated in `resourceProperties`, and
mirrored through the WordPress import `resourcePropertyDiagnostics` handoff.

The fixture keeps duplicate unprefixed and prefixed tokens visible alongside
existing unknown-prefix and invalid-token diagnostics. No Pandoc binary,
EPUBCheck, `zip`/`unzip`, `ZipArchive`, browser renderer, external validator,
online service, live provider test, or live-service provider test is part of
this slice.

Accounting:

- `phpPass`: `15239 -> 15240`
- `phpFail`: `0`
- mapped upstream manifest cases: `14904 -> 14905`
- root mapped inventory: `14911 -> 14912`
- `mappedEpubDuplicateManifestPropertyTokenCases`: `0 -> 1`
- `epubDuplicateManifestPropertyTokenAssertions`: `0 -> 25`
- rebase base: `198791d09b`

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` passed `1`
  file, `3564` assertions, `0` failures
- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
- `php tools/run-tests.php lanes/pandoc/tests` passed `180` files,
  `164502` assertions, `0` failures after rebase onto current main

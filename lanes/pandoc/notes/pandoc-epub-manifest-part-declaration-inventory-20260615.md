# Pandoc EPUB Manifest Part Declaration Inventory - 2026-06-15

`EpubPackage` now groups OPF manifest item declarations by resolved ZIP package part in `packageInventory`.

The report preserves duplicate manifest declarations that target the same package part, including declaration indexes, ids, hrefs, media types, selected declaration provenance, resource kinds, byte exposure policy, ZIP byte metadata, duplicate-declaration diagnostics, and WordPress import mirrors. Existing package validation still emits the `duplicate-manifest-part-target` diagnostic; the new inventory rows give import reviewers the grouped handoff needed to inspect all declarations without exposing package payload bytes.

No Pandoc binary, EPUBCheck, `zip`/`unzip`, `ZipArchive`, browser renderer, external validator, online service, live provider test, or live-service provider test is part of this slice.

Metric movement:

- `phpPass`: `3715 -> 3716`
- `phpFail`: `0`
- mapped upstream manifest cases: `3737 -> 3738`
- `mappedEpubManifestPartDeclarationInventoryCases`: `0 -> 1`
- `epubManifestPartDeclarationInventoryAssertions`: `0 -> 35`

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` passed `1` file, `3242` assertions, `0` failures
- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
- `php tools/run-tests.php lanes/pandoc/tests` passed `46` files, `88052` assertions, `0` failures

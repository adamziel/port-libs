# EPUB OCF Rootfile Media-Type Parameters

- Bead: `plib-0a9pg`
- Base: `origin/main` `2cea4fa785b868a6fa27c96e3ade52a6d7295957`
- Slice: bounded EPUB package ingestion rootfile media-type parameter provenance

## Scope

EPUB OCF `META-INF/container.xml` rootfiles may carry MIME parameters on the OPF media type. This slice keeps the raw rootfile `media-type`, exposes the normalized base type and parameter map/count, and selects OPF package documents by base media type so parameterized primary and alternate renditions stay reviewable.

The compact `EpubPackage` validation report now mirrors the same rootfile raw/base/parameter provenance, including selected OPF classification and duplicate-part diagnostics, without changing manifest item media handling.

## Verification

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php lanes/pandoc/tests/EpubPackageTest.php` passed `2 test files, 5260 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` passed `44 test files, 64425 assertions, 0 failures`

No Pandoc, EPUBCheck, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests are required for this slice.

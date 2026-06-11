# EPUB3 Container Rootfile Media-Type Parameters

Slice: `plib-b20tk`, EPUB3 package ingestion core blocker.
Base: `origin/main` `1d859c516`.

## Scope

EPUB OCF container rootfiles already carried package ZIP provenance into validation and summary handoff. This slice keeps rootfile MIME parameter provenance visible while selecting the OPF package rootfile by base media type, so a parameterized `application/oebps-package+xml` declaration remains loadable.

## Change

- Preserve raw, normalized, base, parameter-list, parameter-map, syntax-valid, and diagnostic media-type fields on parsed container rootfiles.
- Expose those fields in rootfile validation rows while retaining byte, CRC, compression, and selected-rootfile provenance.
- Add rootfile media-type parameter and diagnostic rollups for package review handoff.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`: 1 test file, 1439 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 65567 assertions, 0 failures.

No Pandoc, EPUBCheck, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

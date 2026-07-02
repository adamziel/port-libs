# ODF Database Package Metadata

`plib-8wteg` completes a bounded ODF/ODT `Database/` package metadata slice without reading database payloads into document media.

- Rich `OdfReader::readPackage()` and compact `OpenDocumentPackage::summarize()` now expose `fileCount`, `storedPartCount`, and `databaseKindCounts` on `packageDatabases`.
- The focused fixture covers both lower-case `database/` and upper-case `Database/` roots, including declared, missing, encrypted, undeclared, and invalid declared-size records.
- Database package bytes remain metadata-only under `database-package-bytes-blocked` or `encrypted-resource-bytes-blocked`, and document media handoff still excludes database sidecars.
- Validation: `php -l` for `OdfReader.php`, `OpenDocumentPackage.php`, and `OdfReaderDatabasePackageSidecarTest.php`; `php tools/run-tests.php lanes/pandoc/tests/OdfReaderDatabasePackageSidecarTest.php` passed with 1 file, 166 assertions, and 0 failures; adjacent ODF package gate with database, object replacement, embedded object media-type, identity role flags, and package script tests passed with 5 files, 688 assertions, and 0 failures.

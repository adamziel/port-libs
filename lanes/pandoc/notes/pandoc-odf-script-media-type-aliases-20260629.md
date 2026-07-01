# pandoc-odf-script-media-type-aliases-20260629

Slice: `plib-l8xvy`

Implemented a bounded ODF/ODT package-ingestion slice for extensionless script
sidecars declared by manifest media type.

- `OdfReader` now classifies extensionless `Scripts/*` package members with
  `application/x-python`, BeanShell aliases, and Java VM media types in both
  `packageScripts` and richer `scriptMetadata`.
- `OpenDocumentPackage` compact summaries now classify extensionless
  `application/x-python` script parts as `python`, matching rich reader package
  metadata.
- Script payload bytes remain metadata-only under `script-package-bytes-blocked`
  and do not enter document media or WordPress handoff.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php`
  passed with 1 file, 208 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  passed with 2 files, 2,327 assertions, 0 failures.

Accounting:

- Added `mappedOdfScriptMediaAliasCases: 1`.
- Added `odfScriptMediaAliasAssertions: 52`.
- ODF/ODT readiness local mapped cases moved 95 -> 96; focused assertions
  moved 2,303 -> 2,355.

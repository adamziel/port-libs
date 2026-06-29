# pandoc-odf-beanshell-package-script-aliases-20260629

Slice: `plib-l8xvy`

Implemented a bounded ODF/ODT package-ingestion slice for BeanShell package
script sidecars.

- `OdfReader` and `OpenDocumentPackage` now accept `text/x-beanshell` and
  `application/x-bsh` alongside `application/x-beanshell` for `.bsh` entries.
- Rich reader and compact package summaries classify `Scripts/*.bsh` entries as
  `beanshell` package scripts.
- Script bytes remain metadata-only under `script-package-bytes-blocked` and do
  not enter document media or WordPress handoff.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php`
  passed with 1 file, 156 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  passed with 2 files, 2,275 assertions, 0 failures.

Accounting:

- Added `mappedOdfBeanShellPackageScriptCases: 1`.
- Added `odfBeanShellPackageScriptAssertions: 35`.
- ODF/ODT readiness local mapped cases moved 94 -> 95; focused assertions
  moved 2,268 -> 2,303.

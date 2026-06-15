# pandoc-epub-container-rootfile-matrix-current-base-20260615T0705Z

Slice: `plib-01zp1`, EPUB3 package ingestion.

`EpubPackageReader` now preserves the OCF `META-INF/container.xml` rootfile declaration matrix for compact directory imports. The import packet exposes every rootfile declaration with selected-rootfile provenance, media-type base/parameter state, local/external/unsafe/missing package-part policy, query/fragment suffix diagnostics, and aggregate summary counters while still selecting the readable OPF package document.

The implementation is bounded to native PHP under `lanes/pandoc`. It does not invoke Pandoc, EPUBCheck, `zip`/`unzip`, `ZipArchive`, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php` - 1 file, 1171 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` - 46 files, 86906 assertions, 0 failures.
- `git diff --check -- lanes/pandoc/src/EpubPackageReader.php lanes/pandoc/tests/EpubPackageReaderTest.php`

Metric/accounting delta:

- `phpPass`: `3679 -> 3680`
- `phpFail`: `0`
- `mappedEpubContainerRootfileDeclarationMatrixCases`: `1`
- `epubContainerRootfileDeclarationMatrixAssertions`: `33`

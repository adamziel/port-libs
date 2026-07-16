# pandoc-epub3-page-list-compact-package-current-base-20260610T155412Z

Slice: `plib-90wv` / EPUB3 package ingestion.

This slice extends the compact native PHP `EpubPackageReader` package path to
ingest EPUB3 navigation documents with `epub:type="page-list"` sections. The
reader already mapped `toc` and `landmarks`; it now preserves print page-list
entries in the same navigation handoff shape with label, raw href, resolved
package path, fragment, type, and nested children.

The focused fixture now includes a bounded page-list navigation section pointing
at existing XHTML spine fragments. The focused regression asserts both page
targets and verifies that the compact reader keeps page-list provenance alongside
the existing TOC, landmarks, NCX, manifest, spine, and XHTML body import paths.

Verification:

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  - 1 file, 99 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 60517 assertions, 0 failures

No Pandoc, EPUBCheck, zip/unzip command, browser renderer, external validator,
online service, live provider test, or live-service provider test was invoked.

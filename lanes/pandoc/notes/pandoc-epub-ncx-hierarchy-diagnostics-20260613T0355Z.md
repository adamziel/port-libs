# EPUB NCX Hierarchy Diagnostics

Implemented a bounded native PHP EPUB3 package-reader slice for compact
nav/NCX review metadata.

- `EpubPackageReader` now preserves pending XHTML nav and NCX
  `labelProvenance` on outline entries.
- `epub.ncxReport` now summarizes flattened NCX point counts, top-level point
  counts, max hierarchy depth, missing `playOrder` counts, non-increasing
  positive `playOrder` diagnostics, and duplicate target diagnostics.
- The legacy `epub.ncx` outline remains intact so existing consumers keep the
  same hierarchy, labels, paths, fragments, play orders, and children.

Verification:

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

The final full run after rebasing onto `1f2129da33` passed 46 files, 76366
assertions, and 0 failures.

No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, Node
tooling, online service, live provider test, or external validator was invoked.

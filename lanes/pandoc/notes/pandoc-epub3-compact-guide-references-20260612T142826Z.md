# pandoc-epub3-compact-guide-references-20260612T142826Z

Slice: `plib-v4s1g` / EPUB3 package ingestion.

This slice extends the compact native PHP `EpubPackageReader` package path to
preserve OPF `<guide>` references for package review handoff. The reader now
reports guide presence, typed and untyped reference counts, ordered type
vocabulary buckets, resolved package paths and fragments, manifest id/media type
linkage, missing internal-target diagnostics, and missing type diagnostics.

The focused fixture covers an existing cover asset, an XHTML start-reading
fragment, a missing glossary target with two guide types, and an untyped existing
XHTML reference. The compact reader exposes this metadata under the document
`epub.guide` attribute without changing spine content import.

Verification:

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  - 1 file, 149 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 71752 assertions, 0 failures

Lane accounting:

- `phpPass`: `3225 -> 3226`
- `mappedEpubCompactGuideReferenceCases`: `1`
- `epubCompactGuideReferenceAssertions`: `36`
- Mapped denominator noted in lane status: `3245 -> 3246`

No Pandoc, EPUBCheck, `zip`/`unzip`, ZipArchive, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked.

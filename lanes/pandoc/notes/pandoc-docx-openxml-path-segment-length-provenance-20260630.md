# DOCX/OpenXML path segment length provenance

Slice: `plib-70tfy`, DOCX OpenXML package ingestion core blocker.

## Scope

- Adds per-part `pathSegmentLengthReviews`, `pathSegmentByteLengths`, `pathSegmentMaxByteLength`, `pathSegmentMinByteLength`, and `pathSegmentLengthBuckets` metadata to DOCX package inventory rows.
- Adds package-level `partPathSegmentLengths` summaries keyed by segment byte-length buckets, preserving occurrence counts, unique segment counts, package byte totals, part names, directories, content-type source/base counts, role counts, relationship/missing/parameterized part counts, path segment indexes, and largest-part digest metadata.
- Keeps the slice metadata-only: package entry bytes stay blocked, and the review surface exposes names, lengths, counts, buckets, and existing digests.
- Does not claim phpPass or upstream mapped denominator movement.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result after rebase on `origin/integration/pandoc-package-docx` `c971c31f3`: `1 test files, 13686 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase on `origin/integration/pandoc-package-docx` `c971c31f3`: `295 test files, 120678 assertions, 9781 failures`
  - First failures are outside this slice in `DocBookReaderTest.php`, `HtmlWriterGlobalAttributeReviewTest.php`, `LatexWriterTest.php`, and markdown/Unicode/YAML tests; no `DocxOpenXmlReaderTest.php` failure was reported.

No Pandoc, cmark/commonmark runners, Cabal/Haskell runners, Word, LibreOffice, office suites, zip/unzip, TeX/PDF engines, browser renderers, Node tooling, external validators, online services, live provider tests, or live-service provider tests were invoked.

# DOCX/OpenXML path segment position provenance

Slice: `plib-dggzb`, DOCX OpenXML package ingestion core blocker.

## Scope

- Adds per-part `pathSegmentPositionReviews`, `pathSegmentPositionCounts`, `pathSegmentFirstSegment`, `pathSegmentLastSegment`, and `pathSegmentHasOnlySegment` metadata to DOCX package inventory rows.
- Adds package-level `partPathSegmentPositions` summaries keyed by `first`, `middle`, `last`, and `only`, preserving occurrence counts, part counts, segment buckets, path-segment index buckets, directories, content-type source/base counts, role counts, relationship/missing/parameterized part counts, and largest-part digest metadata.
- Keeps the slice metadata-only: package entry bytes stay blocked, and the review surface exposes names, positions, counts, buckets, and existing digests.
- Direct-format parity accounting is unchanged; focused PHP behavior coverage moves from `481` to `482` passing tests for this added DOCX package provenance slice.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result before final rebase: `1 test files, 14456 assertions, 0 failures`

No Pandoc, cmark/commonmark runners, Cabal/Haskell runners, Word, LibreOffice, office suites, zip/unzip, TeX/PDF engines, browser renderers, Node tooling, external validators, online services, live provider tests, or live-service provider tests were invoked.

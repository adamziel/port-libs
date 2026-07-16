# DOCX/OpenXML Content-Type Subtype Buckets

Current-base slice for `plib-oaoh6`.

- Scope: native PHP DOCX/OpenXML package ingestion metadata only.
- Base: `origin/main` at `5079fe2136`.
- Implementation: `DocxOpenXmlReader` now summarizes package parts by content-type subtype with deterministic bucket counts, source/base/media-type rollups, default extensions, override part names, role counts, byte totals, and largest-part provenance.
- Fixture: `DocxOpenXmlReaderTest.php` adds one package fixture covering XML, relationship, PNG, SVG, text/plain, octet-stream, vendor JSON, invalid, and missing content-type subtype buckets.
- Accounting: adds `mappedDocxOpenXmlContentTypeSubtypeBucketCases = 1` and `docxOpenXmlContentTypeSubtypeBucketAssertions = 53`; `phpPass` moves `17105 -> 17106`; upstream mapped moves `16691 -> 16692`; root mapped moves `16660 -> 16661`; benchmark denominator moves `3829 -> 3830`.
- Verification target: focused `DocxOpenXmlReaderTest.php` at `1 file / 7433 assertions / 0 failures`; full `lanes/pandoc/tests` at `260 files / 179255 assertions / 0 failures`.

No Pandoc, cmark/commonmark runners, Cabal/Haskell runners, Word, LibreOffice, office suites, zip/unzip, TeX/PDF engines, browser renderers, Node tooling, external validators, online services, live provider tests, or live-service provider tests are invoked.

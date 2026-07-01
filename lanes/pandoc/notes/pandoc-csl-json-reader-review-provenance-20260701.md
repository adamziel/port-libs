# Pandoc CSL JSON Reader Review Provenance

Slice: `plib-mancn` follow-up, Pandoc citation/bibliography CSL core blocker.

## Behavior

- `BibliographyReader` now attaches metadata-only CSL JSON reader review
  provenance for direct `csljson` bibliography inputs.
- The document carries `cslJsonReview`, `cslJsonItemReviews`, and the same
  review under `bibliography.cslJsonReview`.
- The review summarizes item ids, field names, item types, title-bearing
  records, CSL name/date variable counts, identifier fields, link-bearing
  fields, and relation fields.
- Source values for titles, URLs, DOIs, keywords, categories, references, and
  ISBNs remain omitted from the review payload.

## Evidence

- Added focused case:
  `records metadata only csl json reader review provenance`.
- `php -l lanes/pandoc/src/BibliographyReader.php`
- `php -l lanes/pandoc/tests/BibliographyReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php`
  passed with `1 test files, 67 assertions, 0 failures`.
- Broader citation/bibliography gate:
  `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  passed with `3 test files, 6884 assertions, 0 failures`.
- `lane-status.json` `phpPass`: `472 -> 473`.

## Non-Overlap

The original `plib-mancn` note covers CSL style variable rendering for direct
import aliases such as `publicationStatus`, `keywordList`, `categoryList`, and
camel citation alias variables. This follow-up does not change CSL rendering,
normalization aliases, BibTeX/BibLaTeX parsing, citation sorting, WordPress
output, or bibliography text. It only adds bounded reader-level provenance for
successful direct CSL JSON bibliography ingestion.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, office suite,
TeX/PDF engine, browser renderer, external validator, online service, live
provider test, or live-service provider test was executed.

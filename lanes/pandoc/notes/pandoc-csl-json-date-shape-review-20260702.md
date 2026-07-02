# CSL JSON date-shape review provenance

Date: 2026-07-02
Slice: `plib-h7b21`

`BibliographyReader('csljson')` now carries metadata-only date-shape rollups
for direct CSL JSON bibliography inputs. The review records range endpoint
counts, open-ended date variables and directions, and literal-date variables at
both bibliography and item scope.

The review payload continues to omit source titles and literal date strings and
does not invoke Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser
engines, office suites, or external validators.

Direct-format parity accounting:

- `mappedCslJsonDateShapeReviewCases`: `1`
- `cslJsonDateShapeReviewAssertions`: `30`

Validation:

- `php -l lanes/pandoc/src/BibliographyReader.php`
- `php -l lanes/pandoc/tests/BibliographyReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php`
  - 1 file, 376 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - 3 files, 7588 assertions, 0 failures

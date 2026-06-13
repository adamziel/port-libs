# XML/HTML5 JATS/BITS Section Title Metadata

Bead: `plib-srekb`
Date: 2026-06-13 UTC
Base: origin/main `d1e41a7720`

This slice adds bounded native PHP JATS/BITS review metadata for article/book
titles and nested section title propagation in `XmlHtmlDom`.

- `summarizeJatsFrontMatter()` now emits `titleMetadata` and
  `subtitleMetadata` records with the selected source element.
- JATS section summaries now carry recursive `parentId`, `depth`,
  `directParagraphCount`, `childSectionCount`, `titlePath`, and
  `titlePathText` metadata.
- Aggregate `sectionTitlePaths` and `sectionTitlePathText` fields expose nested
  title chains for reviewer handoff.
- `directReaderParity` remains `false`; this is still a review packet, not a
  full JATS/BITS direct reader.

Accounting:

- `phpPass`: `3321 -> 3322`
- `phpFail`: remains `0`
- `mappedXmlHtmlDomJatsFrontMatterReviewCases`: `1 -> 2`
- `xmlHtmlDomJatsFrontMatterReviewAssertions`: `45 -> 69`
- `UPSTREAM_TEST_MANIFEST.json` mapped cases: `3280 -> 3281`

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1` file, `1852` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php`
  - `5` files, `4787` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - `45` files, `74537` assertions, `0` failures

No Pandoc binary, Cabal/Haskell runner, browser renderer, external XML
validator, online service, live provider test, or live-service provider test was
invoked.

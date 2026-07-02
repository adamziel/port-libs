# XML/HTML5 DOM Image Map Association Review - 20260702

Slice: `plib-czj5e`

## Scope

`XmlHtmlDom` now adds compact review rollups for client image-map handoff:

- `img usemap` association issue codes, issue counts, validity, and no-browser
  hit-testing provenance for resolved, missing, duplicate, and invalid map
  references.
- `map` association issue codes, issue counts, validity, and review status for
  referenced, unreferenced, duplicate-name, and invalid-name maps.
- Top-level area geometry issue-code summaries for client image maps while
  preserving detailed per-area geometry records.

The implementation remains metadata-only. It does not invoke browser hit
testing, layout, resource loading, navigation, Pandoc, Node, or external
validators.

## Status Movement

- `phpPass`: `481 -> 482`
- `mappedXmlHtmlDomImageMapAssociationReviewCases`: `+1`
- `xmlHtmlDomImageMapAssociationReviewAssertions`: `+53`

## Validation

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomImageMapAssociationReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomImageMapAssociationReviewTest.php`
  - Result: 1 file, 53 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomImageMapAssociationReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: 2 files, 6,277 assertions, 0 failures.

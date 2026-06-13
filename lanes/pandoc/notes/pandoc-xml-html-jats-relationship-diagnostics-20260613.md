# Pandoc XML/HTML JATS Relationship Diagnostics

Slice: `pandoc-xml-html-jats-relationship-diagnostics`
Issue: `plib-g65c3`
Base: origin/main `6847e50304`

## Behavior

`XmlHtmlDom::summarizeJatsFrontMatter()` now carries a metadata-only `relationshipDiagnostics` packet for JATS/BITS XML review inputs. The packet summarizes figure, table-wrap, and bibliographic reference targets, per-target xref counts, resolved and unresolved xrefs, missing `rid` attributes, and `ref-type` target mismatches.

This keeps `directReaderParity=false` and does not attempt JATS/BITS body, back matter, figure, table, citation, or full direct-reader parity.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1` file, `2161` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php`
  - `5` files, `5096` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46` files, `76047` assertions, `0` failures

No Pandoc binary, browser renderer, external XML validator, online service, live provider test, or live-service provider test was invoked.

## Status Delta

- `lane-status.json` `phpPass`: `3367 -> 3368`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3327 -> 3328`
- `mappedXmlHtmlDomJatsRelationshipDiagnosticCases`: `1`
- `xmlHtmlDomJatsRelationshipDiagnosticAssertions`: `28`

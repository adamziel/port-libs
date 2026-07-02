# XML/HTML DOM link icon sizes review

`XmlHtmlDom::summarizeHtmlFragment()` now emits additive metadata-only review
provenance for HTML `link` `sizes` attributes. The summary preserves the raw
attribute and ordered tokens, normalizes valid `any` and `<width>x<height>`
tokens, records valid/unique token lists, reports dimensions and duplicates,
and surfaces invalid tokens plus `sizes` attributes on non-icon links.

The slice preserves raw HTML serialization and WordPress raw HTML handoff. It
does not fetch icon resources, inspect image bytes, submit browser requests,
invoke Pandoc, or run external validators.

## Manifest

- `mappedXmlHtmlDomLinkIconSizesReviewCases`: `1`
- `xmlHtmlDomLinkIconSizesReviewAssertions`: `27`
- `mapped`: `2883 -> 2884`

## Validation

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomLinkIconSizesReviewTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomLinkIconSizesReviewTest.php`

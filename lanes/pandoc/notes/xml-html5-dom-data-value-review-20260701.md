# XML/HTML5 DOM data value review

Slice: `plib-ul9gd` XML/HTML5 DOM core blocker.

`XmlHtmlDom::summarizeHtmlFragment()` now carries additive
`html-data-value-review` metadata for `<data>` elements. Existing raw and
normalized value provenance remains intact, while the summary now exposes
reviewer-metadata eligibility, byte lengths, conformance flags, issue records,
and stable issue codes for missing, empty, unsafe, and oversize values.

The boundary mirrors the existing `Html5DomFragment` value-metadata policy for
safe inert WordPress handoff: cleaned nonempty values up to 256 bytes are
eligible, and values containing unsafe token characters stay diagnostic-only.
Serialized HTML remains unchanged.

Manifest counters:
- `mappedXmlHtmlDomDataValueReviewCases`: `1`
- `xmlHtmlDomDataValueReviewAssertions`: `57`

Focused validation:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomDataValueReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomDataValueReviewTest.php`

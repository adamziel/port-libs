# XML/HTML DOM Fieldset Review Packet

Date: 2026-07-01
Hook: plib-mkcz1

`XmlHtmlDom::summarizeHtmlFieldsetReviewPacket()` now exposes a bounded, metadata-only HTML fieldset review packet for raw HTML/HTML5 DOM handoff. The packet reuses existing per-fieldset diagnostics and adds rollups for:

- fieldset ids and `name` attributes
- form owner provenance
- legend counts and legend text summaries
- enabled and disabled control reference counts
- nested fieldset references
- missing/multiple legend and nested-fieldset issue codes

The slice does not submit forms, inspect file input contents, run browser behavior, invoke upstream Pandoc, or call external validators. Direct-format parity accounting remains active in `UPSTREAM_TEST_MANIFEST.json` via `mappedXmlHtmlDomFieldsetReviewPacketCases=1` and `xmlHtmlDomFieldsetReviewPacketAssertions=70`.

Focused validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomFieldsetReviewPacketTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFieldsetReviewPacketTest.php`

# XML/HTML5 DOM Fragment Tree Review Packet

`XmlHtmlDom::summarizeHtmlFragmentReviewPacket()` now wraps the existing
HTML5 fragment node summaries with aggregate tree metadata for reviewer handoff.

The packet reports top-level node count, total node/element/text/comment counts,
maximum summary depth, element-name frequencies, void/raw-text/active-content
rollups, and aggregate `id`, class, `data-*`, and ARIA attribute provenance. It
also preserves the existing per-node `summarizeHtmlFragment()` payload under
`nodes`, so direct fragment detail remains available without reparsing.

This remains review-only DOM support. The packet reports
`directReaderParity=false` with `html-fragment-tree-summary-review-only` and
does not claim Pandoc direct reader parity.

Verification:

```bash
php -l lanes/pandoc/src/XmlHtmlDom.php
php -l lanes/pandoc/tests/XmlHtmlDomTest.php
php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php
```

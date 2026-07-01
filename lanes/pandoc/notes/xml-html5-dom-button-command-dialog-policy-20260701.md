# XML/HTML5 DOM Button Command Dialog Policy - 2026-07-01

Scope: HTML/HTML5 DOM recovery only.

`XmlHtmlDom::buttonCommandTargetSummary()` now carries dialog policy metadata
through button `commandfor` target summaries. Dialog command targets reuse the
existing dialog review helpers for `closedby` normalization, invalid-token issue
codes, method-dialog form counts, and dialog close values, so downstream HTML
and EPUB handoffs can audit button-triggered dialog behavior without invoking a
browser, Pandoc, or external validators.

Focused validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomButtonCommandDialogPolicyTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomButtonCommandDialogPolicyTest.php lanes/pandoc/tests/XmlHtmlDomDialogClosedByTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`

Direct-format parity remains tracked in lane status; this slice adds DOM review
metadata and does not claim broad HTML/XML/Pandoc parity.

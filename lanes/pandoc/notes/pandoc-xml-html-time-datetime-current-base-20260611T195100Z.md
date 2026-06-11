# XML/HTML5 DOM Time Element Datetime Provenance

Slice: `plib-dltnu` on 2026-06-11.

This native PHP slice extends `XmlHtmlDom::summarizeHtmlFragment()` so HTML
`time` elements preserve reviewer-visible datetime provenance without invoking
Pandoc, browser renderers, external validators, online services, or live
provider tests.

Mapped behavior:
- captures raw `datetime` values and whether they came from the attribute or
  fallback text;
- normalizes valid global datetime, local datetime, date, month, week, year,
  time, and duration forms;
- preserves invalid datetime values as inert review metadata instead of
  dropping provenance.

Focused coverage lives in `lanes/pandoc/tests/XmlHtmlDomTest.php`.
Verification counts are recorded in `lane-status.json` and
`UPSTREAM_TEST_MANIFEST.json` after the focused and full Pandoc PHP gates.

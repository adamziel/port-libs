# XML/HTML5 DOM Autofocus Order Review

This slice adds explicit reviewer handoff rollups for HTML `autofocus`
candidate summaries in `XmlHtmlDom::summarizeHtmlFragment()`.

- Autofocus packets now expose review status, issue counts, order issue counts,
  and an explicit metadata-only/no-browser-focus handoff policy.
- Document-order conflicts continue to preserve all candidate ids, element
  names, current candidate, first candidate, and previous candidate metadata.
- Later `autofocus` candidates report suppression through
  `autofocus-suppressed-by-earlier-candidate` without invoking browser focus
  behavior.

Focused coverage lives in
`lanes/pandoc/tests/XmlHtmlDomAutofocusOrderReviewTest.php` and exercises form
controls, disabled controls, textarea, hyperlink, and tabindex candidates
through raw HTML serialization and WordPress block handoff.

This does not call browser focus APIs, dispatch focus events, fetch resources,
shell out to Pandoc, or invoke external validators. It only improves bounded
XML/HTML5 DOM review metadata for a core blocker edge.

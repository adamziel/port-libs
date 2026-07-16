## xml-html5-dom-microdata-attributes-20260612T025940Z

Bead: `plib-0wlmx`

Slice: Pandoc XML/HTML5 DOM core blocker, bounded to low-level HTML5
microdata attribute review summaries.

Changed `XmlHtmlDom::summarizeHtmlFragment()` so elements carrying
`itemscope`, `itemtype`, `itemid`, `itemref`, or `itemprop` now expose
additive reviewer metadata without changing parsed DOM serialization:

- item scopes preserve raw boolean state and classify the element as a
  microdata item.
- `itemtype` and `itemprop` expose raw values, token order, deduplicated valid
  tokens, invalid tokens, and validity booleans.
- `itemid` preserves raw and trimmed values with bounded token safety.
- `itemref` exposes raw tokens, valid ID references, invalid references, and
  resolved/missing IDs in the current DOM document.

This does not repeat the higher-level `Html5DomFragment` sanitizer microdata
conversion work. It only closes the lower-level XML/HTML5 DOM summary gap for
callers that inspect parsed fragments before sanitizer handoff.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` (1 file,
  1279 assertions)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 69836 assertions)

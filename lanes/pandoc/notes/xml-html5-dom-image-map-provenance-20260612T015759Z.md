# XML/HTML5 DOM Image Map Provenance

Slice: `plib-jidsn`

This slice extends the native HTML5 DOM fragment summary for image-map handoff.

- `img` summaries now preserve `usemap` provenance only when the attribute is
  present, including the raw value, resolved map name, and validity.
- `map` summaries now expose the map name, validity, area count, area hrefs,
  area labels, and compact area hyperlink summaries.
- Existing `area` hyperlink summaries remain unchanged and continue to carry
  `href`, `alt` label, `shape`, `coords`, `rel`, and target metadata.

Focused coverage lives in `lanes/pandoc/tests/XmlHtmlDomTest.php`.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 1 test file, 1201 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 68842 assertions, 0 failures

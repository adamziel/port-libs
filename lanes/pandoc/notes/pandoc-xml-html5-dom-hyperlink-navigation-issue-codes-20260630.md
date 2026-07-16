# Pandoc XML/HTML5 DOM Hyperlink Navigation Issue Codes

Hook: `plib-utpnp`, Pandoc XML/HTML5 DOM core blocker slice 20260615T081643Z.

This slice keeps the native PHP XML/HTML DOM work bounded to hyperlink
navigation metadata. `XmlHtmlDom::summarizeHtmlFragment()` now carries compact
navigation issue-code summaries for `<a>` and `<area>` records alongside the
existing detailed `navigationIssues` records:

- `navigationIssueCodes`
- `navigationIssueCount`
- `hyperlinkNavigationValid`
- `pingRequested`
- `pingRawEmpty`

The new `empty-ping-url-list` diagnostic preserves reviewer-visible provenance
when a `ping` attribute is present but has no URL tokens. Existing unsafe href,
explicit opener, invalid or duplicate rel, invalid referrer policy, unsafe ping,
and non-HTTP ping records are unchanged; the new issue-code fields make those
records easier for package/import handoff code to gate without re-parsing the
issue payloads.

No browser engine, Pandoc process, external sanitizer, URL fetcher, or external
validator was used.

Focused validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomHyperlinkNavigationIssueCodesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomHyperlinkNavigationIssueCodesTest.php`
  passed with 1 file, 46 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  passed with 1 file, 6,224 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php`
  passed with 37 files, 10,510 assertions, 0 failures.

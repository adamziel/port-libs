# pandoc-xml-html5-dom-core-current-base-20260607T163352Z

Accepted base: `d2e7be788a40ae9de50a145789df72120ce1ffab`

## Behavior

This slice adds bounded XML/HTML5 DOM sanitizer support for semantic HTML
metadata before WordPress raw HTML handoff. `Html5DomFragment` now converts
source microdata and RDFa attributes into sanitizer-owned inert
`data-pandoc-*` reviewer metadata:

- microdata: `itemscope`, `itemtype`, `itemid`, `itemprop`, and `itemref`
- RDFa: `about`, `resource`, `vocab`, `prefix`, `property`, `typeof`,
  `datatype`, and `inlist`

URL-valued metadata is resolved through the existing trusted base-URL path and
unsafe semantic URLs are rejected. Malformed semantic term tokens are stripped.
The active source attributes are not preserved in serialized review HTML.

## Evidence

- Baseline focused check before the implementation:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 947 assertions, 0 failures`.
- Exploratory pre-edit DOM output preserved active `itemscope`, `itemtype`,
  `itemid`, `itemprop`, `property`, `typeof`, `about`, `resource`, `vocab`,
  and `prefix` attributes, including unsafe semantic URL inputs.
- Final focused check:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 975 assertions, 0 failures`.
- DOM family check:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 1248 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed.

## Mapping Delta

- `lane-status.json` `phpPass`: `1531 -> 1532`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1950 -> 1951`
- `xmlHtmlDomCoreCases`: `7 -> 8`
- `mappedXmlHtmlDomCoreCases`: `7 -> 8`
- `xmlHtmlDomCoreAssertions`: `103 -> 131`

## Dependency Closure

No new support component is needed. The slice reuses the native
`Html5DomFragment` DOM/libxml `NONET` sanitizer, existing safe URL and
base-resolution helpers, `AstNode` raw HTML blocks, `WordPressBlockWriter`,
focused DOM tests, and the existing WordPress HTML5 DOM fragment handoff
example.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Non-overlap

This does not repeat the accepted XML/HTML5 DOM slices for title/language
metadata, iframe policy metadata, passive link relations, safe SVG data-image
resources, optgroup/option label fallback text, SVG/MathML CDATA preservation,
form/fetch URL filtering, or srcset candidate filtering. It is limited to
inert semantic metadata attribute handoff.

## Follow-up

A useful next XML/HTML5 DOM slice would cover source-position diagnostics, URL
percent-decoding policy, or XHTML/XML namespace edge handoff without invoking
external converters or browser renderers.

# XML/HTML5 DOM Resource URL Base Provenance Slice

Session: `port-libs/polecats/1763/plib-6m14x`
Micro-slice: `xml-html5-dom-resource-url-base-provenance-20260701`

## Scope

This slice adds a native PHP HTML fragment resource URL review primitive:

- `XmlHtmlDom::summarizeHtmlFragmentResourceUrls()`
- active fragment `<base href>` detection and resolution against an optional
  provided base URL
- deterministic resolution for package-relative, root-relative, fragment,
  scheme-relative, and absolute URL references
- bounded records for URL-bearing HTML attributes, `srcset`/`imagesrcset`
  candidates, and object `param valuetype="ref"` references
- unsafe/unusable/unresolved URL diagnostic codes without fetching resources

The existing `summarizeHtmlFragment()` tree shape and serialized HTML output are
unchanged.

## Parity

This is review-only DOM support, not a direct reader parity claim.
The packet reports `directReaderParity: false` with
`html-fragment-resource-url-review-only`.

## Evidence

- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: `1 test files, 6287 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `4 test files, 9375 assertions, 0 failures`

No Pandoc, browser, Node, external validator, office suite, or network resource
fetching was used.

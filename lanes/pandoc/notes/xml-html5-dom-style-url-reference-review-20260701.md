# XML/HTML5 DOM style URL reference review - 2026-07-01

Slice: `plib-1j99m`

`XmlHtmlDom::summarizeHtmlFragment()` now carries metadata-only CSS `url(...)`
reference review fields on HTML `style` attributes. The style declaration
review remains declaration-oriented, and the new URL handoff records each
bounded URL function with:

- declaration and property provenance;
- raw `url(...)` text and unquoted URL text;
- URL kind, scheme, unsafe flag, and issue codes;
- aggregate reference counts, property/kind/scheme lists, unsafe reference
  records, CSS-escaped URL decoding for classification, and `styleUrlValid`.

The slice does not fetch resources, run CSS, invoke a browser, or call external
validators. It only classifies inline style URL references for reviewer handoff
so importers can audit relative, fragment, remote, empty, `data:`, and active
script-like CSS URL sources alongside existing raw HTML preservation.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomStyleUrlReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomStyleUrlReviewTest.php`
  - 1 file, 45 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomStyleUrlReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - 4 files, 9,218 assertions, 0 failures

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node,
zip/unzip, validators, or live services were invoked.

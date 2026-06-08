## pandoc-xml-html5-dom-core-current-base-20260608T135128Z

Base accepted HEAD: `95ed9a719a03101e72b33de7de15d86db46d9a80`

Implemented one bounded XML/HTML5 DOM support-library slice: sanitized HTML ruby fragments now preserve reviewer metadata for base text, `rt`/`rtc` annotation text, and `rp` fallback punctuation as `data-pandoc-ruby-base`, `data-pandoc-ruby-annotation`, and `data-pandoc-ruby-fallback`. Source-owned `data-pandoc-ruby-*` attributes are stripped before regenerated metadata is added, and active content inside annotations is still removed before WordPress raw HTML handoff.

Source truth and non-overlap:

- Source truth is the HTML ruby model used by Pandoc's HTML reader path: `ruby` carries base text, `rt` carries annotation text, `rtc` groups annotations, and `rp` carries fallback punctuation for clients without ruby rendering.
- This slice is additive to the accepted XML/HTML5 DOM clusters for DTD/entity rejection, XML declarations/PI, comments, raw text/RCDATA/plaintext, SVG/MathML foreign content, CDATA, URL/srcset/data image filtering, base URL handling, iframe policy metadata, form/embed/noscript/template unwrapping, table foster parenting, XML namespaces, passive links, image maps, hidden/inert/dialog/details/popover states, semantic metadata, time/revision/language/translate metadata, media tracks, and source-line diagnostics.
- No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer, external XML/HTML sanitizer, online service, live provider test, or live-service provider test was executed.

Verification:

- Baseline before implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1304 assertions, 0 failures`.
- Red-first after adding the ruby expectations: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` failed with `1 test files, 1305 assertions, 1 failures` because the raw CJK fixture bytes hit libxml HTML encoding repair. The test fixture was adjusted to numeric character references so the same DOM text is exercised deterministically.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1318 assertions, 0 failures`.
- Adjacent family: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 1619 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed.

Status delta:

- `phpPass`: `1659 -> 1660`
- `benchmarkDenominator.mapped`: `2079 -> 2080`
- `mappedXmlHtmlDomCoreCases`: `8 -> 9`
- `xmlHtmlDomCoreAssertions`: `124 -> 138`

Dependency closure:

No new native PHP support component is needed. This reuses `Html5DomFragment` normalization, the existing sanitizer diagnostics, `AstNode` raw-HTML handoff, and `WordPressBlockWriter` raw HTML output. Full upstream Pandoc HTML-reader runner parity, browser rendering, external XML/HTML sanitizers, and custom ruby layout semantics remain out of scope for this micro-slice.

Next non-overlapping XML/HTML5 DOM follow-up:

Slot/template shadow metadata, ARIA role/state review metadata, or HTML custom-element import provenance would fit this lane without repeating the accepted fragment-sanitizer clusters.

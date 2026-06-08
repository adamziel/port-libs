# XML/HTML5 DOM Custom Element Provenance Slice

Slice: `pandoc-xml-html5-dom-core-current-base-20260608T201345Z`

Accepted base: `94d7cef270e305ef6fc0f67053ec55d96bb371c3`

## Behavior

- Added native `Html5DomFragment` custom-element provenance handling for WordPress raw HTML review handoff.
- Autonomous custom elements are converted into inert `div` or `span` fallback elements, with the source tag preserved as trusted `data-pandoc-custom-element` metadata.
- Valid customized built-in `is` values are preserved as `data-pandoc-custom-is`; invalid values are stripped and reported as unsafe attributes.
- Safe `part` token lists and `exportparts` mappings are normalized into `data-pandoc-custom-part` and `data-pandoc-custom-exportparts` metadata.
- Source-owned `data-pandoc-custom-*` spoofing is stripped before trusted sanitizer metadata is emitted.
- Foreign SVG/MathML element casing and existing HTML URL/resource policies remain on their existing paths.

## Evidence

- Baseline focused check before adding this case: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `1 test files, 1409 assertions, 0 failures`
- Red-first after adding the focused case:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `1 test files, 1410 assertions, 1 failures`
  - Failure showed live custom-element tags, live `is`, and live `part`/`exportparts` hooks were still exposed.
- Final focused check: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `1 test files, 1437 assertions, 0 failures`
  - Delta over the prior focused file baseline: `+28` focused assertions and `+1` PHP PASS case.
- Focused DOM-family check: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `3 test files, 1752 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - `html5 dom fragment handoff self-test ok`

## Dependency Closure

No new native PHP support component is needed. This slice reuses `Html5DomFragment`, existing URL and attribute policy filtering, AST raw HTML handoff, and `WordPressBlockWriter`.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer, external XML/HTML tool, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat earlier XML/HTML5 DOM work for unsafe XML declarations, DTD/entity rejection, raw text/RCDATA/plaintext, SVG/MathML foreign casing, CDATA, URL/srcset/data-image filtering, base URL and target metadata, iframe srcdoc/source/policy metadata, form/select labels, noscript/template fallback unwrapping, table foster parenting, meta/link metadata, image maps, hidden/inert/details/dialog/popover, microdata/RDFa, time/revision/language metadata, media tracks, ruby annotations, source-line diagnostics, declarative shadow-root and slot fallback metadata, ARIA handoff, passive link relation metadata, iframe policy metadata, figure metadata, or reserved `data-pandoc-*` filtering.

Next useful XML/HTML5 DOM follow-up: ARIA role/state review metadata, bounded accessibility relationships, or remaining reviewer provenance that keeps active browser hooks inert.

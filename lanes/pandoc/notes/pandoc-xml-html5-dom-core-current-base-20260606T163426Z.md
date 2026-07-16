# Pandoc XML/HTML5 DOM Core Current Base - Iframe Source Review Links

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T163426Z`

Base accepted HEAD: `9b626637adac74dd83b40dfa99de8ceeabc8d9b2`

## Implementation

- `Html5DomFragment` now converts safe plain `<iframe src=...>` elements with
  no visible fallback content into inert reviewer links.
- Relative frame sources resolve through trusted fragment base metadata before
  WordPress raw HTML handoff.
- Unsafe frame sources such as control-separated `javascript:` are stripped
  with `unsafe-url` diagnostics, and sourceless iframes remain omitted.
- Existing `iframe srcdoc` behavior still takes precedence and continues to
  unwrap/sanitize embedded source HTML rather than exposing the iframe source.
- The WordPress HTML5 DOM fragment smoke now covers a safe iframe source and an
  unsafe iframe source while confirming iframe wrappers are not emitted.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract and the mapped
HTML-reader review handoff behavior: recovered HTML fragments should not keep
active nested browsing contexts, but safe source/navigation metadata should
remain inspectable for WordPress migration review.

This is bounded native PHP sanitizer support. It is not nested frame execution,
browser sanitizer parity, complete HTML5 tree-builder parity, iframe sandbox or
allow-permission modeling, CSS/media loading, XHTML-to-AST conversion, or full
upstream Pandoc runner parity.

## Evidence

- Current rework notes under the handoff-candidate directory were stale
  20260524/25 entries only; no current 20260606 lane rework note was present.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 675 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  failed with `1 test files, 676 assertions, 1 failures`; the new iframe-source
  test produced only `<p>after</p>` before implementation.
- Focused green after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed
  with `1 test files, 696 assertions, 0 failures`.
- DOM family green after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 969 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1779` -> `1780`.
- `phpPass`: `1366` -> `1367`.
- `xmlHtmlDomCoreCases`: `5` -> `6`.
- `mappedXmlHtmlDomCoreCases`: `5` -> `6`.
- `xmlHtmlDomCoreAssertions`: `70` -> `91`.
- Focused `Html5DomFragmentTest.php`: `675` -> `696` assertions.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET`
parser paths, trusted base URL resolution, the existing WordPress HTML5 DOM
fragment handoff example, and the focused lane test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Non-Overlap

This slice does not repeat DTD/entity rejection, processing-instruction
filtering, XML declaration preflight, comment-boundary serialization, raw
text/RCDATA/plaintext handling, SVG/MathML foreign-content casing,
foreign-content CDATA normalization, URL/srcset filtering, data-image handling,
base URL resolution, inactive fallback base isolation, SVG resource filtering,
SVG presentation resource URL filtering, form/embed/object/applet/noscript/
template fallback unwrapping, `iframe srcdoc` content handoff, table
foster-parenting, XML namespace serialization, obsolete media URL attributes,
picture-source pruning, explicit input/select label preservation, meta refresh
filtering, passive named/property meta handoff, passive link relation handoff,
or navigation side-effect stripping.

It owns only bounded safe plain `iframe src` reviewer-link handoff for blocked
iframe elements with no visible fallback content.

## Follow-Up

Keep iframe sandbox/allow/referrer-policy provenance, richer sanitizer policy,
full HTML5 tree-builder parity, CSS/media loading, XHTML-to-AST conversion, and
full upstream Haskell runner dependency closure as separate bounded slices.

# Pandoc XML/HTML5 DOM Core Current Base - Explicit Input Labels

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T222459Z`

Base accepted HEAD: `0c2931600027fb66150c1c6eccfa685b10a8e9b1`

## Implementation

- `Html5DomFragment` now preserves explicit reviewer-visible labels from
  stripped HTML input controls:
  - `type=submit`, `type=reset`, and `type=button` use the explicit `value`.
  - `type=image` uses the explicit `alt` text.
- The sanitizer still removes all `<input>` markup, active input attributes,
  unsafe image-input URLs, hidden values, and data-entry values such as text,
  checkbox, hidden, and file controls.
- The WordPress HTML5 DOM fragment smoke now covers the input-label handoff and
  proves hidden/data-entry values do not reach raw HTML blocks.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract:
  bounded HTML fragments should preserve reviewer-visible fallback text while
  stripping active browser controls before WordPress raw HTML handoff.
- This is a bounded native sanitizer behavior. It is not form submission
  semantics, browser layout parity, full HTML5 tree-builder parity, CSS/media
  loading, or XHTML-to-AST conversion.
- No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
  external XML/HTML tool, online sanitizer, or online service was executed.

## Evidence

- Baseline focused DOM-fragment check before adding the new case:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 443 assertions, 0 failures`
- Red-first after adding the case, before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 444 assertions, 1 failures`
  - Failure: expected explicit input labels, actual output was
    `<p></p><p></p><p>Agree</p><p>after</p>`.
- Focused verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 462 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - Result: `html5 dom fragment handoff self-test ok`

## Mapping Delta

- `benchmarkDenominator.mapped`: `1544` -> `1545`.
- `phpPass`: `1092` -> `1093`.
- `xmlHtmlDomCoreCases`: `4` -> `5`.
- `mappedXmlHtmlDomCoreCases`: `4` -> `5`.
- `xmlHtmlDomCoreAssertions`: `35` -> `54`.
- Focused `Html5DomFragmentTest.php`: `443` -> `462` assertions.

## Non-Overlap

This slice does not repeat accepted XML/HTML5 DOM work for DTD/entity rejection,
processing-instruction filtering, XML declaration preflight, comment-boundary
serialization, raw text/RCDATA/plaintext handling, SVG/MathML foreign-content
casing, integration-point casing, CDATA normalization, URL/srcset filtering,
base URL resolution, inactive fallback base isolation, SVG resource filtering,
SVG presentation resource URL filtering, form/embed/noscript/template fallback
unwrapping, table foster-parenting, XML namespace serialization, obsolete media
URL attributes, or picture-source pruning. It owns only explicit input label
text preservation while keeping stripped input controls out of the review HTML.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET`
parser path, and existing WordPress HTML5 DOM fragment handoff example.

Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc checkout
at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal project/package files
and test executable dependency closure.

## Follow-Up

Keep full HTML5 tree-builder parity, broader sanitizer policy, form submission
semantics, CSS cascade/media handling, XHTML-to-AST conversion, and full
upstream Haskell runner parity as separate bounded slices.

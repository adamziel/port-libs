# Pandoc XML/HTML5 DOM Core Current Base - Inactive Fallback Bases

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T212525Z`

Base accepted HEAD: `8a25063b80cbebb3b8527755eb93e23f3005e4b0`

## Implementation

- `Html5DomFragment` now ignores `<base href>` elements when they appear under
  inactive or fallback ancestors before selecting the fragment base URL.
- Covered ancestors include `template`, `noscript`, iframe/object/applet
  fallback containers, raw-text fallback containers, form-control fallback
  containers, and SVG/MathML foreign subtrees.
- The sanitizer still unwraps visible fallback reviewer content and still
  strips all `<base>` elements from emitted raw HTML blocks.
- The WordPress HTML5 DOM fragment handoff example now proves a template-local
  inactive base cannot hijack URL resolution for review links.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract: bounded
  HTML fragments may carry a trusted import base URL for resolving relative
  reviewer links, but inert fallback content should not redefine the whole
  fragment's base before WordPress raw HTML handoff.
- This is a bounded native sanitizer behavior. It is not full HTML5 tree-builder
  parity, browser sanitizer parity, CSS cascade/media loading, or XHTML-to-AST
  conversion.
- No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
  external XML/HTML tool, online sanitizer, or online service was executed.

## Evidence

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 425 assertions, 0 failures`
- Targeted pre-fix probe:
  - `Html5DomFragment::fromHtml()` selected
    `https://inactive.example/assets/` from a `<base>` inside `template`, and
    resolved both fallback and document review links under `inactive.example`.
- Focused verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 443 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - Result: `html5 dom fragment handoff self-test ok`

## Mapping Delta

- `benchmarkDenominator.mapped`: `1529` -> `1530`.
- `phpPass`: `1077` -> `1078`.
- `xmlHtmlDomCoreCases`: `4` -> `5`.
- `mappedXmlHtmlDomCoreCases`: `4` -> `5`.
- `xmlHtmlDomCoreAssertions`: `35` -> `53`.
- Focused `Html5DomFragmentTest.php`: `425` -> `443` assertions.

## Non-Overlap

This slice does not repeat accepted XML/HTML5 DOM work for DTD/entity rejection,
processing-instruction filtering, XML declaration preflight, comment-boundary
serialization, raw text/RCDATA/plaintext handling, SVG/MathML foreign-content
casing, integration-point casing, CDATA normalization, URL/srcset filtering,
base URL resolution for active fragments, SVG resource filtering, SVG
presentation resource URL filtering, form/embed/noscript/template fallback
unwrapping, table foster-parenting, XML namespace serialization, obsolete media
URL attributes, or picture-source pruning. It owns only inactive fallback base
isolation before trusted fragment URL resolution.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, and existing WordPress
HTML5 DOM fragment handoff example support rows.

Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc checkout
at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal project/package files
and test executable dependency closure.

## Follow-Up

Keep full HTML5 tree-builder parity, broader sanitizer policy, CSS cascade and
media resource handling, XHTML-to-AST conversion, and full upstream Haskell
runner parity as separate bounded slices.

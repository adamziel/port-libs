# Pandoc XML/HTML5 DOM Core Current Base - Control Srcset Candidates

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T235654Z`

Base accepted HEAD: `8274a083130b4e14806ca5a49cc61e2394be5e70`

## Implementation

- `Html5DomFragment` now normalizes ASCII-control-separated `srcset` candidate
  URLs before descriptor validation.
- Safe legacy candidates such as `h&#10;ttps://... 2x` and
  `./hero&#13;-wide.webp 0640w` are preserved as canonical responsive-image
  candidates.
- Unsafe candidates such as `java&#10;script:alert(1) 3x` still produce
  `unsafe-url` diagnostics and are stripped before WordPress raw HTML handoff.
- The WordPress HTML5 DOM fragment smoke now covers a tab-separated CDN
  `srcset` candidate with an entity-escaped query string.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract:
  bounded HTML fragments handed to WordPress review blocks should preserve safe
  responsive image candidates while applying the same control-character URL
  normalization already used by regular URL attributes.
- This is bounded native PHP DOM/sanitizer support. It is not full browser
  `srcset` parser parity, CSS/media loading, image selection, XHTML-to-AST
  conversion, browser sanitizer parity, or upstream Pandoc runner parity.
- No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
  external XML/HTML tool, online sanitizer, online service, or live provider
  test was executed.

## Evidence

- Baseline focused DOM-fragment check before adding the new case:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 462 assertions, 0 failures`
- Red-first after adding the case, before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 463 assertions, 1 failures`
  - Failure: the safe `<source>` branch was pruned and the safe tab-separated
    CDN image candidate was dropped.
- Focused verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 476 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - Result: `html5 dom fragment handoff self-test ok`

## Mapping Delta

- `benchmarkDenominator.mapped`: `1565` -> `1566`.
- `phpPass`: `1113` -> `1114`.
- `xmlHtmlDomCoreCases`: `4` -> `5`.
- `mappedXmlHtmlDomCoreCases`: `4` -> `5`.
- `xmlHtmlDomCoreAssertions`: `35` -> `49`.
- Focused `Html5DomFragmentTest.php`: `462` -> `476` assertions.

## Non-Overlap

This slice does not repeat accepted XML/HTML5 DOM work for DTD/entity
rejection, processing-instruction filtering, XML declaration preflight,
comment-boundary serialization, raw text/RCDATA/plaintext handling,
SVG/MathML foreign-content casing, integration-point casing, CDATA
normalization, general URL/srcset unsafe-candidate filtering, base URL
resolution, inactive fallback base isolation, SVG resource filtering, SVG
presentation resource URL filtering, form/embed/noscript/template fallback
unwrapping, table foster-parenting, XML namespace serialization, obsolete media
URL attributes, picture-source pruning, or explicit input label preservation.
It owns only control-character normalization for individual `srcset` candidate
URLs before descriptor validation.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET`
parser paths, and the existing WordPress HTML5 DOM fragment handoff example.

Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and test executable dependency closure.

## Follow-Up

Keep full HTML5 tree-builder parity, full browser `srcset` grammar parity,
CSS/media loading, XHTML-to-AST conversion, and full upstream Haskell runner
parity as separate bounded slices.

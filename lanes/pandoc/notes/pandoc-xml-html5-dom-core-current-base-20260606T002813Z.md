# Pandoc XML/HTML5 DOM Core Current Base - Data Srcset Candidates

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T002813Z`

Base accepted HEAD: `da9f176c10d140dd00b15e787e396ea2c6df15db`

## Implementation

- `Html5DomFragment` now splits `srcset` candidates without treating the comma
  inside `data:` URL payloads as a candidate delimiter.
- Safe base64 raster image candidates (`png`, `gif`, `jpg`, `jpeg`, `webp`)
  are preserved for responsive media review.
- Non-image `data:` candidates and SVG data-image candidates are still stripped
  with `unsafe-url` diagnostics before WordPress raw HTML handoff.
- The WordPress HTML5 DOM fragment smoke now proves safe inline data-image
  `srcset` handoff and removal of `data:text/html` candidates.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract:
  bounded HTML fragments handed to WordPress review blocks should preserve
  inert inline raster media candidates while preventing active or ambiguous
  data URL payloads from reaching raw HTML blocks.
- This is bounded native PHP DOM/sanitizer support. It is not full browser
  `srcset` grammar parity, image fetching/selection, CSS media evaluation,
  browser sanitizer parity, XHTML-to-AST conversion, or upstream Pandoc runner
  parity.
- No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
  external XML/HTML tool, online sanitizer, online service, or live provider
  test was executed.

## Evidence

- Baseline focused DOM-fragment check before adding the new case:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 476 assertions, 0 failures`
- Red-first after adding the case, before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 477 assertions, 1 failures`
  - Failure: data URL payload chunks such as `iVBORw0KGgo= 1x` were treated as
    relative responsive-image candidates.
- Focused verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 490 assertions, 0 failures`
- XML/HTML DOM family verification:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 744 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - Result: `html5 dom fragment handoff self-test ok`

## Mapping Delta

- `benchmarkDenominator.mapped`: `1573` -> `1574`.
- `phpPass`: `1121` -> `1122`.
- `xmlHtmlDomCoreCases`: `4` -> `5`.
- `mappedXmlHtmlDomCoreCases`: `4` -> `5`.
- `xmlHtmlDomCoreAssertions`: `35` -> `49`.
- Focused `Html5DomFragmentTest.php`: `476` -> `490` assertions.

## Non-Overlap

This slice does not repeat accepted XML/HTML5 DOM work for DTD/entity
rejection, processing-instruction filtering, XML declaration preflight,
comment-boundary serialization, raw text/RCDATA/plaintext handling,
SVG/MathML foreign-content casing, integration-point casing, CDATA
normalization, control-separated `srcset` URL normalization, general unsafe URL
filtering, base URL resolution, inactive fallback base isolation, SVG resource
filtering, SVG presentation resource URL filtering, form/embed/noscript/
template fallback unwrapping, table foster-parenting, XML namespace
serialization, obsolete media URL attributes, picture-source pruning, or
explicit input label preservation. It owns only data URL payload-aware `srcset`
candidate splitting plus bounded safe-raster data-image admission.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET`
parser paths, and the existing WordPress HTML5 DOM fragment handoff example.

Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and test executable dependency closure.

## Follow-Up

Keep full browser `srcset` grammar parity, broader media-type data URL policy,
HTML tree-builder parity, CSS/media loading, XHTML-to-AST conversion, and full
upstream Haskell runner parity as separate bounded slices.

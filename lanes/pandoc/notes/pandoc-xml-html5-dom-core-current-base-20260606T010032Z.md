# Pandoc XML/HTML5 DOM Core Current Base - Data Image Src

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T010032Z`

Base accepted HEAD: `b78fe5dad8286235b93b1e8139739180f39a0e32`

## Implementation

- `Html5DomFragment` now preserves bounded safe raster `data:image/*;base64`
  values on ordinary HTML `img src` attributes.
- The allowlist is intentionally narrow: PNG, GIF, JPEG/JPG, and WebP payloads
  must pass strict base64 validation.
- Active or ambiguous data payloads such as `data:text/html` and
  `data:image/svg+xml` are still stripped from `img src`.
- Navigational links such as `a href="data:image/png;base64,..."` still use the
  existing URL policy and remain stripped before WordPress raw HTML handoff.
- The WordPress HTML5 DOM fragment smoke now proves safe inline raster image
  `src` handoff and removal of an active `data:text/html` image source.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract:
  recovered HTML fragments handed to WordPress review blocks should preserve
  reviewer-visible inline raster images while preventing active, scriptable, or
  navigation-bearing data URLs from reaching raw HTML blocks.
- This is bounded native PHP sanitizer/serializer behavior. It is not full
  browser sanitizer parity, generic data-URL support, image decoding, media
  selection, CSS resource loading, XHTML-to-AST conversion, or upstream Pandoc
  runner parity.
- No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
  external XML/HTML tool, online sanitizer, online service, or live provider
  test was executed.

## Evidence

- Red-first after adding the focused case, before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 491 assertions, 1 failures`
  - Failure: safe `data:image/png;base64,...` on `img src` was stripped.
- Focused verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 505 assertions, 0 failures`
- XML/HTML DOM family verification:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 759 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - Result: `html5 dom fragment handoff self-test ok`

## Mapping Delta

- `benchmarkDenominator.mapped`: `1580` -> `1581`.
- `phpPass`: `1128` -> `1129`.
- `xmlHtmlDomCoreCases`: `4` -> `5`.
- `mappedXmlHtmlDomCoreCases`: `4` -> `5`.
- `xmlHtmlDomCoreAssertions`: `35` -> `50`.
- Focused `Html5DomFragmentTest.php`: `490` -> `505` assertions.

## Non-Overlap

This slice does not repeat accepted XML/HTML5 DOM work for DTD/entity
rejection, processing-instruction filtering, XML declaration preflight,
complete HTML document unsafe-declaration preflight, HTML fragment declaration
preflight, comment-boundary serialization, raw text/RCDATA/plaintext handling,
SVG/MathML foreign-content casing, integration-point casing, CDATA
normalization, general URL filtering, `srcset` candidate filtering,
control-separated `srcset` URL normalization, data-image `srcset` splitting,
base URL resolution, inactive fallback base isolation, SVG resource filtering,
SVG presentation resource URL filtering, form/embed/noscript/template fallback
unwrapping, table foster-parenting, XML namespace serialization, obsolete media
URL attributes, picture-source pruning, or explicit input label preservation.
It owns only tag-aware safe raster `data:image` preservation for ordinary
`img src` attributes while keeping non-raster and navigational data URLs
blocked.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET`
parser paths, and the existing WordPress HTML5 DOM fragment handoff example.

Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and test executable dependency closure.

## Follow-Up

Keep broader media data-URL policy, full browser sanitizer and tree-builder
parity, CSS/media resource loading, XHTML-to-AST conversion, and full upstream
Haskell runner parity as separate bounded slices.

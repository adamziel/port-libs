# Pandoc XML/HTML5 DOM Core Current Base - SVG Data Images

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T051300Z`

Base accepted HEAD: `27a0520cbee0c34db64918d6587918843c9b97db`

## Implementation

- `Html5DomFragment` now preserves bounded safe raster `data:image/png`,
  `data:image/gif`, `data:image/jpeg`, and `data:image/webp` payloads on SVG
  `image` and `feImage` `href` / `xlink:href` resource attributes.
- The same sanitizer path still strips script-capable `data:image/svg+xml`,
  active `data:text/html`, and navigational `data:` URLs from raw HTML review
  fragments before WordPress block handoff.
- The WordPress HTML5 DOM fragment smoke now proves a safe SVG PNG data image
  survives while an SVG data payload is stripped from the same raw HTML packet.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract for bounded raw
HTML fragment handoff plus the existing native sanitizer policy that already
allows safe raster data-image payloads for HTML `img src` and `srcset`
candidates while rejecting active data payloads.

This is bounded native PHP support-library behavior for Pandoc-reader review
handoff. It is not full HTML5 tree-builder parity, browser sanitizer parity,
CSS cascade/media loading, full SVG resource policy, XHTML-to-AST conversion,
or upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline focused fragment check before this slice:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 552 assertions, 0 failures`.
- Red-first after adding the focused case:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 553 assertions, 1 failures`.
  - Failure: safe raster SVG `image` / `feImage` data-image resource
    attributes were stripped from sanitized output.
- Focused verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 573 assertions, 0 failures`.
- DOM family verification:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 846 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - Result: `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1648` -> `1649`.
- `phpPass`: `1202` -> `1203`.
- `xmlHtmlDomCoreCases`: `5` -> `6`.
- `mappedXmlHtmlDomCoreCases`: `5` -> `6`.
- `xmlHtmlDomCoreAssertions`: `70` -> `91`.
- Focused `Html5DomFragmentTest.php`: `552` -> `573` assertions.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml parser paths,
and existing lane-local manifest/status machinery.

Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and Haskell Tasty executable dependency closure.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted XML/HTML5 DOM work for DTD/entity
rejection, processing-instruction filtering, XML declaration preflight,
complete HTML document doctype preflight, comment-boundary serialization, raw
text/RCDATA/plaintext handling, SVG/MathML foreign-content casing,
`foreignObject` integration-point casing, MathML token text integration-point
casing, `annotation-xml` HTML integration, foreign-content CDATA normalization,
URL/srcset filtering, HTML data-image `img` / `srcset` handling, base URL
resolution, inactive fallback base isolation, SVG local-resource URL
preservation, SVG presentation resource URL filtering, form/embed/noscript/
template fallback unwrapping, table foster-parenting, XML namespace
serialization, obsolete media URL attributes, picture-source pruning, explicit
input label preservation, meta refresh link handoff, or SVG CSS resource URL
escape handling.

It owns only bounded safe raster data-image preservation for SVG `image` and
`feImage` resource attributes in the raw HTML fragment sanitizer.

## Follow-Up

Keep full HTML5 tree-builder parity, richer sanitizer policy, CSS
cascade/media resource handling, broader SVG resource policy, XHTML-to-AST
conversion, and full upstream Haskell runner dependency closure as separate
bounded slices.

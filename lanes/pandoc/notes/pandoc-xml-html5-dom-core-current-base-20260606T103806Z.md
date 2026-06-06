# Pandoc XML/HTML5 DOM Core Current Base - Named Meta Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T103806Z`

Base accepted HEAD: `4d7229bc3c8e868b129629e7dc6a1682afd2bc3c`

## Implementation

- `Html5DomFragment` now converts safe passive HTML `<meta name=... content=...>` records for `description`, `author`, `keywords`, and `generator` into inert reviewer-visible `<span data-pandoc-meta-name=...>` nodes.
- The original `<meta>` elements remain stripped from sanitized raw HTML, and unsupported property metadata, empty metadata, and viewport metadata remain omitted.
- Metadata content is whitespace-normalized and escaped during serialization, so tag-looking source metadata remains review text instead of parsed markup.
- The WordPress HTML5 DOM fragment smoke now covers passive named metadata alongside base URL, link relation, meta refresh, srcdoc, SVG, srcset, picture, and form-control handoff behavior.

## Source Truth

Source truth is the lane-local Pandoc XML/HTML5 DOM support contract and the mapped upstream HTML-reader metadata behavior: recovered HTML fragments handed to WordPress review blocks should retain safe source metadata where it helps reviewer audit, but should not preserve active or invisible DOM metadata as executable HTML. This is bounded native PHP sanitizer behavior, not full HTML5 tree-builder parity, browser sanitizer parity, CSS/media loading, OpenGraph/Twitter card modeling, XHTML-to-AST conversion, or upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present for this lane before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 630 assertions, 0 failures`.
- Focused green after implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 653 assertions, 0 failures`.
- DOM family green after implementation: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 926 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed with `html5 dom fragment handoff self-test ok`.
- Syntax: `php -l lanes/pandoc/src/Html5DomFragment.php`, `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`, and `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php` all reported no syntax errors.
- JSON: `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` decoded with `JSON_THROW_ON_ERROR`.
- Whitespace: `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1712` -> `1713`.
- `phpPass`: `1298` -> `1299`.
- `xmlHtmlDomCoreCases`: `5` -> `6`.
- `mappedXmlHtmlDomCoreCases`: `5` -> `6`.
- `xmlHtmlDomCoreAssertions`: `70` -> `93`.
- Focused `Html5DomFragmentTest.php`: `630` -> `653` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml parser paths, trusted base URL resolution, and the focused PHP lane test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer, external XML/HTML tool, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat DTD/entity rejection, PI/XML declarations, comment boundaries, raw text/RCDATA/plaintext parsing, SVG/MathML foreign casing, CDATA preservation, URL/srcset filtering, raster SVG data images, base URL resolution, inactive fallback base isolation, SVG resource filtering, form/embed/noscript/template unwrap, table foster-parenting, XML namespace serialization, obsolete media URL attributes, picture-source pruning, explicit input/select label preservation, meta refresh filtering, passive link relation handoff, navigation side-effect stripping, or SVG CSS resource escape handling. It owns only bounded passive `meta name` description/author/keywords/generator metadata handoff for sanitized reviewer fragments.

## Follow-Up

Keep OpenGraph/Twitter/meta property handoff, richer sanitizer policy, full HTML5 tree-builder parity, CSS cascade/media resource loading, XHTML-to-AST conversion, and full upstream-runner parity as separate bounded slices.

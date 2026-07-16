# Pandoc XML/HTML5 DOM Core Current Base - Navigation Side Effects

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T093328Z`

Base accepted HEAD: `3f8ff858e83ffe66ab1e60b8b757f837d5955701`

## Implementation

- `Html5DomFragment` now strips HTML `target` and `download` attributes from sanitized fragments.
- HTML `rel` token lists are lowercased, deduplicated, and scrubbed of `opener` tokens while safe tokens such as `noopener`, `noreferrer`, `nofollow`, `author`, and `tag` remain visible.
- Trusted base URL resolution still applies to `href` and `area` targets before WordPress raw HTML handoff.
- The WordPress HTML5 DOM fragment smoke now covers a legacy source link with `target`, `download`, and `rel=opener` side effects.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract: recovered HTML fragments handed to WordPress review blocks can preserve safe source navigation URLs and reviewer metadata, but should not preserve active browsing-context or download side effects. This is bounded native PHP sanitizer behavior, not full browser sanitizer parity, full HTML5 tree-builder parity, CSS/media loading, XHTML-to-AST conversion, or upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present for this lane before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 611 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` failed with `1 test files, 612 assertions, 1 failure` because `target`, `download`, and `opener` side effects remained serialized.
- Focused green after implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 630 assertions, 0 failures`.
- DOM family green after implementation: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 903 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed with `html5 dom fragment handoff self-test ok`.
- Syntax: `php -l lanes/pandoc/src/Html5DomFragment.php`, `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`, and `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php` all reported no syntax errors.
- JSON: `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` decoded with `JSON_THROW_ON_ERROR`.
- Whitespace: `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1702` -> `1703`.
- `phpPass`: `1258` -> `1259`.
- `xmlHtmlDomCoreCases`: `5` -> `6`.
- `mappedXmlHtmlDomCoreCases`: `5` -> `6`.
- `xmlHtmlDomCoreAssertions`: `70` -> `89`.
- Focused `Html5DomFragmentTest.php`: `611` -> `630` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml parser paths, trusted base URL resolution, and the focused PHP lane test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer, external XML/HTML tool, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat DTD/entity rejection, PI/XML declarations, comment boundaries, raw text/RCDATA/plaintext parsing, SVG/MathML foreign casing, CDATA preservation, URL/srcset filtering, raster SVG data images, base URL resolution, inactive fallback base isolation, SVG resource filtering, form/embed/noscript/template unwrap, table foster-parenting, XML namespace serialization, obsolete media URL attributes, picture-source pruning, explicit input/select label preservation, meta refresh filtering, passive link relation handoff, or SVG CSS resource escape handling. It owns only `target`/`download`/`opener` navigation side-effect stripping for sanitized reviewer links.

## Follow-Up

Keep full HTML5 tree-builder parity, richer sanitizer policy, CSS cascade/media resource loading, link relation families beyond bounded reviewer metadata, XHTML-to-AST conversion, and full upstream-runner parity as separate bounded slices.

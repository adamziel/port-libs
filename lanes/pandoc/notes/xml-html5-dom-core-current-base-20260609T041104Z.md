# XML/HTML5 DOM URL Diagnostic Source Lines

Slice: `pandoc-xml-html5-dom-core-current-base-20260609T041104Z`
Session: `port-dev-pandoc-xml-html5-dom-20260609T041104Z`
Base: `62e9eaec3f082b012a61e602d9a7179fe5930ba6`

## Scope

This slice carries source line metadata onto bounded HTML URL repair/review diagnostics emitted by `Html5DomFragment` before WordPress raw HTML handoff.

Line metadata now follows these existing sanitizer paths:

- passive `<link>` reviewer URL repairs;
- `<meta>` URL metadata and refresh URL rejection/normalization;
- blocked `<iframe src>` reviewer-link conversion;
- `<blockquote cite>` / `<q cite>` review metadata;
- `<ins cite>` / `<del cite>` revision metadata plus revision datetime diagnostics;
- image-map `<area href>` reviewer-link conversion.

Serialization and URL safety semantics are unchanged. The patch only preserves the source line on diagnostics and generated reviewer link nodes for the URL paths above.

## Non-Overlap

This does not repeat accepted image resource-policy metadata conversion, image resource-policy source-line diagnostics, generic sanitizer source-line coverage, `srcset` candidate preflight, data-image handling, responsive image metadata, portal source metadata, SVG resource filtering, table repair, foreign-content casing, or ODF/OpenDocument metadata slices. The new behavior is only source-position handoff for selected URL repair/review diagnostics.

## Evidence

- Rework notes: none found under `.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Red-first probe before implementation: a focused `php -r` probe over multiline `link`, `meta`, `iframe`, quote/revision cite, and image-map area HTML showed `normalized-url`, `unsafe-url`, `quote-cite-review`, and `revision-metadata-review` diagnostics without `line` fields.
- Focused command after implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1947 assertions, 0 failures`.
- Adjacent XML/HTML DOM family command: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 2315 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed with `html5 dom fragment handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/Html5DomFragment.php`, `lanes/pandoc/tests/Html5DomFragmentTest.php`, and `lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Root harness not run: isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2280` to `2281`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2682` to `2683`.
- `xmlHtmlDomCoreCases`: `8` to `9`.
- `xmlHtmlDomCoreAssertions`: `124` to `144`.
- Focused `Html5DomFragmentTest.php`: accepted baseline `1927` assertions to final `1947` assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `Html5DomFragment` parsing, its existing source-line diagnostic helper, `WordPressBlockWriter` raw HTML blocks, and the existing WordPress HTML5 DOM fragment example. Full upstream Pandoc HTML reader runner parity remains a separate upstream-runner dependency task requiring hydrated pinned upstream sources and Haskell test executables.

## Exclusions

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was run.

## Next Task

A useful follow-up is a non-overlapping source-position slice for semantic/microdata/RDFa metadata diagnostics, or richer HTML reader metadata AST projection. Keep full HTML5 tree-builder parity, browser sanitizer parity, CSS/media resource loading, and external converter execution out of this bounded support-library lane.

# XML/HTML5 DOM Image Resource-Policy Source Lines

Slice: `pandoc-xml-html5-dom-core-current-base-20260609T035525Z`
Session: `port-dev-pandoc-xml-html5-dom-20260609T035525Z`
Base: `4cca1c57da8720c140326c22572dbfb45205f318`

## Scope

This slice carries source line metadata onto image resource-policy diagnostics emitted by `Html5DomFragment` before WordPress raw HTML handoff. Valid review diagnostics for `loading`, `decoding`, `fetchpriority`, and `crossorigin` now retain the originating `<img>` line, and invalid image policy values now retain the same line on their `unsafe-attribute` diagnostics.

## Non-Overlap

This does not repeat the accepted image resource-policy metadata conversion, URL repair, `srcset` preflight, data-image handoff, responsive image handling, generic source-line diagnostics, table orphan repair, foreign-content casing, or ODF/OpenDocument metadata slices. The new behavior is only source-position handoff for image policy review and invalid-policy diagnostics.

## Evidence

- Rework notes: none found under `.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Baseline focused command before implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1917 assertions, 0 failures`.
- Red-first probe: image resource-policy diagnostics for valid and invalid `<img>` attributes were present but had no `line` field.
- Focused command after implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1927 assertions, 0 failures`.
- Adjacent XML/HTML DOM family command: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 2295 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed with `html5 dom fragment handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/Html5DomFragment.php`, `lanes/pandoc/tests/Html5DomFragmentTest.php`, and `lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Lane diff whitespace check: `git diff --check -- lanes/pandoc` passed.
- Root harness not run: isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2260` to `2261`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2665` to `2666`.
- `xmlHtmlDomCoreCases`: `8` to `9`.
- `xmlHtmlDomCoreAssertions`: `124` to `134`.

## Dependency Closure

No new support component is needed. This reuses native PHP `Html5DomFragment` parsing, its existing source-line diagnostic helper, `WordPressBlockWriter` raw HTML blocks, and the existing WordPress HTML5 DOM fragment example. Full upstream Pandoc HTML reader runner parity remains a separate upstream-runner dependency task requiring hydrated pinned upstream sources and Haskell test executables.

## Exclusions

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was run.

## Next Task

A useful follow-up is a non-overlapping source-position slice for malformed active URL repair diagnostics or other resource metadata diagnostics that still lose DOM line information before WordPress review handoff.

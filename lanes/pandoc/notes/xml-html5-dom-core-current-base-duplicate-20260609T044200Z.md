# XML/HTML5 DOM Duplicate Base Handoff

Slice: `pandoc-xml-html5-dom-core-current-base-duplicate-20260609T044200Z`
Session: `port-dev-pandoc-xml-html5-dom-20260609T044200Z`
Base: `7c6ac18f8d3be98468babe4130239bcc5539af33`

## Scope

This slice maps bounded HTML base-element duplicate behavior needed by Pandoc-style HTML reader handoff:

- the first active `<base href>` remains the only URL base used for reviewer links, images, and raw HTML blocks;
- later active `<base href>` entries are ignored and surfaced as `duplicate-base-ignored` diagnostics with source line metadata;
- the first active `<base target>` remains the only browsing-context metadata converted to an inert reviewer span;
- later active `<base target>` entries are ignored and surfaced as `duplicate-base-ignored` diagnostics with source line metadata;
- base elements inside inactive fallback contexts remain ignored by base resolution.

The behavior is native PHP only. It does not implement a full browser tree builder and does not execute Pandoc, Haskell tests, browser renderers, online sanitizers, or external converters.

## Non-Overlap

This does not repeat accepted control-separated base URL normalization, unsafe base rejection, inactive template/noscript/canvas/srcdoc base handling, base-target metadata preservation, URL repair source-line diagnostics, image resource-policy metadata, foreign-content casing, or ODF/OpenDocument metadata slices. The new behavior is only duplicate active base href/target audit diagnostics while preserving first-base resolution semantics.

## Evidence

- Rework notes: none found under `.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Baseline focused command before implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1952 assertions, 0 failures`.
- Red-first probe before implementation: duplicate active `<base href>` / `<base target>` inputs resolved links from the first base and stripped both base elements, but diagnostics were only `base-target-review`, `blocked-tag`, `blocked-tag`; there was no duplicate-base audit signal for the ignored later base metadata.
- Focused command after implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1981 assertions, 0 failures`.
- Adjacent XML/HTML DOM family command: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `4 test files, 2406 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test` passed with `wordpress-html5-dom-handoff self-test passed`.
- PHP lint passed for `lanes/pandoc/src/Html5DomFragment.php`, `lanes/pandoc/tests/Html5DomFragmentTest.php`, and `lanes/pandoc/examples/wordpress-html5-dom-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Root harness not run: isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2317` to `2318`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2717` to `2718`.
- `mappedXmlHtmlDomCoreCases`: `8` to `9`.
- `xmlHtmlDomCoreAssertions`: `124` to `153`.
- Focused `Html5DomFragmentTest.php`: baseline `1952` assertions to final `1981` assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `Html5DomFragment`, existing URL/base resolution helpers, `WordPressBlockWriter` raw HTML blocks, and the existing WordPress HTML5 DOM handoff example. Full upstream Pandoc HTML reader runner parity remains a separate upstream-runner dependency task requiring hydrated pinned upstream sources and Haskell test executables.

## Exclusions

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, tar, gzip, lz4, TeX/PDF engine, Typst, browser renderer, external converter, external validator, online service, live provider test, or live-service provider test was run.

## Next Task

A useful follow-up is non-overlapping XML/HTML5 DOM support such as richer document metadata AST projection, additional source-position diagnostics, or parser-level HTML reader behavior. Keep browser sanitizer parity, live resource loading, and full Pandoc runner parity out of this bounded support-library lane.

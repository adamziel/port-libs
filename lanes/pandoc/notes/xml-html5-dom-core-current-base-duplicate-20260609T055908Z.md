# XML/HTML5 DOM Document Metadata Source Lines

Slice: `pandoc-xml-html5-dom-core-current-base-duplicate-20260609T055908Z`
Session: `port-dev-pandoc-xml-html5-dom-20260609T055908Z`
Base: `7ed2f69b027c00a8c9af1b63d2dfcdebbab97ac6`

## Scope

This slice maps bounded source-line provenance for document metadata in the native XML/HTML5 DOM handoff:

- synthetic review nodes created from `<title>` and supported `<meta>` elements now carry the source line from the source DOM node;
- unsafe `<meta>` content diagnostics for Content-Security-Policy, referrer, crawler directives, theme-color, theme-color media, and color-scheme now carry source line metadata;
- the same line-aware diagnostics are preserved through `toRawHtmlAst()` for WordPress raw HTML block handoff;
- unsafe metadata remains diagnostic-only and does not become rendered WordPress content.

The behavior is native PHP only. It does not implement a browser tree builder and does not execute Pandoc, Haskell tests, browser renderers, online sanitizers, or external converters.

## Non-Overlap

This does not repeat accepted duplicate active `<base href>`/`target` diagnostics, semantic microdata/RDFa source-line diagnostics, URL repair source-line diagnostics, image resource-policy source-line diagnostics, generic sanitizer source-line coverage, foreign-content casing, or PDF engine page timing policy work. The new behavior is only document metadata review-node provenance plus unsafe meta policy/crawler/color diagnostic provenance.

## Evidence

- Rework notes: none found under `.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Baseline focused command before implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 2114 assertions, 0 failures`.
- Pre-change behavior: title/meta metadata review nodes had no `line` field, and unsafe meta policy/crawler/theme-color/color-scheme diagnostics were plain arrays without source lines.
- Focused command after implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 2141 assertions, 0 failures`.
- Adjacent XML/HTML DOM family command: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `4 test files, 2591 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test` passed with `wordpress-html5-dom-handoff self-test passed`.
- PHP lint passed for `lanes/pandoc/src/Html5DomFragment.php`, `lanes/pandoc/tests/Html5DomFragmentTest.php`, and `lanes/pandoc/examples/wordpress-html5-dom-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Root harness not run: isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2411` to `2412`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2800` to `2801`.
- `xmlHtmlDomCoreCases`: `8` to `9`.
- `mappedXmlHtmlDomCoreCases`: `8` to `9`.
- `xmlHtmlDomCoreAssertions`: `124` to `151`.
- Focused `Html5DomFragmentTest.php`: baseline `2114` assertions to final `2141` assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `Html5DomFragment`, existing DOM source-line helpers, `WordPressBlockWriter` raw HTML blocks, and the existing WordPress HTML5 DOM handoff example. Full upstream Pandoc HTML reader runner parity remains a separate upstream-runner dependency task requiring hydrated pinned upstream sources and Haskell test executables.

## Exclusions

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, tar, gzip, lz4, TeX/PDF engine, Typst, browser renderer, external converter, external validator, online service, live provider test, or live-service provider test was run.

## Next Task

A useful follow-up is non-overlapping XML/HTML5 DOM provenance support such as source-line metadata for remaining iframe/referrer/image-map helper diagnostics or richer document metadata AST projection. Keep browser sanitizer parity, live resource loading, and full Pandoc runner parity out of this bounded support-library lane.

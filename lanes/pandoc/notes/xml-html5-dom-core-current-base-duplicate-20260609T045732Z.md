# XML/HTML5 DOM Semantic Diagnostic Source Lines

Slice: `pandoc-xml-html5-dom-core-current-base-duplicate-20260609T045732Z`
Session: `port-dev-pandoc-xml-html5-dom-20260609T045732Z`
Base: `1f921dffe09079fa869adda4b3a933de84e1ac66`

## Scope

This slice carries source line metadata onto bounded HTML semantic metadata diagnostics emitted by `Html5DomFragment` before raw HTML AST and WordPress block handoff.

Line metadata now follows the semantic microdata/RDFa paths for:

- preserved `itemscope`, `itemtype`, `itemid`, `itemref`, `itemprop`, `property`, `about`, `resource`, `vocab`, `prefix`, `typeof`, `datatype`, and `inlist` metadata;
- normalized semantic URLs such as RDFa `about` values;
- unsafe semantic URLs such as active `itemtype`, `resource`, and `prefix` values;
- invalid semantic term tokens such as malformed `itemref`, `itemprop`, and `property` entries.

Serialization and sanitizer policy are unchanged. The patch only preserves source provenance on existing diagnostics.

## Non-Overlap

This does not repeat accepted duplicate active `<base href>`/`target` diagnostics, URL repair source-line diagnostics, image resource-policy source-line diagnostics, generic sanitizer source-line coverage, foreign-content casing, table repair, base URL normalization, or DOCX/OpenXML numbering-style work. The new behavior is only source-position handoff for semantic microdata/RDFa diagnostics.

## Evidence

- Rework notes: none found under `.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Baseline focused command before implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1981 assertions, 0 failures`.
- Pre-change behavior: semantic diagnostics were emitted by plain array helpers without `line` fields, unlike the accepted URL/image/base diagnostic paths.
- Focused command after implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1991 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test` passed with `wordpress-html5-dom-handoff self-test passed`.
- PHP lint passed for `lanes/pandoc/src/Html5DomFragment.php`, `lanes/pandoc/tests/Html5DomFragmentTest.php`, and `lanes/pandoc/examples/wordpress-html5-dom-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Root harness not run: isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2339` to `2340`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2735` to `2736`.
- `xmlHtmlDomCoreCases`: `8` to `9`.
- `mappedXmlHtmlDomCoreCases`: `8` to `9`.
- `xmlHtmlDomCoreAssertions`: `124` to `134`.
- Focused `Html5DomFragmentTest.php`: baseline `1981` assertions to final `1991` assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `Html5DomFragment`, existing source-line diagnostic helpers, `WordPressBlockWriter` raw HTML blocks, and the existing WordPress HTML5 DOM handoff example. Full upstream Pandoc HTML reader runner parity remains a separate upstream-runner dependency task requiring hydrated pinned upstream sources and Haskell test executables.

## Exclusions

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, tar, gzip, lz4, TeX/PDF engine, Typst, browser renderer, external converter, external validator, online service, live provider test, or live-service provider test was run.

## Next Task

A useful follow-up is non-overlapping XML/HTML5 DOM support such as richer document metadata AST projection, parser-level HTML reader behavior, or remaining source-position diagnostics outside semantic microdata/RDFa and accepted base/URL/image-policy paths. Keep browser sanitizer parity, live resource loading, and full Pandoc runner parity out of this bounded support-library lane.

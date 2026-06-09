# Legacy DOC/CFB Section Field Handoff

## Scope

- Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T051011Z`
- Base: `516b4c2368ab923eeb7c71f762618468a7a4d437`
- Behavior: preserve Word legacy DOC `SECTION` fields as safe cached-result spans with source instruction metadata, matching the existing inert field handoff used for `SECTIONPAGES` and other non-hyperlink fields.

## Source-Truth Behavior

Pandoc's legacy Word reader preserves field-derived document text rather than exposing control instructions as visible body text. This bounded PHP slice keeps the cached `SECTION` result visible and reviewer-auditable while storing the field instruction in metadata:

- `SECTION \* Arabic` becomes a `legacy-doc-field-section` span.
- The cached result remains visible in Markdown/WordPress output.
- The source instruction is stored in `data-legacy-doc-field-instruction`.
- Hidden instruction text is not emitted in visible WordPress block text.

## Evidence

- Rework-note scan: no current `port-pandoc-*.needs-lane-rework.md` note for this lane.
- Baseline before edits: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 2106 assertions, 0 failures`.
- Red-first test-only run: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 2099 assertions, 1 failures`; the new `SECTION` assertion failed because the field result was plain text instead of a span.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 2126 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-section-field-handoff.php --self-test` -> `legacy doc section-field handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2354` -> `2355`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2749` -> `2750`.
- `legacyDocCfbCoreCases`: `7` -> `8`.
- `mappedLegacyDocCfbCoreCases`: `7` -> `8`.
- `legacyDocCfbCoreAssertions`: `64` -> `84`.
- Focused assertion delta: `+20` in `LegacyDocReaderTest.php`.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `LegacyDocReader` field parser, the existing CFB test fixture helpers, `MarkdownWriter`, and `WordPressBlockWriter`. It does not shell out to Pandoc, Word, LibreOffice, zip/unzip, Haskell runners, TeX/PDF engines, browser renderers, online services, or live providers.

## Non-Overlap

This slice does not repeat previously accepted page/num-pages/date fields, source-location fields, generated TOC/INDEX/TOA handling, DOC property/info fields, include/action/prompt/numbering/list fields, CFB validation, FIB/CLX Unicode extraction, or picture-placeholder behavior. It only adds the missing `SECTION` mapping and WordPress smoke coverage for section pagination field provenance.

## Follow-Up

Next legacy DOC/CFB work should target a distinct bounded `.doc` gap such as additional Word field families, table/list handoff, piece-table edge diagnostics, or CFB allocation invariants.

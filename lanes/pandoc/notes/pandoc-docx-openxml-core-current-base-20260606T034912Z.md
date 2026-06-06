# DOCX/OpenXML Positional Tab Run Handoff

- Lane: `pandoc`
- Micro-slice: `pandoc-docx-openxml-core-current-base-20260606T034912Z`
- Base accepted HEAD: `36f4b82297cc95c9287bf2f8b349828229580692`
- Scope: bounded DOCX/OpenXML run-level support under `lanes/pandoc/**`

## Behavior

`DocxReader` now preserves WordprocessingML positional tab stops (`w:ptab`) instead of silently dropping them during run parsing. A positional tab becomes a reviewer `span` with:

- `docx-tab` and `docx-positional-tab` classes
- normalized alignment classes such as `docx-positional-tab-right`
- leader classes such as `docx-positional-tab-leader-dot`
- `data-docx-tab-type="positional"`
- `data-docx-tab-alignment`, `data-docx-tab-relative-to`, and `data-docx-tab-leader` when present

This keeps source DOCX layout checkpoints visible in AST, Markdown, and WordPress handoff output while leaving ordinary `w:tab` behavior unchanged.

## Source Truth And Non-Overlap

Source truth is the bounded WordprocessingML contract for `w:ptab` as a run child carrying positional-tab metadata (`alignment`, `relativeTo`, and optional `leader`). This slice does not overlap accepted DOCX `w:tab`, page/column/textWrapping `w:br`, `w:cr`, `w:lastRenderedPageBreak`, note-reference markers, field metadata, ruby, smart-tag, custom XML, content-control, tracked-formatting, or ODF `text:tab` slices.

The local upstream cache does not contain a hydrated Pandoc checkout, and no external Pandoc/office runner was used.

## Verification Evidence

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1666 assertions, 0 failures
```

Red-first after adding only the focused positional-tab test:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1668 assertions, 1 failures
Failure: expected 5 paragraph children, actual 1, because w:ptab was ignored.
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1687 assertions, 0 failures
```

Focused assertion delta: `+21`.

## Status Delta

- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: `benchmarkDenominator.mapped` `1631 -> 1632`; DOCX/OpenXML core cases `32 -> 33`; DOCX/OpenXML core assertions `357 -> 378`.
- `lanes/pandoc/lane-status.json`: `phpPass` `1181 -> 1182`; latest focused slice updated to this DOCX positional-tab handoff.
- `lanes/pandoc/examples/wordpress-docx-body-handoff.php`: self-test fixture now includes a positional leader tab and checks the rendered WordPress span.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP DOCX reader, AST span representation, Markdown writer, and WordPress block writer. The upstream runner dependency blocker remains unchanged: full Pandoc DOCX parity still requires a hydrated Pandoc checkout and Cabal/Tasty runner availability. No Pandoc, Word, LibreOffice, zip/unzip, external office tooling, Haskell runner, online service, or live provider test was executed.

## Follow-Up

Keep paragraph-level tab-stop definitions, exact layout measurement, and any deeper Pandoc DOCX runner parity as separate bounded slices.

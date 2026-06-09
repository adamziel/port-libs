# DOCX/OpenXML w:noProof Proofing Policy

Slice: `pandoc-docx-openxml-core-current-base-20260609T023901Z`

Base: `cff2757f3c2ce59e8912b5b48a787409562aacb3`

## Behavior

- Added native WordprocessingML `w:noProof` run-property handling in `DocxReader`.
- True `w:noProof` values now produce inert reviewer metadata spans with class `docx-no-proof` and `data-docx-no-proof="true"`.
- Character-style and paragraph-style run-property inheritance carry no-proof metadata into visible runs.
- Explicit false `w:noProof` values (`0` / `false`) suppress inherited no-proof metadata instead of emitting `data-docx-no-proof="false"`.
- Markdown and WordPress writers reuse the existing span metadata path; no new writer-specific support component was needed.

## Evidence

Baseline before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 3559 assertions, 0 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 3592 assertions, 0 failures
```

Additional local smoke:

```text
php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test
docx body handoff self-test ok
```

Assertion delta: `+33`

Mapped status delta: `phpPass 2162 -> 2163`, `benchmarkDenominator.mapped 2586 -> 2587`, `docxOpenXmlCoreCases 33 -> 34`, `docxOpenXmlCoreAssertions 385 -> 418`.

## Dependency Closure

No new dependency blocker. This slice reuses the existing native PHP DOCX ZIP/OPC package fixture path, `DocxReader` run-property metadata merge, `MarkdownWriter`, and `WordPressBlockWriter`. It did not require Pandoc, Haskell test binaries, Word, LibreOffice, zip/unzip, external converters, TeX/PDF engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Non-Overlap

This avoids the recently accepted/pending DOCX clusters for chart embedded-data provenance, custom XML property diagnostics, picture effects, paragraph policy flags, tracked formatting changes, proof-error/permission ranges, and PDF engine signature lock diagnostics. It maps a distinct WordprocessingML run proofing policy flag (`w:noProof`) rather than proof-error range markers (`w:proofErr`).

## Follow-Up

Next DOCX/OpenXML work should stay non-overlapping, for example connector/group-shape metadata, theme color/style inheritance edges, or chart style/color metadata.

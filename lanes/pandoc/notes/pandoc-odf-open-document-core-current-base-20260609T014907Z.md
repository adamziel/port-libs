# ODF OpenDocument Font-Face Pitch Metadata Slice

- Micro-slice: `pandoc-odf-open-document-core-current-base-20260609T014907Z`
- Base accepted HEAD: `08f16fc4bbcf45b83d9ea2497b2ad817ee73416e`
- Upstream source truth: pinned Pandoc `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, especially `src/Text/Pandoc/Readers/ODT/StyleReader.hs` `readFontPitches`, `fontPitchReader`, `findPitch`, and `TextProperties.pitch`, plus `ContentReader` style-diff handling.

## Implemented

- Added native ODF `office:font-face-decls/style:font-face` parsing to `OdfReader`.
- Preserved font-face declarations in the package result, document attributes, and import report style metadata.
- Resolved `style:text-properties style:font-name` to inherited `style:font-pitch` declarations, including `content.xml` automatic styles that reference `styles.xml` font faces.
- Preserved direct `style:font-pitch` overrides while retaining the referenced font-face metadata for review.
- Kept rendered inline behavior stable: fixed-pitch metadata does not convert arbitrary spans into code; existing `Source_Text` inline-code behavior remains unchanged.

## Evidence

- Baseline focused run before patch: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> `1 test files, 2692 assertions, 0 failures`.
- Final focused run after patch: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> `1 test files, 2716 assertions, 0 failures`.
- Added focused delta: `+1` PHP PASS case / `+24` focused assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` -> `odf open document handoff self-test ok`.

## Dependency Closure

No new support component is needed. This slice reuses the native ODF package/style reader, Markdown writer, WordPress block writer, focused ODF reader tests, and the existing WordPress ODF handoff example. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ODF text:tab normalization, blockquote style mapping, heading auto/source id mapping, conditional/hidden text fields, chart metadata, drop-down field handoff, table caption/span, field, form, tracked-change, or embedded object slices. The behavior is scoped to font-face declaration metadata and font-pitch resolution for ODF text styles.

## Follow-Up

Potential next ODF work: bounded list-level text-properties inheritance or automatic style family-specific properties not already surfaced in `OdfReader`, with the same no-office-suite/no-external-converter constraints.

# Legacy DOC EQ Field Handoff

Date: 2026-06-09 UTC

Slice: `pandoc-legacy-doc-cfb-core-current-base-duplicate-20260609T052246Z`

Base: `aeac7627505caef0c7f45b74c533b70ec36e1807`

## Source Truth

- Microsoft MS-DOC `flt` enumerates field type `0x31` as `EQ`.
- This slice ports the format contract only: the native reader preserves the cached displayed result and field-code metadata, but does not evaluate Word EQ instructions or call external converters.

## Behavior

- `LegacyDocReader` now maps Plcfld type code `0x31` to `eq`.
- `EQ` field instructions now render as `legacy-doc-equation-field` spans with:
  - normalized hidden instruction metadata;
  - metadata-only native review policy;
  - preserved EQ field code excluding generic result-format switches;
  - cached displayed-result character count.
- WordPress block output keeps the cached equation result visible while hiding `EQ`, `\f(...)`, and `MERGEFORMAT` instruction text from visible content.

## Evidence

- Red-first focused run after adding the test only:
  - `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Failed as expected: Plcfld type `0x31` reported `unknown`.
- Final focused run:
  - `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `1 test files, 2148 assertions, 0 failures`
  - Baseline before this slice: `1 test files, 2126 assertions, 0 failures`
  - Delta: `+1` PHP PASS case, `+22` focused assertions.
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-legacy-doc-eq-field-handoff.php --self-test`
  - Passed.

## Dependency Closure

No new support component is needed. This reuses the lane-local CFB/FIB/Plcfld parsing, `LegacyDocReader`, `MarkdownWriter`, `WordPressBlockWriter`, and focused PHP test runner. Full upstream Pandoc runner parity and live Word/Pandoc conversion remain out of scope for this isolated slice.

## Non-Overlap

This avoids the accepted legacy DOC/CFB clusters for hyperlinks, cross references, source-location fields, include aliases, literal QUOTE/SHAPE fields, SECTION/SECTIONPAGES fields, numbering fields, forms, DATA/SET/mail-merge metadata, encrypted FIB guards, Unicode FIB text, CFB structural preflight, and inline picture placeholders.

# Pandoc Legacy DOC/CFB Current-Base Include Fields

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T064913Z`
Base: `c73ab3af9ca883f50ffd6b3d1d33ae6c6162db8c`

## Behavior

Legacy DOC field results for `INCLUDEPICTURE` and `INCLUDETEXT` now remain visible as inert review spans instead of plain text. The spans preserve field instruction provenance, include source, source kind, basename, switches, format switches, and locked-result metadata while keeping the raw field instruction out of rendered text.

The WordPress legacy DOC handoff fixture now exercises both field types and confirms the Plcfld type codes `0x43` and `0x44` remain visible in the field inventory.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed before this slice.
- Red-first check: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` failed with the new include-field result still rendered as plain text: `1 test files, 1150 assertions, 1 failures`.
- Green focused check: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed: `1 test files, 1178 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` passed: `legacy doc handoff self-test ok`.

## Status Delta

- `lane-status.json` `phpPass`: `1554 -> 1555`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1975 -> 1976`.
- Focused assertion delta for `LegacyDocReaderTest.php`: `+28`.

## Dependency Closure

No new support component is needed. This reuses the native `LegacyDocReader` field-token handling, CFB/DOC fixture builders, `MarkdownWriter`, `WordPressBlockWriter`, and the lane-local legacy DOC handoff example.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not change CFB header/DIFAT/MiniFAT validation, FIB/CLX piece-table parsing, DOP metadata, saved-by/user metadata, bookmarks, notes, comments, sections, styles, lists, existing hyperlink/page/form/cross-reference/data/prompt/symbol/generated/numbering/SET field behavior, OLE object exposure, pictures, or macro policy. It owns only `INCLUDEPICTURE` and `INCLUDETEXT` displayed-result handoff metadata.

## Next

Next legacy DOC/CFB work should choose a non-overlapping native gap such as safe OLE object relationship handoff, additional Plcfld story coverage, annotation/bookmark boundary metadata, or FIB/CLX preflight behavior.

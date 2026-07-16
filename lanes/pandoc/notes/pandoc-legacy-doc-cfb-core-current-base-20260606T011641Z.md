# Pandoc Legacy DOC CFB Core Current Base - 2026-06-06T011641Z

## Scope

Implemented the bounded legacy DOC/CFB current-base slice for Word `SYMBOL`
field-code provenance.

- `LegacyDocReader` now wraps displayed `SYMBOL` field results in
  `legacy-doc-symbol-field` spans.
- The span preserves the hidden field instruction plus bounded symbol code,
  font, size, and switch metadata.
- The visible glyph remains normal rendered text for Markdown and WordPress,
  while the hidden field instruction stays out of visible post content.
- `wordpress-legacy-doc-handoff.php --self-test` now exercises the same
  `SYMBOL` field path in the synthetic legacy DOC CFB packet.

## Source Truth

This ports the bounded Word field-code contract needed by legacy Word imports:
field begin, separator, and end characters delimit a hidden instruction and a
displayed result, and `SYMBOL` is a legacy Word field type whose instruction
can carry a source character code plus font/size/switch provenance. The slice
keeps the displayed result visible and records source provenance for review; it
does not calculate Word layout, load fonts, execute Word automation, decrypt
DOC files, or invoke Pandoc.

## Verification

Baseline before edits:

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
- Result: `1 test files, 633 assertions, 0 failures`

After implementation:

- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
- Result: `1 test files, 646 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
- Result: `legacy doc handoff self-test ok`

## Status Delta

- `lane-status.json` `phpPass`: `1135 -> 1136`
- `UPSTREAM_TEST_MANIFEST.json` mapped checks: `1587 -> 1588`
- Legacy DOC/CFB mapped cases: `6 -> 7`
- Legacy DOC/CFB focused assertion inventory: `38 -> 51`
- Focused test delta: `+1` PASS case / `+13` assertions

## Dependency Closure

No new support component is needed. This reuses native PHP
`CompoundFileBinary`, `LegacyDocReader`, `MarkdownWriter`,
`WordPressBlockWriter`, focused PHP tests, and the existing WordPress legacy DOC
handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
external office tool, online sanitizer, online service, or live provider test
was executed.

## Non-Overlap

This slice does not repeat accepted CFB header/FAT/DIFAT/MiniFAT/directory
safety, directory timestamp/CLSID/state-bit provenance, encrypted FIB
rejection, `fExtChar` Unicode text ranges, CLX/Pcd text extraction and PCD flag
validation, FibRgLw97 subdocument boundaries, bookmarks, note/comment PLCs,
sections, styles, formatting tables, list tables, hyperlinks, page/date/count
fields, form fields, cross-reference fields, ObjectPool embedded object
inventory, macro-project preflight, OLE property scalars, DOCX/ODT/EPUB/PDF,
charset, YAML, CSL/BibTeX, XML/HTML5 DOM, syntax highlighting, ZIP/OPC, or
upstream-runner dependency audit slices.

## Next

Keep FFData table-property expansion, header/footer subdocument routing, image
extraction policy, encrypted DOC password/decryption behavior, and full
upstream Pandoc Haskell runner parity as separate bounded follow-ups.

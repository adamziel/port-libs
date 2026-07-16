# Charset Unicode Width Core Current Base - KOI8-T/Tajik

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T034225Z`
Base accepted HEAD: `91cca3175da49493fc1f64ed296d9fb56109fdfc`

## Behavior

- Added native bounded KOI8-T/Tajik byte decoding labels to `UnicodeText`.
- Mapped Tajik Cyrillic and punctuation bytes from local source truth `/usr/share/tcl9.0/encoding/koi8-t.enc`.
- Repaired undefined KOI8-T bytes with U+FFFD while preserving sourceEncoding repair counts.
- Added WordPress charset handoff coverage for a Tajik legacy Markdown review packet, including display-width audit output.

## Evidence

- Baseline before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1326 assertions, 0 failures`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1340 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `UnicodeText` decoding, `MarkdownReader` sourceEncoding provenance, `MarkdownWriter` display-width helpers, `WordPressBlockWriter`, focused PHP tests, and the existing WordPress charset handoff example. No Pandoc, Cabal solver/build/test command, Haskell runner, TeX/PDF engine, Word, LibreOffice, zip/unzip, browser renderer, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted KOI8-R/U, Windows/ISO/Mac Cyrillic, DOS codepage, CJK multibyte, emoji/ZWJ/tag, Indic/Southeast Asian grapheme, Unicode separator/control, ambiguous-width, XML/HTML5 DOM, ODT, DOCX, EPUB, ZIP/OPC, archive, math, citation, BibTeX/CSL, table geometry, syntax-highlighting, or upstream-runner dependency audit slices. It is limited to KOI8-T/Tajik byte decoding and charset handoff evidence under `lanes/pandoc/**`.

## Next

Pick a non-overlapping charset/Unicode gap such as ISO-2022-KR state handling, GB12345 byte mapping, or another display-width edge not already covered by current charset slices.

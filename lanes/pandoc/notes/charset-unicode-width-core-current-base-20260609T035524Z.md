# Charset Unicode Width Core Current Base - GB12345

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T035524Z`
Base accepted HEAD: `4cca1c57da8720c140326c22572dbfb45205f318`

## Behavior

- Added bounded native GB12345 byte decoding to `UnicodeText`.
- Mapped the focused GB12345 pairs from local source truth
  `/usr/share/tcl9.0/encoding/gb12345.enc`.
- Preserved `gb12345` sourceEncoding provenance through `MarkdownReader`.
- Repaired malformed trails and valid-but-unmapped GB12345 pairs with U+FFFD.
- Added a WordPress charset audit row for a traditional Chinese GB12345 review
  packet, including display-width accounting.

## Evidence

- Baseline before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1357 assertions, 0 failures`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1371 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `UnicodeText`
decoding, `MarkdownReader` sourceEncoding provenance, display-width helpers,
`WordPressBlockWriter`, focused PHP tests, and the existing WordPress charset
handoff example. No Pandoc, Cabal solver/build/test command, Haskell runner,
TeX/PDF engine, Word, LibreOffice, zip/unzip, browser renderer, external
charset converter, online service, live provider test, or live-service provider
test was executed.

## Non-Overlap

This does not repeat accepted GBK/GB18030, Big5, ISO-2022-KR, KOI8-T,
Windows/ISO/Mac/DOS codepage, Unicode separator/control/emoji/Indic/Southeast
Asian, XML/HTML5 DOM, ODT, DOCX, EPUB, ZIP/OPC, archive, math, citation,
BibTeX/CSL, table geometry, syntax-highlighting, or upstream-runner dependency
audit slices. It is limited to bounded GB12345 traditional Chinese byte
decoding and charset handoff evidence under `lanes/pandoc/**`.

## Next

Pick a non-overlapping charset/Unicode gap such as additional GB12345 pairs
from real fixtures, GB1988 labels, JIS0212/EUC-JP plane-2 pairs, or another
display-width edge not already covered by current charset slices.

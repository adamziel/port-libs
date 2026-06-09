# Pandoc Charset/Unicode Current-Base Mac Japanese Slice

Micro-slice: `pandoc-charset-unicode-width-core-current-base-20260609T040657Z`
Base accepted HEAD: `39b1c5d5b6751a4cd8edd906dabeef64d6d0fc2e`

## Behavior

Added bounded native Mac Japanese byte decoding for Pandoc reader/writer
handoff:

- `UnicodeText` now normalizes `mac-japan`, `macjapan`, `x-mac-japan`,
  `mac-japanese`, `x-mac-japanese`, and `csmacjapanese` labels to canonical
  `mac-japan`.
- Single-byte halfwidth-katakana bytes, `0xA0`, and the Mac-specific
  `0xFD`/`0xFE`/`0xFF` copyright/trademark/ellipsis bytes now decode before
  Markdown parsing.
- Bounded `0x81`, `0x82`, and `0x83` rows decode punctuation, fullwidth
  digits/Latin letters, hiragana, katakana, and Greek letters from local Tcl
  `macJapan.enc` source truth.
- Unsupported larger CJK Mac Japanese double-byte rows, undefined bytes, and
  truncated lead bytes emit U+FFFD and increment repair counts rather than
  silently passing mojibake into WordPress review packets.
- `MarkdownReader`, source-encoding metadata, display-width accounting, and
  `WordPressBlockWriter` preserve decoded legacy Mac Japanese text in the
  charset audit handoff.

Source truth: `/usr/share/tcl9.0/encoding/macJapan.enc`.

## Red-First Evidence

Before the implementation, the new focused test failed because Mac Japanese
labels fell through to UTF-8 repair instead of canonical `mac-japan` decoding.

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1372 assertions, 1 failures`
  - Failure: expected source encoding `mac-japan`, actual `utf-8-repaired`

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1387 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors

Focused assertion delta: `+16` assertions. Focused PASS-case delta: `+1`
Pandoc charset case.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `UnicodeText`,
`MarkdownReader`, `WordPressBlockWriter`, display-width accounting, the focused
Unicode test file, the WordPress charset handoff example, and the local Tcl
encoding table as source truth. No Pandoc, Cabal solver/build/test command,
Haskell runner, Word, LibreOffice, zip/unzip, external charset converter,
browser renderer, online service, live provider test, or live-service provider
test was executed.

## Non-Overlap

This does not repeat accepted UTF BOM handling, UTF-32, malformed UTF-8 repair,
Windows/ISO/DOS/KOI8 code pages, MacRoman, MacTurkish, MacCyrillic,
MacUkrainian, MacGreek, MacIcelandic, Mac Central European, Mac Romanian,
Mac Croatian, Mac Thai, MacDingbats, Mac Symbol, Shift_JIS, EUC-JP,
ISO-2022-JP, Big5, GBK/GB18030/GB12345, declared HTML/XML charset sniffing,
Unicode normalization, display-width wrapping, or emoji/grapheme slices. It
only adds bounded Mac Japanese source-byte decoding and proves WordPress
handoff metadata.

## Next

A future non-overlapping charset slice can cover additional macJapan CJK rows
from real fixtures, JIS0212/EUC-JP plane-2 pairs, GB1988 labels, or another
display-width edge. Full upstream Pandoc runner parity remains a separate
Cabal/Haskell dependency gate.

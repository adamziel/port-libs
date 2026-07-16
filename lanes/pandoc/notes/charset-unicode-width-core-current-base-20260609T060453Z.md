# Charset Unicode Width Core Current Base - Big5 Kana/Fullwidth

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T060453Z`
Base accepted HEAD: `11b5789183ebb8ab34ff922479caf161e9cc4881`

## Behavior

- Added bounded native Big5 byte mappings for kana iteration marks, kana, and fullwidth digits used by legacy Traditional Chinese/Japanese review packets.
- Source truth is the local static Tcl table `/usr/share/tcl9.0/encoding/big5.enc`:
  - `A2 AF..B1` maps to fullwidth digits `0..2`;
  - `C6 A1..A6` maps to `ヾ`, `ゝ`, `ゞ`, `々`, `ぁ`, and `あ`.
- Preserved existing Big5 Chinese fixture behavior, two-codepoint Big5 pointer sequences, MarkdownReader `sourceEncoding` provenance, WordPress block output, and Unicode display-width slicing.
- Added a WordPress charset audit row for Big5 kana/fullwidth review packets.

## Evidence

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1525 assertions, 0 failures
```

Focused verification after this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1536 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test
charset unicode handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native support component is needed. This slice reuses `UnicodeText`,
`MarkdownReader`, `WordPressBlockWriter`, the focused PHP test runner, and the
existing WordPress charset handoff example.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external template engine, external charset converter,
TeX/PDF engine, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ISO-2022-CN GB2312, EUC-TW plane-1, KOI8-RU,
GB1988, GB12345, EUC-JP JIS0212, CP950 Euro/private extension, GBK/GB18030,
Big5 base Chinese fixture, Big5 two-codepoint pointer sequences, or Unicode
display-width-only slices. It is limited to bounded Big5 kana/fullwidth byte
decoding and WordPress charset audit coverage under `lanes/pandoc/**`.

## Next

Pick a non-overlapping charset or width gap such as additional Big5/CNS pairs
from local source tables, source-backed Big5-HKSCS mappings, or another
display-width edge not already covered by current charset slices.

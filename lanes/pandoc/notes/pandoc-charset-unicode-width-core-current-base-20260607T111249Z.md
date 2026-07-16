# Pandoc Charset/Unicode Width Slice - Windows-1255

Slice: `pandoc-charset-unicode-width-core-current-base-20260607T111249Z`
Base accepted HEAD: `f0ab63b0aec4070b72a5ad36f42b8b417227d7b2`

## Source Truth

- WHATWG Encoding Windows-1255 index: https://encoding.spec.whatwg.org/index-windows-1255.txt
- WHATWG Windows-1255 table view: https://encoding.spec.whatwg.org/windows-1255.html
- No Pandoc, Cabal solver/build/test command, Haskell runner, external charset converter, browser renderer, online service, live provider test, or live-service provider test was executed.

## Implemented Behavior

- Added native `windows-1255` byte decoding in `UnicodeText`, including aliases `cp1255`, `microsoft-cp1255`, `ms1255`, and `x-cp1255`.
- Preserved Hebrew letters, niqqud marks, Hebrew punctuation, sheqel sign, smart punctuation, and LRM/RLM marks.
- Undefined Windows-1255 byte slots now repair to U+FFFD and increment repair counts.
- Markdown source-encoding metadata and WordPress charset audit output now carry the decoded Windows-1255 handoff.

## Verification

- Baseline before this slice: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 697 assertions, 0 failures`
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 710 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - `charset unicode handoff self-test ok`

## Dependency Closure

No new support component is needed. This reuses native `UnicodeText`, `MarkdownReader`, `WordPressBlockWriter`, and the existing WordPress charset/Unicode handoff example.

## Non-Overlap

This slice does not repeat the accepted ISO-8859-8 Hebrew, ISO-8859-7 Greek, Windows-1253 Greek, Windows-1254 Turkish, Shift_JIS, GBK, Big5, HZ-GB-2312, Indic virama, Myanmar/Khmer conjunct, or Unicode GLOB work. Follow-up charset work should stay on non-overlapping Windows-1256 Arabic, Windows-1257 Baltic, Windows-1258 Vietnamese, or charset-sniffing handoff gaps.

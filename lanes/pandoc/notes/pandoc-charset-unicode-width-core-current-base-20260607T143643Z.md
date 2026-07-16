# Pandoc Charset/Unicode Width Core - Windows-1256 Arabic Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260607T143643Z`
Base accepted HEAD: `d30a47d3f1909bba68426d3e20e0f67927b5f01d`

## Behavior

- Added bounded `windows-1256` / `cp1256` label recognition to `UnicodeText`.
- Decodes the Windows-1256 Arabic byte map used by WHATWG Encoding, including Arabic, Persian, and Urdu letters, smart punctuation, Euro, ZWNJ/ZWJ, and LRM/RLM bytes.
- Preserves source encoding metadata, zero-width display accounting, and WordPress charset-audit output through `MarkdownReader` and `WordPressBlockWriter`.

## Source Truth

- WHATWG Encoding Windows-1256 index: https://encoding.spec.whatwg.org/index-windows-1256.txt
- No Pandoc, Cabal solver/build/test command, Haskell runner, external charset converter, browser renderer, online service, live provider test, or live-service provider test was executed.

## Red-First Evidence

- Baseline before the focused case: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 710 assertions, 0 failures`.
- After adding the Windows-1256 case before implementation, the same focused test failed as expected: expected canonical encoding `windows-1256`, actual `utf-8-repaired`; `1 test files, 711 assertions, 1 failures`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` => `1 test files, 722 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test` => `charset unicode handoff self-test ok`.
- PHP lint and `git diff --check -- lanes/pandoc` were run after implementation.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native `UnicodeText` byte decoding and display-width helpers, `MarkdownReader` source metadata, focused Unicode tests, `WordPressBlockWriter`, and the existing WordPress charset handoff example.

## Non-Overlap

This slice is distinct from accepted ISO-8859-6 Arabic, ISO-8859-7 Greek, ISO-8859-8 / Windows-1255 Hebrew, ISO-8859-3 Latin-3, ISO-8859-9 Turkish, Shift_JIS, Big5/GBK, HZ-GB-2312, Indic virama, Myanmar/Khmer conjunct, and Unicode GLOB/display-width slices. A useful follow-up would be another non-overlapping charset gap such as Windows-1257/1258, declared HTML/XML charset sniffing, or bidi-review metadata.

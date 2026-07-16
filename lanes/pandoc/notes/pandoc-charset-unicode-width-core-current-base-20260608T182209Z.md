# Pandoc Charset/Unicode Width Core: UTF-8 Malformed Scalar Repair

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T182209Z`
Base accepted HEAD: `74e2e1d508ba035b714146936835879271d84645`

## Scope

This slice adds a bounded native PHP UTF-8 repair behavior needed before Markdown and WordPress handoff:

- Complete but invalid UTF-8 scalar byte sequences now emit one `U+FFFD` replacement and one repair count per sequence.
- Covered invalid scalar forms are UTF-8 encoded surrogate halves, overlong scalar encodings, and code points beyond `U+10FFFF`.
- Structurally broken continuation sequences still repair per broken byte, preserving the existing `Broken \xE2(\xA1 UTF-8` behavior.

The source-truth contract is Unicode scalar validity for UTF-8 decoding: byte sequences that are structurally complete but encode invalid scalar values must not leak individual continuation bytes into the AST, Markdown, or WordPress handoff path.

## Implementation

- `UnicodeText::repairUtf8()` now advances across a complete invalid scalar sequence when all expected continuation bytes are present.
- Added a small continuation-byte helper reused by sequence validation.
- The existing sourceEncoding metadata path still reports `utf-8-repaired`, the BOM value, and the repair count.
- The WordPress charset handoff example now includes a `UTF-8 scalar repair` audit row with repaired text, repair count, and narrow/wide display widths.

## Evidence

- Baseline before this focused assertion growth:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 896 assertions, 0 failures`
- Focused final test:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 908 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - `charset unicode handoff self-test ok`
- PHP syntax:
  - `php -l lanes/pandoc/src/UnicodeText.php`
  - `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
- JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`

## Dependency Closure

No new native PHP support component is needed. This reuses `UnicodeText`, `MarkdownReader`, `MarkdownWriter`, and `WordPressBlockWriter`.

No Pandoc, Cabal solver/build/test command, Haskell runner, external charset converter, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat the accepted charset slices for Shift_JIS, HZ-GB-2312, ISO-8859-3/7/8, Windows-1256, Indic/Myanmar/Khmer display clusters, format controls, or Unicode GLOB behavior. It is limited to malformed UTF-8 scalar repair in the shared byte-decoding path.

Root harness: not run - isolated micro-slice.

# Pandoc Charset Unicode Width Core Current Base

Micro-slice: `pandoc-charset-unicode-width-core-current-base-20260608T081210Z`

Accepted base: `1f56cf80baabc1fc215f50cf306dc9c1a354f581`

## Behavior

`UnicodeText::displayWidth()` now treats U+2028 LINE SEPARATOR and U+2029 PARAGRAPH SEPARATOR as zero-column formatting controls for direct display accounting. The same characters continue to act as hard line boundaries in `wrapByDisplayWidth()`.

The focused test covers:

- zero-width direct accounting for U+2028 and U+2029;
- display breakpoint splitting without consuming visible columns for the separators;
- padding based on visible width rather than separator bytes;
- wrapping that still returns separate lines for the separator boundaries.

The WordPress charset handoff smoke includes an escaped `[LS]` / `[PS]` audit row so reviewer output can prove the hidden separators are accounted as zero-width while the wrapped output remains `A / B / U+9B5A`.

## Source Truth And Non-Overlap

This reuses the existing charset/Unicode support row for Pandoc shared display-width behavior. Earlier separator coverage in this lane already mapped U+2028 and U+2029 as hard wrapping boundaries. This slice does not duplicate charset decoding, Unicode normalization, soft-break handling, tab stops, emoji width clusters, or the existing separator wrapping case; it only closes the direct display-width accounting gap for those hard separators across width, split, and padding APIs.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external charset converter, browser renderer, online service, live provider test, or live-service provider test was run.

## Evidence

- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 804 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 812 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test` passed with `charset unicode handoff self-test ok`.
- PHP lint: `php -l lanes/pandoc/src/UnicodeText.php`, `php -l lanes/pandoc/tests/UnicodeTextTest.php`, and `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php` passed.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace check: `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
- Status delta: `phpPass` `1575 -> 1576`, mapped denominator `1996 -> 1997`.

## Dependency Closure

No new native PHP support component is needed. The slice reuses `UnicodeText`, `MarkdownReader`, `MarkdownWriter`, and `WordPressBlockWriter`.

Full upstream runner parity remains blocked on a hydrated pinned Pandoc checkout and an explicitly reviewed non-mutating Cabal plan. That remains outside this isolated micro-slice.

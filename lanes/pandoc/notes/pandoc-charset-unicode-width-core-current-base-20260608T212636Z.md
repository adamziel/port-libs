# Pandoc Charset/Unicode Width Core - IBM861/CP861 DOS Icelandic Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T212636Z`
Base accepted HEAD: `d1134e2a181aaf4c0c02f2b0d3b93f388be55ad8`

## Behavior

- Added bounded native IBM861/CP861/DOS861 label recognition to `UnicodeText`.
- Decodes the CP861 Icelandic high-byte overrides used by legacy DOS Markdown imports, including Á/Í/Ó/Ú, á/í/ó/ú, Ð/ð, Þ/þ, Ý/ý, Ø/ø, £, and shared CP437 box/math bytes.
- Preserves canonical source encoding metadata and display-width audit rows through `MarkdownReader` and `WordPressBlockWriter`.

## Source Truth

- Local system Tcl charset map `/usr/share/tcl9.0/encoding/cp861.enc` for the CP861 byte table.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external charset converter, browser renderer, online service, live provider test, or live-service provider test was executed.

## Red-First Evidence

- Baseline before adding the new focused case: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` => `1 test files, 1038 assertions, 0 failures`.
- After adding the CP861 case before implementation: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` => `1 test files, 1039 assertions, 1 failures`; expected canonical encoding `ibm861`, actual `utf-8-repaired`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` => `1 test files, 1049 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test` => `charset unicode handoff self-test ok`.
- `php -l lanes/pandoc/src/UnicodeText.php`, `php -l lanes/pandoc/tests/UnicodeTextTest.php`, and `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php` => no syntax errors detected.
- `git diff --check -- lanes/pandoc` => no output.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native `UnicodeText` byte decoding and display-width helpers, `MarkdownReader` source metadata, focused Unicode tests, `WordPressBlockWriter`, and the existing WordPress charset handoff example.

## Non-Overlap

This slice is distinct from accepted IBM437/850/852/860/863/865/866, Windows-1256/1258, ISO-8859-3/7/8/9/11/16, TIS-620/Windows-874, Shift_JIS/EUC-JP/ISO-2022-JP, Big5/GBK/GB18030/EUC-KR/HZ-GB-2312, and Unicode display-width cluster slices. A useful follow-up would be another non-overlapping charset gap such as IBM775 Baltic or CP862 Hebrew, or additional Unicode line-break metadata.

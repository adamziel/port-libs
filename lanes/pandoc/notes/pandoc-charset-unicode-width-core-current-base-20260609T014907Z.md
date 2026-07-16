# Pandoc Charset/Unicode Current-Base Mac Thai Slice

Micro-slice: `pandoc-charset-unicode-width-core-current-base-20260609T014907Z`
Base accepted HEAD: `08f16fc4bbcf45b83d9ea2497b2ad817ee73416e`

## Behavior

Added bounded native Mac Thai single-byte decoding for Pandoc reader/writer handoff:

- `x-mac-thai`, `mac-thai`, `macthai`, and related aliases normalize to `mac-thai`.
- Bytes map through the local Tcl `macThai.enc` single-byte table.
- Undefined Tcl slots `0x90`, `0x9f`, and `0xfc`-`0xff` become U+FFFD and increment repair diagnostics.
- Thai bytes, Mac Thai punctuation, PUA glyph slots, FEFF, and zero-width space are preserved through `MarkdownReader`, `sourceEncoding`, display-width accounting, and `WordPressBlockWriter`.

Source truth: `/usr/share/tcl9.0/encoding/macThai.enc` static table. No external charset converter, Pandoc runner, Cabal/Haskell runner, browser renderer, online service, live provider test, or live-service provider test was executed.

## Evidence

Rework notes: none found for `port-pandoc-*.needs-lane-rework.md`.

Baseline focused test:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
- Result before this slice: `1 test files, 1229 assertions, 0 failures`.

Red-first check after adding the Mac Thai test only:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
- Result: failed the new Mac Thai case, expected `mac-thai`, actual `utf-8-repaired`; `1 test files, 1230 assertions, 1 failures`.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
- Result: `1 test files, 1245 assertions, 0 failures`.

Example smoke:

- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
- Result: `charset unicode handoff self-test ok`.

Lint and metadata validation:

- `php -l lanes/pandoc/src/UnicodeText.php`: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`: passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2489 -> 2490`.
- `mappedCharsetUnicodeWidthCoreCases`: `9 -> 10`.
- `charsetUnicodeWidthCoreAssertions`: `65 -> 81`.
- Focused `UnicodeTextTest.php`: `1229 -> 1245` assertions, plus one PASS case.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `UnicodeText`, `MarkdownReader`, `WordPressBlockWriter`, the existing focused Unicode test file, and the charset handoff example. The next non-overlapping charset follow-up should target another unclaimed legacy Mac code page or a distinct Unicode display-width/repair edge needed by Pandoc conversion.

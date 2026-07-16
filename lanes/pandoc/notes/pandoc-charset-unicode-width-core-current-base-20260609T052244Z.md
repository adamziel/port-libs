# CP950 Charset/Unicode Handoff

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T052244Z`
Base accepted HEAD: `aeac7627505caef0c7f45b74c533b70ec36e1807`

## Behavior

`UnicodeText` now normalizes `cp950`, `windows-950`, and `ms950` labels and overlays a bounded CP950 extension table on top of the existing native Big5 decoder. The mapped pairs cover WordPress import-visible bytes for U+20AC, U+7881, U+92B9, selected punctuation, and box-drawing characters. Plain `big5` still treats those CP950-only extension pairs as repaired/unmapped text, preserving the existing Big5 behavior.

Source truth for this bounded table was the local Tcl encoding table at `/usr/share/tcl9.0/encoding/cp950.enc`. No online service or external converter was used.

## Red-First Evidence

Before implementation, the new focused test failed:

`php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`

Result: `FAIL decodes cp950 big5 extension bytes into wordpress blocks`; expected encoding `cp950`, actual `utf-8-repaired`; `1 test files, 1454 assertions, 1 failures`.

## Final Verification

`php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`

Result: `1 test files, 1474 assertions, 0 failures`.

`php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`

Result: `charset unicode handoff self-test ok`.

Focused delta: +1 PHP PASS case and +21 focused assertions in `UnicodeTextTest.php`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `UnicodeText`, `MarkdownReader`, `WordPressBlockWriter`, the focused PHP test runner, the lane-local WordPress charset handoff example, and a bounded local encoding-table fixture. Full upstream Pandoc runner parity remains a separate upstream-runner dependency task.

## Non-Overlap

This does not repeat accepted Big5 pointer-sequence handling, GBK/GB12345/GB18030 decoding, ISO-2022/HZ/JIS/Mac/Korean/KOI8 slices, display-width control handling, or ODF/doctemplate/package work. It specifically covers CP950/Windows-950 extension bytes needed by Pandoc charset handoff behavior.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external template engine, external converter, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

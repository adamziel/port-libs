# Pandoc Charset/Unicode Width Current-Base Slice

Micro-slice: `pandoc-charset-unicode-width-core-current-base-20260609T013815Z`
Base accepted HEAD: `72bdfd8308ce4b57fa512b92a3a80b6f1309110e`

## Behavior

Added native Mac Croatian single-byte decoding in `UnicodeText`.
The slice normalizes labels such as `maccroatian`, `mac-croatian`, and
`x-mac-croatian` to canonical `mac-croatian`, then applies the Mac Croatian
override bytes before Markdown and WordPress block handoff.

Source truth for the byte table was the local Tcl encoding map:
`/usr/share/tcl9.0/encoding/macCroatian.enc`.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` files existed.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1210 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1224 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  passed with `charset unicode handoff self-test ok`.

## Dependency Closure

No new support component is needed. This reuses the native PHP `UnicodeText`
decoder, `MarkdownReader` byte-source handoff, `WordPressBlockWriter`, and the
existing WordPress charset audit example. No Pandoc, Cabal solver/build/test
command, Haskell runner, external charset converter, browser renderer, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This avoids accepted ISO-8859, Windows, IBM/DOS, KOI8, MacRoman, MacTurkish,
MacCyrillic, MacGreek, MacIceland, MacCentralEuropean, and MacRomanian charset
slices. A next non-overlapping charset slice could cover bounded `macThai`
decoding or a distinct Unicode display-width edge.

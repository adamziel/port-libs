# Pandoc Charset/Unicode Current-Base Mac Ukrainian Slice

Micro-slice: `pandoc-charset-unicode-width-core-current-base-20260609T020811Z`
Base accepted HEAD: `ae05f994f04ccc78db62e7bd6dd42669f76246b1`

## Behavior

Added bounded native Mac Ukrainian single-byte decoding for Pandoc
reader/writer handoff:

- `x-mac-ukrainian`, `mac-ukrainian`, `mac-ukraine`, and related aliases now
  normalize to canonical `mac-ukrainian` instead of `mac-cyrillic`.
- The decoder reuses the Mac Cyrillic table and applies the Tcl `macUkraine`
  difference where byte `0xFF` maps to U+00A4 currency sign rather than the
  Mac Cyrillic Euro mapping.
- Markdown source metadata, display-width accounting, and the WordPress
  charset audit example now preserve Mac Ukrainian decoded text.

Source truth: `/usr/share/tcl9.0/encoding/macUkraine.enc`.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` files existed.
- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1271 assertions, 0 failures`.
- Red-first after adding the Mac Ukrainian test:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  failed as expected because `x-mac-ukrainian` returned canonical
  `mac-cyrillic`; result was `1 test files, 1272 assertions, 1 failures`.
- Final focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1285 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  passed with `charset unicode handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `UnicodeText`,
`MarkdownReader`, `WordPressBlockWriter`, the focused Unicode test file, and
the existing WordPress charset handoff example. No Pandoc, Cabal solver/build
or test command, Haskell runner, Word, LibreOffice, external charset converter,
browser renderer, online service, live provider test, or live-service provider
test was executed.

## Non-Overlap

This does not repeat accepted ISO-8859, Windows, IBM/DOS, KOI8, MacRoman,
MacTurkish, MacCyrillic, MacGreek, MacIceland, MacCentralEuropean,
MacRomanian, MacCroatian, MacThai, or declared-BOM charset slices. It only
separates the Mac Ukrainian byte page from the accepted Mac Cyrillic table and
proves the byte `0xFF` mapping difference through Markdown and WordPress
handoff.

## Next

A next charset slice can stay non-overlapping by covering another bounded
legacy symbol table such as MacDingbats, declared HTML/XML parser integration,
terminal-profile-specific emoji width variants, or a broader Unicode width
table refresh.

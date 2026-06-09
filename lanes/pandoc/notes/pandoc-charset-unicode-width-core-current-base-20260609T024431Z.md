# Pandoc Charset/Unicode Current-Base MacDingbats Slice

Micro-slice: `pandoc-charset-unicode-width-core-current-base-20260609T024431Z`
Base accepted HEAD: `12507a9792ad5cde3ccd9d84d97d5835d2a8ef77`

## Behavior

Added bounded native MacDingbats single-byte decoding for Pandoc
reader/writer handoff:

- `macdingbats`, `mac-dingbats`, `x-mac-dingbats`, and related aliases now
  normalize to canonical `mac-dingbats`.
- The decoder maps the legacy MacDingbats symbol bytes into Unicode symbols,
  including private-use slots `0x80..0x8D`.
- Undefined MacDingbats bytes now produce U+FFFD and increment repair counts
  instead of silently using unrelated single-byte text mappings.
- Markdown source metadata, display-width accounting, and the WordPress
  charset audit example preserve the decoded symbol text.

Source truth: `/usr/share/tcl9.0/encoding/macDingbats.enc`.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` files existed.
- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1298 assertions, 0 failures`.
- Final focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1313 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  passed with `charset unicode handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `UnicodeText`,
`MarkdownReader`, `WordPressBlockWriter`, the focused Unicode test file, and
the existing WordPress charset handoff example. No Pandoc, Cabal solver/build
or test command, Haskell runner, Word, LibreOffice, zip/unzip, external charset
converter, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ISO-8859, Windows, IBM/DOS, KOI8, MacRoman,
MacTurkish, MacCyrillic, MacUkrainian, MacGreek, MacIceland,
MacCentralEuropean, MacRomanian, MacCroatian, MacThai, or declared-BOM charset
slices. It only adds the legacy MacDingbats symbol table and proves symbol,
private-use, undefined-byte repair, width, Markdown, and WordPress handoff
behavior.

## Next

A next charset slice can stay non-overlapping by covering declared HTML/XML
charset parser integration, terminal-profile-specific emoji width variants, or
a broader Unicode width table refresh.

# Pandoc Charset/Unicode Current-Base Mac Symbol Slice

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T031408Z`
Base accepted HEAD: `915ae6d7e19462f5fae70630857416b816400e62`

## Behavior

Added bounded native Mac Symbol / Symbol single-byte decoding for Pandoc reader
and writer handoff:

- `UnicodeText` now normalizes `symbol`, `mac-symbol`, `x-mac-symbol`,
  `macsymbol`, `xmacsymbol`, and `cssymbol` to canonical `mac-symbol`.
- Printable Symbol bytes now decode through the static Tcl `symbol.enc` table,
  so ASCII-looking bytes such as `A`, `B`, `G`, `W`, `a`, `b`, `g`, and `w`
  become Greek/math glyphs instead of ordinary ASCII text.
- Undefined Symbol bytes such as `0x80`, `0xA0`, and `0xFF` emit U+FFFD and
  increment repair counts.
- `MarkdownReader`, source-encoding metadata, display-width accounting, and
  `WordPressBlockWriter` preserve decoded Greek/math/PUA glyphs in the charset
  audit handoff.

Source truth: `/usr/share/tcl9.0/encoding/symbol.enc`.

## Red-First Evidence

Before the implementation, the new focused test would have failed because
`symbol` normalized to UTF-8 and printable Symbol bytes were exposed as ASCII
letters instead of `Α Β Γ Ω α β γ ω ≥ ≠≡≈ ∏√∑ ∫ `.

Baseline focused run before editing:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
- Result: `1 test files, 1313 assertions, 0 failures`

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1326 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors

Focused assertion delta: `+13` assertions. Focused PASS-case delta: `+1`
Pandoc charset case.

## Non-Overlap

This slice does not repeat accepted charset clusters for UTF BOM handling,
UTF-32, malformed UTF-8 repair, Windows/ISO/DOS code pages, MacRoman,
MacTurkish, MacCyrillic, MacUkrainian, MacGreek, MacIcelandic, Mac Central
European, Mac Romanian, Mac Croatian, Mac Thai, MacDingbats, CJK encodings,
declared HTML/XML charset sniffing, Unicode normalization, display-width
wrapping, emoji/grapheme handling, or Unicode GLOB/collation behavior. It only
adds the distinct local Tcl Symbol table and proves WordPress handoff metadata.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
single-byte decoder, Markdown byte-source handoff, WordPress block writer,
display-width accounting, local Tcl encoding table source truth, and focused PHP
test harness. No Pandoc, Cabal, Haskell runner, Word, LibreOffice, external
charset converter, browser renderer, online service, live provider, or
live-service provider test was executed.

## Follow-Up

A future non-overlapping charset slice can cover Mac Japanese or a narrower
upstream-runner dependency audit. Full upstream Pandoc runner parity remains a
separate Cabal/Haskell dependency gate.

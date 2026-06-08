# Pandoc Charset Unicode Width Current Base - Mac Greek

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T195004Z`

Accepted base: `e33874b6a59046b0ea8a8d0d93a0e5bb2e4b1b0b`

## Scope

- Added bounded native `mac-greek` / `x-mac-greek` byte decoding in `UnicodeText`.
- Source truth for the high-half byte table was the local static Tcl encoding table at `/usr/share/tcl9.0/encoding/macGreek.enc`, following the existing Mac Cyrillic table approach.
- Added MarkdownReader and WordPressBlockWriter handoff coverage for Greek source bytes, smart punctuation, tonos/precomposed Greek letters, symbols, and the Mac Greek private-use Apple glyph byte.
- Updated the WordPress charset Unicode handoff smoke with a Mac Greek audit row and display-width `34/48` default/wide evidence.

## Red-First Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 922 assertions, 0 failures`.
- Red-first with only the new test: same command failed with `1 test files, 923 assertions, 1 failures`; `x-mac-greek` decoded as `utf-8-repaired`.
- Final: same command passed with `1 test files, 935 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test` passed with `charset unicode handoff self-test ok`.

## Dependency Closure

No new support component is needed. The slice reuses `UnicodeText` single-byte decoding, `MarkdownReader` source encoding metadata, `WordPressBlockWriter`, and the existing charset Unicode handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, external charset converter, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat the accepted ISO-8859-7 Greek or Windows-1253 Greek slices: those cover ISO/Windows Greek byte pages and undefined/control-byte behavior. This slice owns the classic Mac Greek byte page and its Mac-specific symbol/private-use mappings.

## Next

Choose another non-overlapping legacy charset or Unicode display-width edge that feeds Markdown/WordPress handoff coverage without invoking external converters or upstream Pandoc runners.

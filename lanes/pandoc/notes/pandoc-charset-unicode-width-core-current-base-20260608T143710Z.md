# Pandoc Charset/Unicode Width Current-Base Slice

## Behavior

- Added bounded native Mac Cyrillic byte decoding to `UnicodeText`.
- Normalized `mac-cyrillic`, `x-mac-cyrillic`, `mac-ukrainian`, and `x-mac-ukrainian` labels to `mac-cyrillic`.
- Routed decoded Mac Cyrillic Markdown through `MarkdownReader` and `WordPressBlockWriter` so source-encoding metadata and default/wide display-width audit rows survive the WordPress handoff.

## Source Truth

- Used the static Mac Cyrillic byte table from `/usr/share/tcl9.0/encoding/macCyrillic.enc` as local source truth for 0x80-0xff mappings.
- Did not run Pandoc, Cabal solver/build/test commands, Haskell runners, external charset converters, browser renderers, online services, live provider tests, or live-service provider tests.

## Evidence

- Baseline focused lane test before the patch: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` -> `1 test files, 868 assertions, 0 failures`.
- Red-first probe before implementation: `UnicodeText::decodeBytes(..., 'x-mac-cyrillic')` fell back to `utf-8-repaired` with `3` repairs.
- Final focused lane test: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` -> `1 test files, 881 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test` -> `charset unicode handoff self-test ok`.

## Dependency Closure

- No new support component is needed. This slice reuses `UnicodeText`, `MarkdownReader`, and `WordPressBlockWriter`.
- Full upstream runner parity remains out of scope for this isolated micro-slice because the lane contract forbids shelling out to Pandoc, Cabal/Haskell runners, or external converters.

## Non-Overlap

- This does not repeat the accepted BOM declaration preflight, Unicode Prepend control clustering, I Ching/counting-symbol width, Tibetan tsheg wrapping, Windows-1251, KOI8-R/U, ISO-8859-5, IBM437/866, or MacRoman charset rows.
- The new case is specifically Mac Cyrillic/Mac Ukrainian byte decoding plus WordPress charset/display-width handoff.

## Next

- A useful follow-up would map another legacy charset family or a bounded Unicode repair diagnostic with the same native PHP and WordPress handoff discipline.

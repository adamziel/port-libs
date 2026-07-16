# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T154911Z`

Base accepted HEAD: `2069ed7e1febba5c2afce1b99c380343613b723c`

## Behavior Added

- Extended `UnicodeText::decodeBytes()` to recognize bounded Shift_JIS /
  Windows-31J labels: `shift-jis`, `shift_jis`, `sjis`, `csshiftjis`,
  `ms932`, `ms_kanji`, `windows-31j`, `x-sjis`, and `cp932`.
- Added a native PHP Shift_JIS decoder for ASCII, halfwidth katakana, and the
  focused JIS0208 pointers needed by a Japanese WordPress review packet:
  `計画`, `本文と半角ｶﾀｶﾅ、丸①波～髙崎。`
- Preserved the decoder repair boundary where a malformed lead byte followed
  by ASCII emits U+FFFD and then keeps the ASCII byte visible, so a quote or
  tag delimiter cannot be swallowed into the bad multibyte sequence.
- Extended the WordPress charset handoff smoke with a `Shift_JIS source` audit
  row carrying decoded text, canonical source encoding, and narrow/wide
  display-width accounting.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Earlier accepted charset slices already covered BOM
precedence for UTF-8/UTF-16/UTF-32, Windows-1252, ISO-8859-1, ISO-8859-15 /
Latin-9, MacRoman, malformed UTF-8 repair, line-ending normalization, Unicode
normalization, display-width breakpoint splitting, display-column wrapping,
emoji presentation width, keycap/regional/tag emoji sequence width, emoji
skin-tone modifier width, emoji ZWJ variation width, supplementary/rare East
Asian wide scripts and symbols, BMP/geometric emoji symbols, decomposed Hangul
Jamo width, Indic/Thai/Lao grapheme handling, default-ignorable control width
accounting, East Asian ambiguous-width policy, Unicode soft-break wrapping,
Unicode separator wrapping, and prepended format-control zero-width accounting.

The bounded source truth is the WHATWG Encoding Standard Shift_JIS decoder and
its published `index-jis0208.txt` table. The PHP port follows the pointer
calculation and ASCII-restore repair behavior for the focused fixture bytes:

- https://encoding.spec.whatwg.org/#shift_jis-decoder
- https://encoding.spec.whatwg.org/index-jis0208.txt

This is intentionally not a full generated JIS table or a general WHATWG
Encoding implementation. It ports the smallest Japanese byte-decoding cluster
needed by native Pandoc-like Markdown reader handoff and WordPress review
packets.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 336 assertions, 0 failures`

Red-first focused check after adding the Shift_JIS test and before the
implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 337 assertions, 1 failures`
  - Failure: the new `windows-31j` case expected canonical encoding
    `shift_jis`, but `UnicodeText::decodeBytes()` returned `utf-8-repaired`.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 349 assertions, 0 failures`
  - Delta: `+13` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Additional verification:

- PHP lint for changed PHP files.
  - `php -l lanes/pandoc/src/UnicodeText.php`
    - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/UnicodeTextTest.php`
    - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
    - Result: no syntax errors.
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
  - Result: both Pandoc JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`.
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses the current Markdown reader, Markdown writer,
and WordPress charset handoff example. It does not invoke Pandoc, Cabal,
Haskell test binaries, external charset converters, citeproc, BibTeX, Biber,
Word, LibreOffice, `zip`, `unzip`, `tar`, `lz4`, external template engines,
TeX/PDF engines, browser renderers, roff, Typst, MathJax, KaTeX, terminal
probes, online sanitizers, or online conversion services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, UTF-8/UTF-16/UTF-32 BOM handling, Windows-1252,
ISO-8859-1, ISO-8859-15 / Latin-9, MacRoman, malformed UTF-8 repair, Unicode
normalization, display-width breakpoint splitting, display-column wrapping,
emoji presentation width, keycap/regional/tag emoji sequence width, emoji
skin-tone modifier width, emoji ZWJ variation width, supplementary/rare East
Asian wide scripts and symbols, BMP/geometric emoji symbols, decomposed Hangul
Jamo width, Indic/Thai/Lao grapheme handling, default-ignorable control width
accounting, East Asian ambiguous-width policy, Unicode soft-break wrapping,
Unicode separator wrapping, prepended format-control zero-width accounting, or
upstream-runner dependency audit work.

It only extends the charset/Unicode primitive with bounded Shift_JIS /
Windows-31J byte decoding for native text handoff.

## Follow-Up

Keep full generated Shift_JIS/JIS0208 tables, EUC-JP and ISO-2022-JP labels,
full WHATWG Encoding label coverage, declared HTML/XML charset sniffing,
terminal-profile-specific emoji width variants, broader Unicode table
refreshes, and full upstream Haskell runner parity as separate bounded slices.

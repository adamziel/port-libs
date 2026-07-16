# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T133352Z`

Base accepted HEAD: `71697cd932f3f46a05c27d723511ed0f8940bc1c`

## Behavior Added

- Extended the bounded native East Asian wide table in `UnicodeText` for:
  - Tangut ideographs: U+17000..U+187F7;
  - Tangut Components: U+18800..U+18AFF;
  - Khitan Small Script: U+18B00..U+18CFF;
  - Tangut Supplement: U+18D00..U+18D8F.
- Verified the ranges through `displayWidth()`, `splitAtDisplayWidth()`,
  `splitByDisplayBreakpoints()`, `padDisplay()`, and `wrapByDisplayWidth()`.
- Extended the WordPress charset handoff smoke with a `Rare CJK scripts` audit
  row so review tables expose the same two-column accounting.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Earlier accepted charset slices already covered
BOM precedence, UTF-16, Windows-1252, ISO-8859-1, ISO-8859-15 / Latin-9,
MacRoman, malformed UTF-8 repair, line-ending normalization, Unicode
normalization, display-width breakpoint splitting, display-column wrapping,
emoji presentation width, keycap/regional/tag emoji sequence width, emoji
skin-tone modifier width, emoji ZWJ variation width, supplementary East Asian
wide symbols, BMP emoji-width symbols, geometric emoji symbols, East Asian
ambiguous-width policy, decomposed Hangul Jamo width, Indic/Thai/Lao grapheme
handling, default-ignorable control accounting, Unicode soft-break wrapping,
Unicode separator wrapping, and prepended format-control zero-width accounting.

The bounded upstream-facing behavior is display-column accounting before native
Pandoc-like writers lay out tables or review packets. This patch does not add a
generated Unicode database or terminal-profile-specific width policy; it fills
one missing East Asian wide cluster in the existing native PHP width table.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 300 assertions, 0 failures`

Red-first focused check after adding the rare-script test and before the
implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 301 assertions, 1 failures`
  - Failure: the new Tangut case expected display width `6`, but
    `UnicodeText::displayWidth()` returned `3`.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 311 assertions, 0 failures`
  - Delta: `+11` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both Pandoc JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses the current Markdown writer and WordPress
charset handoff example. It does not invoke Pandoc, Cabal, Haskell test
binaries, citeproc, BibTeX, Biber, Word, LibreOffice, `zip`, `unzip`, `tar`,
`lz4`, external charset converters, external template engines, TeX/PDF
engines, browser renderers, roff, Typst, MathJax, KaTeX, terminal probes,
online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, line-ending normalization, BOM precedence, UTF-16,
Windows-1252, ISO-8859-1, ISO-8859-15 / Latin-9, MacRoman, malformed UTF-8
repair, Unicode normalization, display-width breakpoint splitting,
display-column wrapping, emoji presentation width, keycap/regional/tag emoji
sequence width, emoji skin-tone modifier width, emoji ZWJ variation width,
supplementary East Asian wide symbols already covered by the 16FE0/1B000/1F200
case, BMP emoji-width symbols, geometric emoji symbols, decomposed Hangul Jamo
width, Indic/Thai/Lao grapheme handling, default-ignorable control width
accounting, East Asian ambiguous-width policy, Unicode soft-break wrapping,
Unicode separator wrapping, prepended format-control zero-width accounting, or
upstream-runner dependency audit work.

It only extends the charset/Unicode primitive with bounded Tangut/Khitan East
Asian wide display accounting.

## Follow-Up

Keep generated Unicode EastAsianWidth table refreshes, full grapheme-break
property parity, terminal-profile-specific emoji width variants, HTML/XML
charset sniffing, ISO-2022/Shift_JIS/EUC-JP labels, and full upstream Haskell
runner parity as separate bounded slices.

# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T185902Z`

Base accepted HEAD: `4e4dadda554ebde678816b2f0359edfa9505904d`

## Behavior Added

- Extended `UnicodeText::decodeBytes()` to recognize bounded Central European
  single-byte labels:
  - `windows-1250`, `cp1250`, and `microsoft-cp1250`;
  - `iso-8859-2`, `latin-2`, `latin2`, `l2`, `iso-ir-101`,
    `csisolatin2`, and `iso-8859-2:1987`.
- Added native PHP byte-to-codepoint replacement maps for the non-identity
  Windows-1250 and ISO-8859-2 bytes needed by Polish, Czech, and Hungarian
  reviewer packets before Markdown parsing.
- Preserved source encoding metadata and display-width accounting through
  `MarkdownReader` and `WordPressBlockWriter`.
- Extended the WordPress charset handoff smoke with `Windows-1250 source` and
  `Latin-2 source` audit rows carrying decoded text, canonical source encoding,
  and display width.

## Source Truth

The bounded source truth is the WHATWG Encoding Standard single-byte decoder
model plus its current `index-windows-1250.txt` and `index-iso-8859-2.txt`
tables:

- https://encoding.spec.whatwg.org/#single-byte-decoder
- https://encoding.spec.whatwg.org/index-windows-1250.txt
- https://encoding.spec.whatwg.org/index-iso-8859-2.txt

This slice intentionally implements only the bounded in-lane rows needed by the
focused fixtures. It does not add GBK, Big5, EUC-KR, full generated WHATWG
index ingestion, or HTML/XML charset sniffing.

No current-base Pandoc rework note was present. The pinned Pandoc checkout was
not present under `/home/claude/port-libs/.upstream-cache/pandoc`, matching the
existing upstream-runner blocker. No Pandoc, Cabal solver/build, Haskell
runner, external charset converter, browser renderer, online sanitizer, or
online service was executed.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 378 assertions, 0 failures`

Red-first probe before implementation:

- `UnicodeText::decodeBytes($bytes, 'windows-1250')`
  - Result: `utf-8-repaired`, `9` repairs, replacement characters in the
    decoded Central European text.
- `UnicodeText::decodeBytes($bytes, 'iso-8859-2')`
  - Result: `utf-8-repaired`, `9` repairs, replacement characters in the
    decoded Central European text.

Post-implementation focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 394 assertions, 0 failures`
  - Delta: `+16` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Additional verification:

- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses `MarkdownReader`, `WordPressBlockWriter`, the
WordPress charset handoff example, and the focused PHP test harness. Full
upstream runner parity remains gated on a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` and locally buildable Haskell Tasty
executables for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, UTF-8/UTF-16/UTF-32 BOM handling, Windows-1252,
ISO-8859-1, ISO-8859-15 / Latin-9, MacRoman, malformed UTF-8 repair,
Shift_JIS/Windows-31J decoding, EUC-JP decoding, ISO-2022-JP decoding,
Unicode normalization, display-width breakpoint splitting, display-column
wrapping, emoji presentation width, keycap/regional/tag emoji sequence width,
emoji skin-tone modifier width, emoji ZWJ variation width, supplementary/rare
East Asian wide scripts and symbols, BMP/geometric emoji symbols, decomposed
Hangul Jamo width, Indic/Thai/Lao grapheme handling, default-ignorable control
width accounting, East Asian ambiguous-width policy, Unicode soft-break
wrapping, Unicode separator wrapping, prepended format-control zero-width
accounting, or upstream-runner dependency audit work.

It only extends the charset/Unicode primitive with bounded Windows-1250 and
ISO-8859-2 byte decoding plus WordPress handoff evidence.

## Follow-Up

Keep GBK, Big5, EUC-KR, full generated WHATWG index ingestion, HTML/XML
charset sniffing, terminal-profile-specific emoji width variants, broader
Unicode width table refreshes, and full upstream Pandoc Haskell runner parity
as separate bounded slices.

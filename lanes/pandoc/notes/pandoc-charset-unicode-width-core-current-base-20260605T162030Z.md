# Pandoc Charset/Unicode Width Core Slice - 2026-06-05T16:20:30Z

## Scope

Added bounded native EUC-JP byte decoding to `UnicodeText::decodeBytes()` and
the Markdown/WordPress handoff path. The implementation recognizes EUC-JP
labels, decodes ASCII, halfwidth katakana, and the existing bounded JIS0208
pointer subset used by the current Japanese review-packet fixtures, and reports
malformed EUC-JP lead/trail byte repairs.

This does not shell out to Pandoc, Haskell runners, `iconv`, `mb_convert_*`,
online services, or external charset converters.

## Source Truth

- WHATWG Encoding Standard EUC-JP labels and decoder:
  https://encoding.spec.whatwg.org/#names-and-labels
  https://encoding.spec.whatwg.org/#euc-jp-decoder
- WHATWG `index-jis0208` pointer model, reused through the existing bounded
  PHP pointer table already used by the Shift_JIS slice:
  https://encoding.spec.whatwg.org/index-jis0208.txt

The PHP port stays intentionally bounded; it does not add full generated
JIS0208/JIS0212 tables.

## Behavior

- `euc-jp`, `x-euc-jp`, `eucjp`, and `cseucpkdfmtjapanese` labels normalize to
  canonical source encoding `euc-jp`.
- EUC-JP two-byte JIS0208 sequences decode through the existing bounded pointer
  table for Japanese heading/body review text.
- EUC-JP `0x8E` halfwidth katakana sequences decode to U+FF61..U+FF9F.
- Malformed EUC-JP halfwidth and JIS0208 sequences emit U+FFFD and increment
  repair counts while preserving following ASCII bytes.
- The WordPress charset handoff example now includes an `EUC-JP source` audit
  row with decoded text and narrow/wide display widths.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 349 assertions, 0 failures`

Focused implementation check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - First result after adding the EUC-JP case: `1 test files, 352 assertions,
    1 failures`
  - Failure: fixture bytes omitted the `本` JIS0208 pointer, so the expected
    text was corrected to match the intended source bytes.
  - Final result: `1 test files, 364 assertions, 0 failures`
  - Delta: `+15` focused assertions and `+1` focused PHP PASS case.

Example smoke:

- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses `MarkdownReader`, `MarkdownWriter`, `AstNode`,
and `WordPressBlockWriter`.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, UTF-8/UTF-16/UTF-32 BOM handling, Windows-1252,
ISO-8859-1, ISO-8859-15 / Latin-9, MacRoman, malformed UTF-8 repair,
Shift_JIS/Windows-31J decoding, Unicode normalization, display-width
breakpoint splitting, display-column wrapping, emoji presentation width,
keycap/regional/tag emoji sequence width, emoji skin-tone modifier width,
emoji ZWJ variation width, supplementary/rare East Asian wide scripts and
symbols, BMP/geometric emoji symbols, decomposed Hangul Jamo width,
Indic/Thai/Lao grapheme handling, default-ignorable control width accounting,
East Asian ambiguous-width policy, Unicode soft-break wrapping, Unicode
separator wrapping, prepended format-control zero-width accounting, or
upstream-runner dependency audit work.

It only extends the charset/Unicode primitive with bounded EUC-JP byte
decoding and WordPress handoff evidence.

## Follow-Up

Keep full generated JIS0208/JIS0212 tables, ISO-2022-JP labels, HTML/XML
charset sniffing, terminal-profile-specific width variants, and full upstream
Pandoc Haskell runner parity as separate bounded slices.

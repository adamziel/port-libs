# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260606T035818Z`

Base accepted HEAD: `eef886d3b354b3a29bf86d4402b4f49fa968ab13`

## Behavior Added

- Split Windows-949 / CP949 / UHC labels from true EUC-KR labels in
  `UnicodeText::decodeBytes()` source metadata.
- Added a bounded native PHP CP949 extension-pair map for legacy Korean byte
  sources: `0x8141`, `0x8142`, `0x8143`, `0x8151`, `0x8152`, `0x81A1`, and
  `0x81A2`.
- Preserved true EUC-KR behavior by continuing to repair those UHC extension
  pairs to U+FFFD when the caller explicitly requests `euc-kr` / KS C 5601.
- Extended the WordPress charset handoff smoke with a `Windows-949 UHC source`
  audit row carrying decoded text, canonical source encoding, and display-width
  accounting.

## Source Truth

The bounded source truth is the Unicode official Microsoft CP949 mapping table:
`https://www.unicode.org/Public/MAPPINGS/VENDORS/MICSFT/WINDOWS/CP949.TXT`.
The selected rows map:

- `0x8141 -> U+AC02`
- `0x8142 -> U+AC03`
- `0x8143 -> U+AC05`
- `0x8151 -> U+AC26`
- `0x8152 -> U+AC27`
- `0x81A1 -> U+AC7E`
- `0x81A2 -> U+AC7F`

No local Pandoc upstream checkout was present under
`/home/claude/port-libs/.upstream-cache/pandoc`, so this slice used the
official Unicode mapping table for the bounded byte-mapping source truth.

No current-base Pandoc rework note was present. No Pandoc, Cabal solver/build/
test command, Haskell runner, external charset converter, browser renderer,
online sanitizer, online service, or live provider test was executed.

## Red-First Evidence

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 506 assertions, 0 failures`

Red-first focused check after adding the Windows-949/UHC test and before the
implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 507 assertions, 1 failures`
  - Failure: `UnicodeText::decodeBytes(..., 'cp949')` returned canonical
    `euc-kr` instead of `windows-949`.

After the implementation, the first focused run exposed a fixture expectation
mistake for `0x81A1` and `0x81A2`; the Unicode CP949 table maps them to
U+AC7E and U+AC7F, rendered as `걾걿`, not the initially expected syllables.
The fixture expectation was corrected to match the source table.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 521 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+15` assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Additional verification commands were run after metadata updates and are
recorded in the final handoff response.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice extends the existing native PHP
`UnicodeText` helper and reuses `MarkdownReader`, `MarkdownWriter`,
`WordPressBlockWriter`, `AstNode`, the WordPress charset handoff example, and
the focused PHP test harness. Full generated CP949/WHATWG index ingestion,
HTML/XML charset sniffing, terminal-profile-specific emoji width variants,
broader Unicode width table refreshes, and full upstream Pandoc Haskell runner
parity remain separate bounded follow-up work.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, UTF-8/UTF-16/UTF-32 BOM handling, Windows-1252,
Windows-1250, Windows-1251, KOI8-R, ISO-8859-1, ISO-8859-2,
ISO-8859-15 / Latin-9, MacRoman, malformed UTF-8 repair, Shift_JIS/Windows-31J
decoding, EUC-JP decoding, ISO-2022-JP decoding, Big5, GBK, GB18030, bounded
EUC-KR KS X 1001 pairs, HZ-GB-2312, Unicode normalization, display-width
breakpoint splitting, display-column wrapping, emoji presentation width,
keycap/regional/tag emoji sequence width, emoji skin-tone modifier width,
emoji ZWJ variation width, supplementary/rare East Asian wide scripts and
symbols, BMP/geometric emoji symbols, decomposed Hangul Jamo width,
Indic/Thai/Lao grapheme handling, default-ignorable control width accounting,
East Asian ambiguous-width policy, Unicode soft-break wrapping, Unicode
separator wrapping, prepended format-control zero-width accounting, or
upstream-runner dependency audit work.

It only extends the charset/Unicode primitive with bounded Windows-949/UHC
Korean extension byte decoding and WordPress handoff evidence.

## Follow-Up

Keep full generated CP949/WHATWG index ingestion, HTML/XML parser charset
sniffing, terminal-profile-specific emoji width variants, broader Unicode width
table refreshes, and full upstream Pandoc Haskell runner parity as separate
bounded slices.

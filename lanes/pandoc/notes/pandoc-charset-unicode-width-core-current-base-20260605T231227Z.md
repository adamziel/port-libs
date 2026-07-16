# Pandoc Charset/Unicode Width Core - Windows-1251 Cyrillic Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T231227Z`
Base: `5d88bf01234ea0ed3d4e3dd6fcfdd802280f154c`

## Change

- Extended `UnicodeText::decodeBytes()` to recognize bounded Windows-1251
  labels including `windows-1251`, `cp1251`, `microsoft-cp1251`, `ms1251`,
  and `x-cp1251`.
- Added a native PHP CP1251 decoder for Cyrillic Markdown source bytes,
  including the Cyrillic contiguous range, Windows punctuation/control slots,
  the euro sign, `Ё/ё`, and numero sign metadata used by the focused review
  packet.
- Preserved canonical `windows-1251` source metadata through `MarkdownReader`
  and the WordPress block handoff.
- Kept undefined byte `0x98` visible as U+FFFD with a repair count rather than
  silently leaking a C1 control into imported text.
- Extended the WordPress charset handoff smoke with a `Windows-1251 source`
  audit row carrying decoded text, canonical source encoding, and display width.

## Source Truth

The bounded source truth is the Windows-1251 code page mapping for the Cyrillic
range and selected punctuation/control bytes needed by Cyrillic review packets.
This slice reuses the lane's existing native PHP charset handoff and does not
run Pandoc, Cabal, Haskell test binaries, external charset converters, browser
renderers, online sanitizers, online services, or live provider tests.

## Red-First Evidence

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 471 assertions, 0 failures`

Before implementation, the new CP1251 fixture would fall through to UTF-8
repair and return `utf-8-repaired` with replacement characters instead of
canonical `windows-1251` source metadata and decoded Cyrillic text.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 483 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+12` assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`UnicodeText`, `MarkdownReader`, `MarkdownWriter`, and `WordPressBlockWriter`
support path. KOI8-R/U, ISO-8859-5, broader Windows-1251 punctuation fixtures,
Windows-949 extension pairs, full GB18030 four-byte ranges, generated charset
index ingestion, declared HTML/XML charset sniffing, terminal-profile-specific
emoji width variants, and full upstream Pandoc Haskell runner parity remain
separate bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250, ISO-8859-1/2/15, MacRoman, Shift_JIS/Windows-31J, EUC-JP,
ISO-2022-JP, Big5, GBK, EUC-KR, HZ-GB-2312, Unicode normalization, emoji
presentation and tag/ZWJ clusters, supplementary East Asian wide ranges,
ambiguous-width policy, Unicode separator wrapping, default-ignorable controls,
prepended format-control zero-width accounting, Indic virama clusters,
Myanmar/Khmer conjuncts, Markdown/HTML reader behavior, table geometry,
DOCX/ODF/EPUB/PDF, syntax-highlighting, CSL/BibTeX, YAML, doctemplate, ZIP/OPC,
or upstream-runner dependency audit slices.

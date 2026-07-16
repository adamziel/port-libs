# Pandoc Charset/Unicode Width Core - Windows-1254 Turkish Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260607T085432Z`
Base: `63be7b48de2468cb308ecab366fd7ed1adc53468`

## Change

- Added bounded `windows-1254` / `cp1254` label recognition to `UnicodeText`,
  including `microsoft-cp1254`, `ms1254`, and `x-cp1254` aliases while
  preserving canonical source metadata as `windows-1254`.
- Added native single-byte decoding for Windows-1254 Turkish Markdown imports:
  Windows smart punctuation and Euro C1 controls, ISO-8859-9 Turkish printable
  letters, and pass-through Latin-1-compatible printable bytes.
- Kept undefined Windows-1254 C1 slots explicit as U+FFFD repairs before
  Markdown or WordPress handoff.
- Extended the WordPress charset handoff smoke with a Windows-1254 audit row
  carrying decoded text, canonical source encoding, and display-width evidence.

## Source Truth

The bounded source-truth contract is the Windows-1254 single-byte layout:
ASCII bytes pass through unchanged, defined C1 slots map to Windows smart
punctuation and related symbols, bytes `0xD0`, `0xDD`, `0xDE`, `0xF0`,
`0xFD`, and `0xFE` preserve Turkish `ĞİŞğış`, and undefined C1 byte slots
`0x81`, `0x8D`, `0x8E`, `0x8F`, `0x90`, `0x9D`, and `0x9E` become U+FFFD
repairs. This slice does not ingest generated charset indexes or use external
charset converters for progress.

No current-base Pandoc rework note was present. No Pandoc, Cabal solver/build/
test command, Haskell runner, external charset converter, browser renderer,
online sanitizer, online service, live provider test, or live-service provider
test was executed.

## Red-First Evidence

Baseline before adding the new focused case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 684 assertions, 0 failures`

After adding the Windows-1254 focused case and before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: failed in the new Windows-1254 case.
  - Failure: `UnicodeText::decodeBytes(..., 'cp1254')` fell through to UTF-8
    repair instead of returning canonical `windows-1254` decoded text.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 697 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+13` assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
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

No new support component is needed. This slice reuses native PHP `UnicodeText`,
`MarkdownReader`, `MarkdownWriter`, `WordPressBlockWriter`, the existing
WordPress charset handoff example, and the lane-local focused PHP harness.
Windows-1255/1256/1257/1258, declared HTML/XML charset sniffing, full generated
charset indexes, bidi layout shaping, terminal-profile-specific width variants,
and full upstream Pandoc Haskell runner parity remain separate bounded
follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250/1251/1253, ISO-8859-1/2/3/4/5/6/7/8/9/10/13/14/15/16,
TIS-620, MacRoman, KOI8-R/U, Shift_JIS/Windows-31J, EUC-JP, ISO-2022-JP,
Big5, GBK/GB18030, EUC-KR, HZ-GB-2312, Unicode normalization, emoji
presentation and tag/ZWJ clusters, supplementary/rare East Asian wide ranges,
BMP/geometric emoji symbols, ambiguous-width policy, Unicode soft-break
wrapping, Unicode separator wrapping, default-ignorable controls, prepended
format-control zero-width accounting, Indic virama clusters, Myanmar/Khmer
conjuncts, Thai/Lao Sara Am grapheme slicing, Markdown/HTML reader behavior,
XML/HTML5 DOM, table geometry, DOCX/ODF/EPUB/PDF, syntax-highlighting,
CSL/BibTeX, YAML, doctemplate, ZIP/OPC, or upstream-runner dependency audit
slices.

# Pandoc Charset/Unicode Width Core Current Base - 2026-06-09

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T061345Z`
Base: `ad25c5c67f0859a34d555620436625e00d668451`

## Behavior

- Added native Apple Mac Arabic single-byte decoding to `UnicodeText`.
- Canonical labels now include `mac-arabic`, `x-mac-arabic`, `macarabic`, `xmacarabic`, `arabic-mac`, and related aliases.
- The mapping follows the Unicode Consortium vendor mapping `Public/MAPPINGS/VENDORS/APPLE/ARABIC.TXT`, including Arabic letters, Persian letters, Arabic-Indic digits, Arabic punctuation, combining marks, and Mac Arabic symbols.
- Markdown source decoding now records `sourceEncoding.encoding = mac-arabic` and hands the decoded text to WordPress blocks without UTF-8 repair fallback.

## Evidence

Red-first check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
FAIL decodes mac arabic source bytes into wordpress blocks
Expected: 'mac-arabic'
Actual: 'utf-8-repaired'
1 test files, 1526 assertions, 1 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1537 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test
charset unicode handoff self-test ok
```

Focused delta: +1 PHP PASS case and +12 focused assertions over the 1,525 assertion baseline.

## Non-Overlap

This slice is limited to Mac Arabic byte decoding and display-width handoff. It does not repeat accepted UTF BOM, UTF-16/UTF-32, ISO-8859 Arabic/Hebrew/Greek/Cyrillic, Windows-125x, DOS/IBM, KOI8, Mac Roman/Symbol/Dingbats/Croatian/Thai/Turkish/Icelandic/Romanian/Central-European/Cyrillic/Ukrainian/Greek, CJK multibyte, normalization, grapheme, emoji, line-break, or table-width behavior already covered in `UnicodeTextTest.php`.

## Dependency Closure

No new native support component is needed. The patch reuses the existing `UnicodeText` single-byte decoder, `MarkdownReader` source-encoding metadata, `WordPressBlockWriter`, and display-width accounting. Mac Hebrew decoding and broader bidi-layout metadata remain bounded follow-up charset slices; this patch does not attempt visual bidi shaping or external conversion.

Root harness: not run - isolated micro-slice.

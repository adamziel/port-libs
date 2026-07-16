# Pandoc Charset/Unicode Width Core Current Base Duplicate - 2026-06-09

Slice: `pandoc-charset-unicode-width-core-current-base-duplicate-20260609T072210Z`
Base: `93c7fe92d8764429cde901a465ac3a9266aec0d4`

## Behavior

- Added native CP165 / DOS Arabic variant decoding to `UnicodeText`.
- Canonical labels now include `cp165`, `ibm165`, `dos165`, `x-cp165`, `xcp165`, and `csibm165`.
- The mapping follows the local static source truth in `/usr/share/tcl9.0/encoding/cp165.enc`: CP165-specific bytes such as `0x24`, `0x9B`, `0x9C`, `0x9F`, `0xA6`, `0xA7`, and `0xFF` override IBM864 while the shared Arabic presentation-form range continues to reuse the existing IBM864 table.
- Markdown source decoding now records `sourceEncoding.encoding = cp165` and hands decoded Arabic presentation forms, Arabic percent sign, lam-alef forms, and non-breaking space through WordPress blocks without UTF-8 repair fallback.

## Evidence

Focused baseline before adding the CP165 case:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1579 assertions, 0 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1593 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test
charset unicode handoff self-test ok
```

Focused delta: +1 PHP PASS case and +14 focused assertions over the 1,579 assertion baseline.

## Non-Overlap

This slice is limited to CP165 / DOS Arabic variant byte decoding and WordPress/display-width handoff. It does not repeat accepted UTF BOM, UTF-16/UTF-32, ISO-8859 Arabic/Hebrew/Greek/Cyrillic, Windows-125x, IBM864, other DOS/IBM code pages, KOI8, Mac Arabic or other Mac encodings, CJK multibyte, normalization, grapheme, emoji, line-break, or table-width behavior already covered in `UnicodeTextTest.php`.

## Dependency Closure

No new native support component is needed. The patch reuses the existing `UnicodeText` single-byte decoder, `MarkdownReader` source-encoding metadata, `WordPressBlockWriter`, and display-width accounting. Broader bidi visual layout and unrelated legacy Arabic encodings remain bounded follow-up charset slices; this patch does not invoke external converters or attempt visual bidi shaping.

Root harness: not run - isolated micro-slice.

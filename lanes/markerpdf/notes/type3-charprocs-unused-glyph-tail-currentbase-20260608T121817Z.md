# Type3 CharProcs Unused Glyph Tail Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T121817Z`

Base accepted HEAD: `10bcbb2d7091a3fcd80f2751345f7a527f5879e7`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to PDF parser/text
layers before OCR/model fallback. In this no-GPU PHP lane, Type3
`/CharProcs` are font-private glyph programs: selected `d0`/`d1` metrics can
drive WordPress text advance grouping, but CharProc payload streams must not
become visible fallback text.

The selected font Encoding determines which glyph names are relevant to text
shown on a page. A malformed reference tail on an unused CharProc dictionary
entry should not make selected glyph metrics unusable. Selected malformed
glyph entries still fail closed to the existing fallback behavior.

## Red Check

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsUnusedGlyphTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps valid Type3 CharProc widths when an unused glyph entry has a malformed tail on current base
Values are not identical
Expected: array (
  0 => 'WideBlock',
)
Actual: array (
  0 => 'Wide Block',
)
PASS keeps unused malformed Type3 CharProc glyph streams private during fallback extraction

1 test files, 8 assertions, 1 failures
```

That proved one malformed unused glyph entry poisoned the whole CharProcs map
and forced selected glyphs onto stale `/Widths` fallback spacing.

## Change

- `PdfTextExtractor::type3CharProcWidths()` now passes the Encoding-selected
  glyph names into Type3 CharProc reference resolution.
- `PdfTextExtractor::type3CharProcUnicodeMap()` uses the same selected glyph
  boundary for no-ToUnicode Type3 glyph-name Unicode fallback.
- `PdfTextExtractor::charProcObjectReferencesFromDictionary()` still tracks
  malformed reference tails per glyph name, but rejects the whole selected map
  only when a malformed tail belongs to a selected glyph.
- Fallback stream exclusion remains broader: every referenced glyph stream,
  including unused malformed-tail entries, stays private and cannot become a
  WordPress paragraph.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsUnusedGlyphTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps valid Type3 CharProc widths when an unused glyph entry has a malformed tail on current base
PASS keeps unused malformed Type3 CharProc glyph streams private during fallback extraction

1 test files, 14 assertions, 0 failures
```

Adjacent malformed-tail regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsUnusedGlyphTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGlyphTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateGlyphTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDirectDictionaryTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsIndirectDictionaryTailBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 64 assertions, 0 failures
```

Broad Type3/font sweep:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfFontType3.*CurrentBaseTest\.php|PdfFontSimpleType3.*CurrentBaseTest\.php|PdfFontCMapCidType3.*CurrentBaseTest\.php|PdfFontCidType3.*CurrentBaseTest\.php|PdfImageXObjectType3CharProc.*CurrentBaseTest\.php|PdfPageResourceDuplicateType3FontCurrentBaseTest\.php' | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 71 selected test files (root lock skipped)
71 test files, 1472 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-unused-glyph-tail-currentbase.php
```

Result: exits 0 and emits `used_charproc_widths_preserved=true`,
`unused_glyph_tail_ignored_for_widths=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`,
rendering only the Gutenberg paragraph `WideBlock`.

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfFontType3CharProcsUnusedGlyphTailBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfFontType3CharProcsUnusedGlyphTailBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-unused-glyph-tail-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-unused-glyph-tail-currentbase.php
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer,
object scanner, exact-generation object lookup, Type3 `/CharProcs` dictionary
parser, glyph-name Encoding map, fallback stream privacy inventory, text
advance grouping, focused PHP runner, and WordPress smoke harness. No Python,
PDFium, pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution, browser
service, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted malformed selected glyph-entry tail rejection,
duplicate same-glyph tail replacement, direct or indirect `/CharProcs`
dictionary tail rejection, array-wrapped CharProcs rejection, duplicate
top-level `/CharProcs` key precedence, indirect dictionary generation
selection, stream-object dictionary fallback exclusion, top-level/nested
dictionary parsing, comment-split references, Encoding generation/comment
parsing, duplicate Type3 font subtype selection, FontMatrix/width precedence,
D1 bbox operands, marked-content/graphics-state/path/text-object setup,
inline-image paint rejection, image XObject review, resource fallback
exclusion, CMap/CIDSet width behavior, xref repair, metadata, annotations,
forms, OCR/model execution, or supplied table/equation handoffs. The bounded
behavior is only malformed unused glyph-name entries inside the selected
Type3 `/CharProcs` dictionary before selected glyph width/Unicode maps and
fallback glyph-stream privacy.

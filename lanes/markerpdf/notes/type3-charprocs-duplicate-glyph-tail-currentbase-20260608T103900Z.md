# Type3 CharProcs Duplicate Glyph Tail Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T103900Z`

Base accepted HEAD: `84b4162fa47ccf352ed51e23acf414da0446c583`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to PDF parser/text layers before OCR/model fallback. In this no-GPU PHP lane, Type3 `/CharProcs` are glyph programs: selected `d0`/`d1` metrics can drive WordPress text advance grouping, but glyph program streams remain private and cannot become visible fallback text.

Duplicate keys are malformed PDF dictionaries, but the lane's current native parser consistently uses selected later top-level dictionary values where accepted behavior already depends on that current-value boundary. This slice applies the same selected-entry rule inside a Type3 `/CharProcs` dictionary for a duplicate glyph name: a later valid duplicate glyph entry can replace an earlier malformed same-glyph reference tail. The privacy inventory remains stricter and collects every referenced duplicate glyph stream so stale payloads stay hidden.

## Change

- `PdfTextExtractor::charProcObjectReferencesFromDictionary()` now tracks malformed glyph-reference tails per glyph name instead of immediately rejecting the whole dictionary.
- If a later valid duplicate glyph entry replaces the malformed same-glyph entry, width/Unicode lookup can use the selected valid reference.
- If any selected glyph entry still has a malformed tail, the existing fail-closed behavior remains.
- Lenient fallback-exclusion scans now keep duplicate glyph references as an inventory, not a selected glyph map, so stale duplicate glyph streams are also excluded from fallback visible text.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateGlyphTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS allows a later valid duplicate Type3 CharProc glyph entry to replace a malformed stale tail on current base
PASS keeps stale and current duplicate Type3 CharProc glyph streams private during fallback extraction

1 test files, 14 assertions, 0 failures
```

Adjacent Type3 malformed-tail and duplicate-key tests:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsGlyphTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDirectDictionaryTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsIndirectDictionaryTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 52 assertions, 0 failures
```

Broad Type3/font sweep:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfFontType3.*CurrentBaseTest\.php|PdfFontSimpleType3.*CurrentBaseTest\.php|PdfFontCMapCidType3.*CurrentBaseTest\.php|PdfFontCidType3.*CurrentBaseTest\.php|PdfImageXObjectType3CharProc.*CurrentBaseTest\.php' | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 68 selected test files (root lock skipped)
68 test files, 1420 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-duplicate-glyph-tail-currentbase.php
```

Result: exits 0 and emits `later_duplicate_glyph_width_selected=true`, `charproc_payload_visible_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, rendering only `WideBlock`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, exact object lookup, Type3 CharProcs dictionary parser, fallback stream exclusion inventory, text advance grouping path, focused PHP runner, and WordPress smoke harness. GPU/OCR/model execution, Python/PDFium runtime, raster rendering, and external PDF tools remain intentionally out of scope.

## Non-Overlap

This does not repeat accepted malformed selected glyph-entry tail rejection, direct or indirect `/CharProcs` dictionary tail rejection, duplicate top-level `/CharProcs` key precedence, indirect dictionary generation selection, stream-object dictionary fallback exclusion, top-level/nested dictionary parsing, comment-split references, encoding generation/comment parsing, duplicate Type3 font subtype selection, FontMatrix/width precedence, D1 bbox operands, marked-content/graphics-state/path/text-object setup, inline-image paint rejection, image XObject review, resource fallback exclusion, CMap/CIDSet width behavior, xref repair, metadata, annotations, forms, OCR/model execution, or supplied table/equation handoffs. The bounded behavior is only duplicate glyph-name entries inside the selected Type3 `/CharProcs` dictionary where an earlier malformed same-glyph reference tail is replaced by a later valid duplicate while every duplicate glyph stream remains private for fallback extraction.

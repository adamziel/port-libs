# markerPDF Type3 CharProcs duplicate-key boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260606T012848Z`

## Source Truth

The local upstream markerPDF clone is not available in this isolated worktree,
so this slice uses the pinned markerPDF manifest, accepted Type3 parser notes,
and native PDF dictionary semantics as source truth. In the no-GPU markerPDF
scope, searchable PDF text extraction must treat Type3 `/CharProcs` as glyph
program dictionaries and must not let stale duplicate dictionary keys drive
WordPress paragraph grouping. PDF parsers commonly overwrite earlier duplicate
dictionary keys with the later value; this slice applies that boundary only to
Type3 `/CharProcs` lookup.

## Red Check

Before the parser change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateKeyBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (0 => 'WideBlock', 1 => 'Thin Text')
Actual:   array (0 => 'Wide Block', 1 => 'ThVn TYxt')

1 test files, 1 assertions, 1 failures
```

That proved the accepted parser selected the first top-level `/CharProcs`
dictionary in a duplicate-key Type3 font dictionary. The stale first dictionary
split the wide word and mapped the thin text through stale glyph programs.

## Implementation

`PdfTextExtractor::charProcsDictionaryBody()` now reads the last top-level
`/CharProcs` value before resolving either an indirect dictionary reference or
a direct dictionary value. `type3CharProcsDictionaryReference()` uses the same
last-value boundary so fallback stream exclusion and dictionary generation
checks stay aligned.

The change is scoped to Type3 `/CharProcs`; global PDF name lookup behavior is
unchanged.

## Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateKeyBoundaryCurrentBaseTest.php
```

Result: `1 test files, 9 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsColorSpaceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsD1BBoxOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryStreamFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsEncodingCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsEncodingGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsExtGStateSoftMaskBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGraphicsStateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInitialOperatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBalanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentPointBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsOperandCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPathSetupBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternColorOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPreMetricOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTextStateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthPrecedenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcPrivateGlyphBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php
```

Result: `40 test files, 391 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-duplicate-key-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Thin Text`, with
`last_duplicate_charprocs_dictionary_selected=true`,
`stale_duplicate_charprocs_widths_excluded=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PDF dictionary
scanner, exact object lookup, Type3 `/CharProcs` parser, stream decoder, and
text-advance grouping path. No Python, PDFium, pypdfium2, Surya, Texify, Torch,
OCR, GPU/model execution, browser service, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted exact-generation `/CharProcs` dictionary lookup,
same-number CharProc stream generation selection, direct Type3 d0/d1 width
handling, stream-only CharProc fallback exclusion, nested `/CharProcs`
dictionary filtering, Type3 stream-dictionary rejection, Type3 resource
fallback exclusion, malformed filtered CharProcs, marked-content balance,
graphics-state balance, inline image boundaries, FontMatrix width vectors, or
Type3 glyph-name Unicode recovery. The new boundary is specifically duplicate
top-level `/CharProcs` dictionary keys inside the Type3 font dictionary.

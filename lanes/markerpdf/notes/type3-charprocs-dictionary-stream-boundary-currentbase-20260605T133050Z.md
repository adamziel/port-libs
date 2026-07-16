# markerPDF Type3 CharProcs dictionary stream boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T133050Z`

Accepted base: `f142d7b9b18cd05cbd5f51482c8462a8ab4294f0`

## Source-truth behavior

PDF Type3 `/CharProcs` is a glyph-name-to-glyph-program dictionary. The glyph
programs are stream objects, but the `/CharProcs` map itself must resolve to a
dictionary object. A malformed indirect `/CharProcs` stream object should not
let its stream dictionary become the glyph map, and its stream payload should
not be visible fallback text for WordPress import.

This no-GPU markerPDF slice keeps the native searchable-PDF parser behavior
bounded to PDF object parsing, Type3 font widths, and fallback stream exclusion.

## Red run before production change

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryStreamBoundaryCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects Type3 CharProcs stream dictionaries before WordPress text grouping on current base (lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryStreamBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Bad Path',
)
Actual: array (
  0 => 'BadPath',
)

1 test files, 1 assertions, 1 failures
```

That proved `PdfTextExtractor` trusted a fake glyph map stored in the malformed
`/CharProcs` stream dictionary and used the referenced wide CharProc instead
of the Type3 `/Widths` fallback.

## Implementation

`PdfTextExtractor` now:

- resolves indirect Type3 `/CharProcs` through the existing exact-generation
  object lookup;
- rejects the object as a glyph map when the resolved object is a stream;
- treats comments before a resolved object dictionary as non-structural
  whitespace when deciding whether that resolved object is a stream;
- tracks referenced `/CharProcs` dictionary object generations and excludes
  those stream payloads from fallback decoded-stream text extraction.

Valid direct and indirect `/CharProcs` dictionaries, glyph CharProc streams,
comment-split references, filtered glyph streams, generation-specific glyph
streams, Type3 CMap fallback, and resource fallback exclusion are preserved.

## Focused verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryStreamBoundaryCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS rejects Type3 CharProcs stream dictionaries before WordPress text grouping on current base

1 test files, 7 assertions, 0 failures
```

Adjacent Type3/font run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3*.php
```

Result:

```text
Focused test run: 31 selected test files (root lock skipped)
31 test files, 269 assertions, 0 failures
```

Broader Type3 plus extractor run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInitialOperatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcPrivateGlyphBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result:

```text
Focused test run: 21 selected test files (root lock skipped)
21 test files, 795 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-dictionary-stream-boundary-currentbase.php
```

Result emits:

```text
charprocs_stream_dictionary_rejected=true
fallback_widths_preserve_word_gap=true
charprocs_stream_payload_excluded=true
charproc_payload_visible_text_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Lint:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryStreamBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-dictionary-stream-boundary-currentbase.php
```

All reported no syntax errors.

## Dependency closure

No new support component is needed. The slice reuses the existing native PDF
object table, exact-generation reference lookup, stream-object detection,
Type3 font metrics, and decoded-stream fallback scanner. No Python, OCR,
Surya/Texify/Torch, pypdfium, or external PDF tool execution is involved.

## Non-overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling, glyph
stream generation selection, indirect `/CharProcs` dictionary generation
selection, comment-split references, nested/top-level dictionary parsing,
filtered CharProc decoding, pre-metric setup validation, Type3 CMap/CIDSet
fallback, image/subtype CharProc resource exclusion, or fallback exclusion for
valid glyph program streams. The new boundary is only malformed `/CharProcs`
stream objects as dictionary-map decoys plus their fallback payload exclusion.

Root harness: not run - isolated micro-slice.

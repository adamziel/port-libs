# markerPDF Type3 CharProcs width-vector boundary current base

Base accepted HEAD: `5771f733e9e3256de06e48cb643fff27796d43dd`

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T043233Z`

## Source truth

markerPDF upstream delegates native searchable-PDF text extraction to PDF text
facilities instead of OCR/model execution. In this no-GPU lane, the equivalent
native PHP behavior is to read Type3 `/CharProcs` as font programs, not visible
page text. Type3 `d0` and `d1` metric operators declare a `wx wy` width vector
in glyph space; the vector must be transformed through the Type3 `/FontMatrix`
before the parser estimates text advance for WordPress paragraph grouping.

## Red-first boundary

Before this source change, an accepted-base probe with a non-orthogonal Type3
`/FontMatrix [0.001 0 0.001 0.001 0 0]`, a wide `500 500 d0` CharProc, and a
thin `125 125 ... d1` CharProc produced:

```text
Expected lines: ['WideBlock', 'Thin Text']
Actual lines:   ['Wide Block', 'Thin Text']
```

That proved the native Type3 width path was using only `wx` and the horizontal
FontMatrix scale, so `wy` could not contribute to horizontal advance when the
FontMatrix sheared glyph Y into text X.

## Implementation

`PdfTextExtractor` now parses Type3 CharProc metric operators as full `wx wy`
vectors. The extractor transforms the vector through the Type3 FontMatrix and
feeds the transformed horizontal component back into the existing 1000-unit
font-width convention used by text grouping. CharProc payload text remains
excluded from visible page text, and unsupported/corrupt CharProc streams still
fail closed.

## Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 7 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcPrivateGlyphBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInitialOperatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsResourceSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 20 selected test files (root lock skipped)
20 test files, 790 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-width-vector-currentbase.php
```

The WordPress smoke emits `WideBlock` and `Thin Text`, with
`width_vector_fontmatrix_applied=true`,
`thin_width_boundary_preserved=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-width-vector-currentbase.php
```

All reported no syntax errors.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the native PDF object parser,
stream decoder, Type3 `/CharProcs` dictionary resolution, FontMatrix parser,
CMap/source-boundary mapping, and existing text-advance grouping pipeline.
No OCR, Surya, Texify, Torch, Streamlit/FastAPI model worker, external PDF
renderer, or live-service dependency was used.

## Non-overlap

This does not repeat accepted Type3 CharProc fallback exclusion, exact object
generation selection, exact indirect CharProcs dictionary generation,
nested-dictionary pruning, top-level lookup, subtype gating, scalar FontMatrix
normalization, filtered fail-closed behavior, private glyph fallback,
resource-subtype decoys, Type3 CMap/CIDSet spacing, color glyph resources,
or optional-content image XObject review. The new boundary is only full `wx wy`
CharProc width-vector handling through a non-orthogonal Type3 FontMatrix before
WordPress text grouping.

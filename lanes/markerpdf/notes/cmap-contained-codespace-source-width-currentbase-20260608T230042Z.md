# CMap Contained Codespace Source Width Current Base

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T230042Z`  
Base accepted HEAD: `d8ca989a03aa98e6028adc24e3edc39bb34ec9a6`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to PDF-native text
extraction before any OCR/model fallback. In this no-GPU PHP lane, Type0 CMap
decoding must preserve the same font-code to CID width relationship for
searchable PDFs: `/Encoding` code-space ranges identify valid source code
boundaries, `begincidrange` maps contiguous source codes to CIDs, and
descendant CIDFont `/W` entries drive text advance and WordPress grouping.

This slice covers a current-base edge where a redundant contained code-space
range, such as a singleton inside a larger same-width range, should not cause
source-width sequence ranking to give up and fall back to source-byte distance.
That fallback can insert a false word gap before a far sparse source code even
when the CID sequence and `/W` width say the glyphs are adjacent.

## Implementation

- `PdfTextExtractor::codeSpaceSequenceOffsetInCidRange()` now normalizes
  same-width code-space ranges by dropping ranges fully contained by another
  same-width range before choosing the fast sequence-rank path.
- `PdfTextExtractor::parseCidRanges()` keeps large CMap `begincidrange`
  entries lazy when `cidRange` metadata is already recorded, avoiding dense
  expansion of wide sparse source ranges just to preserve far-source width
  lookup.
- Added a focused Type0 fixture whose `/Encoding` CMap contains an enclosing
  three-byte code-space range and a redundant contained singleton range. The
  far singleton maps through the lazy CID range to CID `1016`, whose `/W`
  advance is intentionally thin.

## Verification

Focused new regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapContainedCodespaceSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses enclosing CMap codespace for contained source-width range ranking on current base
1 test files, 11 assertions, 0 failures
```

Adjacent CMap/source-width family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapContainedCodespaceSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMultiRangeSparseSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSparseOverflowCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSparseArrayBfrangeSourceWidthCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 430 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-contained-codespace-source-width-currentbase.php
```

The smoke exits `0` and emits `<p>ABCDE</p>`,
`contained_codespace_range_collapsed=true`,
`lazy_cid_range_far_source_ranked=true`,
`far_source_thin_width_preserved=true`,
`false_word_gap_excluded=true`,
`cmap_program_bytes_visible_text_excluded=true`,
`nul_bytes_excluded=true`, `executes_python_pdftext=false`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is required. This reuses the native PHP PDF tokenizer,
CMap parser, lazy CID range lookup, CIDFont width lookup, text grouping, lane
TestRunner harness, and WordPress smoke renderer. It does not launch Python
pdftext, OCR, Surya, Texify, Torch, GPU/model code, Streamlit/FastAPI model
workers, live services, or external PDF tools.

## Non-Overlap

This does not repeat accepted explicit-long-source fallback, repeated-zero
source-width fallback, multi-range sparse source-width ranking, sparse overflow
CID-range width lookup, sparse array `bfrange` source-width lookup, CMap
program-byte exclusion, malformed CMap filter boundaries, Type3 width
fallbacks, xref repair, metadata, annotations, forms, image filters,
OCR/model parity, or supplied table/equation handoffs. The bounded behavior is
contained same-width code-space range normalization for source-width sequence
ranking on a lazy CID range.

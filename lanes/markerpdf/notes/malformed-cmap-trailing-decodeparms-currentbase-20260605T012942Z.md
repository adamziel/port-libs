# markerPDF malformed CMap trailing DecodeParms boundary current-base

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T012942Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, with low-level ToUnicode CMap stream decoding delegated to pdftext/PDFium before WordPress-visible Markdown is assembled.

The native no-GPU PHP lane owns the parser boundary before that model-free text import path. A ToUnicode CMap stream with a valid `/Filter` but malformed `/DecodeParms` array entries that are not applied to any real filter must still fail closed before compressed CMap mappings can replace fallback source text.

## Behavior

`PdfTextExtractor::decodeStream()` now rejects malformed, unapplied `/DecodeParms` entries before applying a stream filter stack. `extractCMapStreamFilterLengthOwnerReview()` counts those trailing malformed entries through the existing `invalid_decodeparms_parameter_count` and `decodeparms_operand_policy=reject_malformed_decodeparms_parameters` metadata.

The focused fixture uses `/Filter /FlateDecode` and `/DecodeParms [ null << /Predictor /Twelve /Columns 1 >> ]`. Before the fix, the decoder ignored the trailing malformed dictionary, decoded the compressed CMap, and visible text became `Trailing DecodeParms CMap Leak...`. After the fix, the CMap fails closed, fallback `/Identity-H` text remains visible, and the malformed CMap payload text stays out of WordPress paragraphs.

## Evidence

Red probe before source repair:

```text
text="DecodeParms CMap LeakecodeParms Safe Import"
decoded_cmap_count=1
invalid_decodeparms_parameter_count=0
decodeparms_operand_policy="decodeparms_resolved"
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed CMap Filter array operands before current-base text extraction
PASS classifies literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect CMap Filter arrays with dictionary operands before current-base text extraction
PASS rejects current-generation indirect CMap Filter dictionaries instead of stale valid filters
PASS rejects current-generation malformed CMap DecodeParms parameters before ToUnicode decoding
PASS rejects trailing malformed CMap DecodeParms array entries before ToUnicode decoding
PASS classifies stale-generation CMap Filter references by the current xref-selected malformed owner

1 test files, 388 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
fallback_text="Safe Import | Literal Safe Import | Indirect Literal Safe Import | Indirect Array Safe Import | Generation Safe Import | DecodeParms Safe Import | Trailing DecodeParms Safe Import | Stale Reference Safe Import"
trailing_decodeparms_operand_policy="reject_malformed_decodeparms_parameters"
trailing_decodeparms_invalid_parameter_count=1
trailing_decodeparms_unmatched_parameter_rejected=true
leaking_cmap_text_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted direct dictionary/literal malformed CMap `/Filter` boundary, selected indirect non-name CMap `/Filter` operand classification, current-generation dictionary filter selection, stale-generation filter reference classification, current xref-selected scalar malformed CMap `/DecodeParms` parameters, CMap `/Filter` and `/Length` owner review, generic stream DecodeParms owner repair, stream-filter stack fail-closed behavior, CMap width grouping, malformed ToUnicode fallback, or encrypted-PDF preflight.

The bounded behavior here is specifically trailing/unapplied malformed CMap `/DecodeParms` array entries before ToUnicode CMap decoding and WordPress paragraph extraction.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream filter resolver, DecodeParms validator, ToUnicode CMap parser, and WordPress smoke renderer. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.

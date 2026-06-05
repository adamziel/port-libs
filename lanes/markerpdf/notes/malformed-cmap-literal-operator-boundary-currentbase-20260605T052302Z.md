# markerPDF malformed CMap literal-operator boundary current-base

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T052302Z`
Base: `4e31699cbe0c41186d30963bf2eb08a46d45bd56`

## Source truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF text/CMap stream decoding to pdftext/PDFium before marker block conversion.
- Relevant parser behavior: CMap block operators are PDF/PostScript tokens. Names such as `beginbfchar` and `endbfchar` inside decoded literal strings, hex strings, arrays, dictionaries, or comments are data, not executable CMap rows, and must not override the real ToUnicode mapping before WordPress paragraph extraction.
- Primary references used for this slice:
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
  - https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_cmap.py

## Behavior

`PdfTextExtractor::cMapOperatorBlocks()` now scans decoded CMap programs token-by-token before recognizing `beginbfchar`, `endbfchar`, `beginbfrange`, `endbfrange`, and codespace operators. The scanner skips PDF comments, literal strings, hex strings, arrays, and dictionaries, preserving declared row-count handling while preventing literal-string operator decoys from becoming active CMap blocks.

The focused fixture uses a valid `/Filter /FlateDecode` ToUnicode CMap whose real row maps `<0001>` to `Literal Operator Safe Import`. The same decoded CMap contains a literal string with a fake `beginbfchar` block mapping `<0001>` to `Literal Operator CMap Leak`. Before the fix, the regex block parser read the literal-string decoy and visible text leaked the decoy mapping. After the fix, WordPress text uses the real CMap row and the literal-string decoy remains data only.

## Red-first evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
FAIL ignores CMap block operators inside decoded literal strings before current-base text extraction
Expected: ['Literal Operator Safe Import']
Actual: ['Literal Operator CMap Leak']
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
1 test files, 659 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-literal-operator-boundary-currentbase.php
```

The smoke emits `literal_operator_decoy_excluded=true`, `safe_import_text_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Full root harness not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted malformed CMap dictionary/literal `/Filter` operand rejection, selected indirect filter operand review, current-generation stale filter selection, malformed DecodeParms parameter rejection, trailing DecodeParms fail-closed behavior, null-filter DecodeParms slot handling, inherited `/UseCMap` DecodeParms review, post-`endcmap` parser bounding, second CMap program exclusion, unsupported/identity `/Crypt` CMap filter behavior, generic stream-filter stack boundaries, CMap source-width grouping, or terminal character-spacing advance boundaries.

The bounded behavior is specifically token-aware CMap operator block discovery inside decoded filtered CMap streams.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, Flate stream decoder, ToUnicode CMap parser, content text extraction, review metadata path, and WordPress smoke renderer. Live pdftext/PDFium execution, Surya/Torch OCR/layout models, Texify, tabled-pdf model inference, Streamlit/FastAPI workers, benchmark downloads, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF direction; none were executed.

# markerPDF FontDescriptor Indirect Flags Edge

Session: `port-dev-markerpdf-fontdesc6-20260602T0823Z`
Micro-slice: `markerpdf-font-descriptor-flags-edge-current-base-20260602T0823Z`
Base accepted HEAD: `994b7490311940ee81997008ba6fc8aee609e316`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` converts pdftext dictionary spans into Marker spans with `font=f"{s['font']['name']}_{font_flags_decomposer(s['font']['flags'])}"` and `font_weight=s["font"]["weight"]`. `marker/pdf/utils.py::font_flags_decomposer` maps the PDF font descriptor bits in order: fixed-pitch, serif, symbolic, script, non-symbolic, italic, caps, bold, and use-external-attributes.

This native slice keeps that upstream boundary but handles a PDF parser edge before the reduced PHP `pdftext`-style span is created: `/FontDescriptor` dictionary fields themselves may be indirect objects. The PHP parser already resolved an indirect descriptor dictionary; it now also resolves object-valued `/FontName`, `/Flags`, and `/FontWeight` values inside that dictionary.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/utils.py

## Native Behavior Added

`PdfTextExtractor::fontDescriptorInfo()` now dereferences:

- `/FontName 4 0 R` name objects before choosing the styled span font name.
- `/Flags 5 0 R` integer objects before passing flags through `PdfTextBlockConverter::fontFlagsDecomposer()`.
- `/FontWeight 6 0 R` numeric objects before `FontStyleCleaner` applies weight-driven bold cleanup.

The focused fixture proves the old failure mode: before the source fix, `/Flags 5 0 R` was read as integer `5`, producing `FallbackSerif_fixed_pitch_symbolic` instead of `IndirectSerifItalic_serif_non_symbolic_italic`. After the fix, the same synthetic PDF emits indirect italic and ForceBold descriptor spans, then renders the Gutenberg paragraph `Plain *italic segment* bridge **bold segment** outro`.

## Evidence

Red-first focused failure before the source fix:

```text
FAIL resolves indirect FontDescriptor flag fields before WordPress styled span cleanup (lanes/markerpdf/tests/PdfTextExtractorTest.php)
Values are not identical
Expected: 'IndirectSerifItalic_serif_non_symbolic_italic'
Actual: 'FallbackSerif_fixed_pitch_symbolic'

1 test files, 335 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 341 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-fontdescriptor-indirect-flags-import.php
```

The smoke emits `font_flags: [98,262176]`, `indirect_fontdescriptor_fields_resolved=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and one paragraph containing `Plain *italic segment* bridge **bold segment** outro`.

Full focused markerPDF lane gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
58 test files, 2338 assertions, 0 failures
```

Syntax checks passed for:

- `lanes/markerpdf/src/PdfTextExtractor.php`
- `lanes/markerpdf/tests/PdfTextExtractorTest.php`
- `lanes/markerpdf/examples/wordpress-pdf-fontdescriptor-indirect-flags-import.php`

`git diff --check -- lanes/markerpdf` passed.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, indirect-object resolver, FontDescriptor metadata path, pdftext-style span construction, `FontStyleCleaner`, and `MarkdownPostProcessor`. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya, tabled, Texify, Torch, Streamlit/FastAPI runtime paths, and model downloads.

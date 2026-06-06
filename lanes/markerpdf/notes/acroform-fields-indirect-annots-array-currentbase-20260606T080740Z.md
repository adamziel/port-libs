# markerPDF AcroForm indirect Annots array boundary

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260606T080740Z`

Accepted base: `5d340c807e1abb7efc234e954dd60aa716c48876`

## Source Truth

- Upstream markerPDF routes searchable PDF text and page metadata through pdftext/PDFium before model/OCR stages. Under the current no-GPU lane scope, this PHP port owns native parser boundaries for catalog `/AcroForm`, page `/Annots`, Widget annotations, and form-field review metadata before WordPress import.
- PDF page annotation arrays can be indirect objects. This slice covers the malformed-but-recoverable boundary where `/Annots` resolves through an indirect reference chain to a terminal array, and that terminal array contains both normal Widget references and a direct Widget dictionary.

## Implementation

- `PdfAcroFormExtractor` now resolves recursive indirect array references before page Widget mapping.
- Direct Widget dictionaries in the terminal indirect `/Annots` array are materialized at the terminal array object, so page-owned Widget repair can promote omitted AcroForm parent fields and direct widget fields.
- The existing direct annotation-object fallback remains in place, and Widget `/P` page ownership is still enforced so wrong-page fields are excluded.

## Focused Evidence

Red before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectAnnotsArrayBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect page Annots array chains before AcroForm page widget repair
Values are not identical
Expected: array (
  0 => 'chain.parent',
  1 => 'chain.inline',
)
Actual: array (
)

1 test files, 1 assertions, 1 failures
```

Green focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectAnnotsArrayBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect page Annots array chains before AcroForm page widget repair

1 test files, 32 assertions, 0 failures
```

AcroForm field-boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFields*CurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectAnnotsArrayBoundaryCurrentBaseTest.php
Focused test run: 27 selected test files (root lock skipped)
...
27 test files, 1643 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-indirect-annots-array-currentbase.php
```

The smoke emits `indirect_annots_chain_resolved=true`, `referenced_parent_widget_repaired=true`, `direct_widget_materialized_from_terminal_array=true`, `wrong_page_widget_p_excluded=true`, `review_values_hidden_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted AcroForm `/Fields` direct dictionary materialization, indirect `/Fields` and `/Kids` arrays, page-tree indirect `/Kids`, wrong-page `/P` exclusion, direct page Widget arrays, generation-boundary repair, token-aware `/Fields` parsing, parent-without-`/Kids` repair, explicit empty `/Kids` exclusion, duplicate `/Annots`, object-stream field dictionaries, or submit/reset/action review. The bounded behavior is specifically recursive indirect page `/Annots` array resolution before page-owned AcroForm Widget repair.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation-aware reference resolver, top-level array/dictionary parser, page-tree walker, AcroForm field extractor, page Widget repair path, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, JavaScript/action execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope.

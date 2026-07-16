# Page Resource Struct Property Wrapper Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260606T022622Z`

Accepted base: `764a6c14f1ba73661d0d83d3e39c8d1e9ab39f7f`

## Behavior

Tagged searchable PDFs may inherit page `/Resources` from an ancestor page tree node. Their `/Properties` resource entries may point at wrapper objects before the actual marked-content property dictionary, for example `/TitleProp 21 0 R` where object `21 0` contains `22 0 R` and `22 0` contains `<< /MCID 0 >>`.

`PdfTextExtractor` already used wrapper-aware resource resolution for visible marked-content replacement. This slice applies the same native resource resolution to the StructTree MCID dictionary path, so inherited wrapped `/Properties` entries can map property names to MCIDs before structure-tree reading order is applied.

## Evidence

Red-first focused run before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php`

Result: `1 test files, 212 assertions, 1 failures`; the new fixture returned `Body physical first` before `Title structure first` because wrapped property dictionaries were not resolved for MCID mapping.

After the source edit and metadata expectation alignment:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php`

Result: `1 test files, 225 assertions, 0 failures`.

Adjacent page-resource family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php`

Result: `18 test files, 545 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-page-resource-struct-property-wrapper-currentbase.php`

Result: emits `wrapped_properties_reordered_text=true`, `property_names_excluded_from_paragraphs=true`, `parent_tree_mcids=[0,1]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF object/resource resolver and stays inside the no-GPU markerPDF scope. OCR, Surya, Texify, Torch/model workers, Streamlit/FastAPI model execution, and external PDF tools remain intentionally out of scope for this slice.

## Non-Overlap

This does not change live OCR, model execution, xref repair, encryption/auth preflight, form-resource inheritance semantics, or `/Resources` merge behavior. It is limited to inherited page `/Resources /Properties` entries used for StructTree MCID reading order when property entries resolve through indirect wrappers.

# markerPDF AcroForm Fields Cycle Boundary Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T090011Z`

## Source Truth

Upstream markerPDF relies on the PDF parser layer to expose searchable PDF content and form metadata before Markdown/WordPress conversion. At this native no-GPU boundary, AcroForm field trees must be bounded: malformed `/Kids` cycles must not recurse forever or drop the terminal field dictionary that owns the import-review metadata and page widget.

## Implementation

- `PdfAcroFormExtractor` now ignores already-visited `/Kids` references while walking a field tree branch.
- Self-referential terminal fields such as `/Kids [6 0 R 8 0 R]` preserve the terminal field value-state review and valid page widget.
- Ancestor cycles such as child `/Kids [10 0 R 14 0 R]` preserve the referenced terminal branch while keeping inherited `/FT`, `/DV`, `/DA`, and `/MaxLen` metadata.
- Detached cyclic field decoys remain excluded, and AcroForm values continue to stay out of visible WordPress paragraph text.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsCycleBoundaryCurrentBaseTest.php`

Result: `1 test files, 2 assertions, 2 failures`; both expected field-name arrays were empty because cyclic `/Kids` entries caused traversal to return no fields.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsCycleBoundaryCurrentBaseTest.php` passed with `1 test files, 53 assertions, 0 failures`.
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(AcroForm|SecurityAcroForm).*Test\.php$' | sort)` passed with `31 test files, 3059 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-cycle-boundary-currentbase.php` emitted `self_cycle_field_names=["article.self"]`, `ancestor_cycle_field_names=["profile.email"]`, preserved widget objects `[8]` and `[14]`, `visible_text_contains_form_values=false`, and all execution flags false.

## Non-Overlap

This does not repeat accepted page-owned widget discovery, direct Widget `/Fields` normalization, child-field branch normalization, token-aware arrays, indirect `/Fields`/`/Kids` arrays, generation-exact field refs, trailer-root selection, comment-split references, unowned widget-parent rejection, widget appearance/action/XFA/signature review, submit/reset actions, security preflight, or metadata/image/xref/parser clusters. The bounded behavior is only cycle-edge suppression during AcroForm field-tree descent.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, dictionary parser, generation-checked references, page-widget map, AcroForm field hierarchy/value-state review, and WordPress smoke path. Full OCR/model execution, XFA layout binding, form action execution, JavaScript, rendering, signing, pypdfium/PIL, Python models, and external PDF tools remain intentionally out of scope.

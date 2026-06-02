# AcroForm Appearance Default Resources Review Boundaries, 2026-06-02

Micro-slice: `acroform-appearance-default-resources-review-boundaries-currentbase-20260602T085845Z`

Source truth:

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF conversion at the native pdftext/pypdfium extraction boundary; this PHP lane mirrors that by exposing AcroForm review metadata without executing form actions, JavaScript, appearance rendering, Python models, or external PDF tools.
- PDF AcroForm variable-text review uses catalog `/AcroForm /DA` default appearance strings whose `/Tf` font operands reference the form default resource dictionary `/AcroForm /DR`, especially `/DR /Font`. Widget or field `/DA` overrides can use the same default resources, but unresolved resource names must remain unresolved review metadata rather than falling back to another font.

Implemented behavior:

- `PdfAcroFormExtractor::extractForm()` now exposes a form-level `default_resources` summary for `/AcroForm /DR /Font`, including direct and indirect font resources, font subtype, base font, encoding, and FontDescriptor review fields.
- Field and widget `default_appearance` metadata now includes bounded resource resolution fields such as `font_resource_resolved`, `font_resource_object`, `font_resource_base_font`, `font_resource_subtype`, descriptor flags/name/weight, and the default-resource source object.
- Widget-local `/DA` values are resolved against declared `/DR` resources only. A widget `/DA` using `/Missing` remains `font_resource_resolved=false` and does not inherit `/Helv` as a false positive.

Focused verification:

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-default-resources.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` passed with 1 test file, 443 assertions, and 0 failures. Focused AcroForm assertions moved 393 -> 443.
- `php tools/run-tests.php lanes/markerpdf/tests` passed with 60 test files, 2901 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-default-resources.php` emitted `default_resource_object=30`, `default_resource_font_count=3`, `resolved_default_appearance_fonts=["Helv:Helvetica","Body:ABCDEE+SourceSansPro"]`, `unresolved_widget_appearance_fonts=["article.teaser:Missing"]`, `body_font_descriptor_flags=32`, and all execution flags false.
- `git diff --check -- lanes/markerpdf` passed.

Status delta:

- Behavior tests move 446 -> 447.
- Mapped markerPDF/PDF semantics move 298 -> 299 / 78.

Non-overlap:

- This does not repeat accepted AcroForm field flags, current/default value-state, widget `/AS` default comparison, SubmitForm/ResetForm, calculation/signature state, XFA signature widget, annotation appearance text extraction, FontDescriptor styled spans, indirect FontDescriptor text styling, or page/widget annotation-state review slices. It only adds `/AcroForm /DR /Font` default-resource review metadata for `/DA` appearance strings.

Dependency closure:

- No new support component is needed. This reuses the native PDF object/dictionary parser, AcroForm field/widget traversal, PDF name decoder, and existing non-executing review metadata path. Full appearance rendering remains out of scope and would require a separate native renderer; this slice intentionally reports review metadata without rendering.

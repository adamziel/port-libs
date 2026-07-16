# markerPDF XMP Membership Property Boundary Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260606T001202Z`
Session: `port-dev-markerpdf-metadata-xmp-20260606T001202Z`
Base accepted HEAD: `94f038e3d5fdd3910f787431f17ec33eea55aed5`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF delegates searchable-PDF text and metadata extraction through PDF parser dependencies before OCR/model stages. Under the current no-GPU markerPDF scope, this lane owns native PHP PDF parser and metadata boundaries for WordPress imports.
- XMP is RDF/XML. RDF containers commonly use `rdf:li`, but RDF/XML membership properties such as `rdf:_1`, `rdf:_2`, and later numeric members are valid collection entries. They must remain ordered separate metadata values and must not collapse into concatenated text.

## Behavior

- `PdfMetadataExtractor` now treats direct RDF container membership properties `rdf:_n` as collection items when no `rdf:li` values are present.
- Membership entries are ordered by numeric index, duplicate indexes keep the first occurrence, and existing `rdf:li` behavior remains preferred.
- The behavior applies through the shared XMP collection helper, covering document metadata promotion and rejected-stream review summaries.
- WordPress imports now preserve title alternatives, creator lists, and subject lists from `rdf:_n` containers while keeping raw XMP packet text and trailing decoy packets out of visible Gutenberg paragraphs.

## Red-First Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMembershipPropertyBoundaryCurrentBaseTest.php
```

Result before the source edit:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL extracts ordered XMP RDF membership properties before WordPress metadata import
Expected: 'Current Membership Property XMP Title'
Actual: 'Titre membership ignoreCurrent Membership Property XMP Title'
FAIL summarizes rejected RDF membership-property XMP streams without concatenating values
Expected: 2
Actual: 1
1 test files, 16 assertions, 2 failures
```

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMembershipPropertyBoundaryCurrentBaseTest.php
```

```text
Focused test run: 1 selected test files (root lock skipped)
PASS extracts ordered XMP RDF membership properties before WordPress metadata import
PASS summarizes rejected RDF membership-property XMP streams without concatenating values
1 test files, 46 assertions, 0 failures
```

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfMetadataXmp.*Test\.php$' | sort)
```

```text
Focused test run: 35 selected test files (root lock skipped)
35 test files, 1548 assertions, 0 failures
```

```bash
php lanes/markerpdf/examples/wordpress-pdf-xmp-membership-property-boundary-currentbase.php
```

Result: emits `markerpdf-pdf-xmp-membership-property-boundary-currentbase` with ordered authors/keywords, packet boundary applied, decoy XMP excluded, and visible text excluding XMP metadata.

## Non-Overlap

This does not repeat accepted XMP packet boundary, declared encoding, entity rejection, namespace, external `rdf:about`, resource reference, `rdf:nodeID`, qualified value, nested qualifier, sparse list fallback, typed node, or duplicate catalog `/Metadata` work. The bounded behavior is only RDF/XML membership-property containers (`rdf:_n`) in the existing XMP metadata parser boundary.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, catalog `/Metadata` extractor, XMP packet/root boundary parser, DOM-based XMP reader, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.

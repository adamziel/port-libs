# markerpdf XMP Namespace Boundary Current Base

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF metadata through PDF parser-backed document metadata before model/OCR fallback. In the no-GPU native PHP lane, Catalog `/Metadata` XMP parsing owns the boundary where only document-level Adobe XMP roots are promoted to WordPress metadata.

An XML element with local name `xmpmeta` but a non-Adobe namespace is not the document XMP packet wrapper. It must not stop the root scan before the current Adobe `adobe:ns:meta/` XMP root that follows.

## Implementation

`PdfMetadataExtractor::boundedXmpXmlRootCandidates()` now keeps collecting bounded `xmpmeta` candidates after non-Adobe local-name matches, but still stops at the first Adobe `adobe:ns:meta/` XMP root that contains RDF. `parseXmpPacket()` still promotes only DOM candidates whose `rdf:RDF` parent is the document root or an Adobe `xmpmeta` root, so wrong-namespace wrappers are skipped while the following current XMP packet can be parsed and trailing decoys remain bounded.

## Evidence

Red-first probe before the change selected trailer Info metadata only:

```text
source: ["info"]
title: Info Title
xmp: []
```

Focused test after the change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpNamespaceBoundaryCurrentBaseTest.php

Focused test run: 1 selected test files (root lock skipped)
PASS skips non-Adobe xmpmeta wrappers before the current document XMP root
PASS summarizes rejected XML streams after non-Adobe xmpmeta wrappers
PASS does not promote trailing packets after the first Adobe XMP root boundary

1 test files, 47 assertions, 0 failures
```

Adjacent XMP boundary gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpNamespaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpPacketBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpCdataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php

Focused test run: 5 selected test files (root lock skipped)
...
5 test files, 1033 assertions, 0 failures
```

Full XMP current-base family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php

Focused test run: 16 selected test files (root lock skipped)
...
16 test files, 633 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-namespace-boundary-currentbase.php
```

The smoke exits 0 and emits `title_from_current_adobe_xmp=true`, `wrong_namespace_decoy_excluded=true`, `trailing_decoy_excluded=true`, `visible_text_excludes_xmp=true`, and no Python/model/external PDF execution.

## Non-overlap

This does not repeat accepted XMP packet padding, comment/DOCTYPE decoy, CDATA closing-token, UTF-16, language alternative, typed-node, qualified-value, nested-qualifier, empty-root, self-closing-root, DTD/entity rejection, xref metadata precedence, associated-file XMP, OutputIntent, encryption, or Info-encoding fallback work.

The bounded behavior is specifically continuing XMP root candidate scanning past wrong-namespace `xmpmeta` local-name wrappers.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, XMP XML candidate parser, DOM metadata reader, trailer Info fallback, text extractor, and WordPress smoke renderer. Full OCR/model parity remains intentionally outside the current no-GPU markerPDF scope and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.

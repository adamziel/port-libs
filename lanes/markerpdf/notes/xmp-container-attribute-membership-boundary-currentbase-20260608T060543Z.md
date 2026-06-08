# XMP Container Attribute Membership Boundary Current Base

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T060543Z`
Base accepted HEAD: `cfec77028507d7bdc4213fc9124ee422079c0937`

## Source Truth

PDF document metadata is supplied by the catalog `/Metadata` stream as XMP/RDF XML. This no-GPU slice keeps that boundary native: the root document XMP packet is parsed without Python, PDFium, PIL, OCR, model workers, external PDF tools, or XML network access.

The covered RDF/XML shape is a producer-repaired ordered list where an RDF container (`rdf:Seq` or `rdf:Bag`) wraps an `rdf:Description` whose `rdf:_n` attributes contain the membership values. Those attributes are explicit ordered list members; private child qualifier elements on the same wrapper are not document text values.

## Behavior

- `PdfMetadataExtractor` now expands `rdf:_n` attribute membership values when an `rdf:Description` wrapper appears inside RDF containers.
- Document XMP authors and keywords are selected before trailer Info fallback for that shape.
- Rejected non-document XML streams still expose only review counts and field names; raw XMP text values and private qualifier nodes stay redacted.
- Visible WordPress paragraph extraction excludes XMP payload text and trailing packet decoys.

## Red-First Evidence

Before implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpContainerAttributeMembershipBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 17 assertions, 2 failures
```

Failures:

- accepted document XMP authors fell back to `Info Container Attribute Author`;
- rejected-stream XMP summary omitted `authors` and `keywords`.

## Verification

After implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpContainerAttributeMembershipBoundaryCurrentBaseTest.php
```

Result:

```text
PASS extracts XMP RDF container attribute membership lists before WordPress import
PASS summarizes rejected XMP container attribute membership streams without text leakage

1 test files, 50 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-xmp-container-attribute-membership-currentbase.php
```

Result: exits `0`; emits ordered XMP authors, ordered XMP keywords, `rejected_non_metadata_xml_stream`, `rejected_author_count=3`, `rejected_keyword_count=2`, `private_xmp_values_redacted=true`, and `visible_text_excludes_xmp=true`.

## Status Delta

- Added 2 focused PHP PASS cases.
- Added 50 focused assertions.
- Added 1 WordPress smoke/example.
- Updated `lanes/markerpdf/lane-status.json` from `phpPass=2936` to `phpPass=2938`, `wordpressScenarios=2444` to `wordpressScenarios=2445`, and tracked behavior tests from `2165` to `2167`.

## Dependency Closure

No new support component is needed. This reuses the existing native `DOMDocument` XMP parsing path with `LIBXML_NONET` and the existing PDF stream decoder/object selection code.

## Non-Overlap

This does not repeat the accepted XMP packet begin/end, direct attribute, direct/wrapped attribute membership, resource reference, nodeID, parseType Collection, qualified `rdf:value`, structured-list, typed-node, or catalog `/Metadata` stream-role slices. The new boundary is specifically RDF container wrappers whose `rdf:Description` child carries `rdf:_n` membership attributes.

Root harness: not run - isolated micro-slice.

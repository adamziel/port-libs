# Pandoc EPUB Metadata Link Media-Type Parameters

Slice: `plib-40lwn`, EPUB3 package ingestion core blocker.

This slice keeps OPF metadata `<link>` media-type parameters visible in the
native PHP EPUB reader. Metadata links now report declared media types,
manifest-inherited media types, normalized/base MIME values, parameter maps,
parameter names, invalid parameter diagnostics, and the same fields in metadata
link target-policy rows.

The handoff flows through:

- `metadata.links`
- `metadata.linkMediaTypes`
- `metadata.linkTargetReport`
- `importReport.metadata`
- document metadata attributes

No Pandoc, EPUBCheck, zip/unzip, browser renderer, external validator, online
service, live provider test, or live-service provider test is invoked.

Focused verification:

```text
php -l lanes/pandoc/src/EpubReader.php
php -l lanes/pandoc/tests/EpubReaderTest.php
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 4222 assertions, 0 failures
```

Full verification:

```text
php tools/run-tests.php lanes/pandoc/tests
44 test files, 72570 assertions, 0 failures
```

Metric accounting:

- `phpPass`: `3250 -> 3251` after rebasing over `46aa98116d`
- `phpFail`: `0`
- `mappedEpubMetadataLinkMediaTypeParameterCases`: `1`
- `epubMetadataLinkMediaTypeParameterAssertions`: `41`

Non-overlap: this does not change OPF metadata link target resolution,
refines-subject attachment, vocabulary token parsing, linked-resource byte
exposure policy, remote fetch policy, manifest media-type preflight, collection
links, guide links, XHTML links, or compact `EpubPackage` link summaries. It is
restricted to media-type parameter provenance on rich `EpubReader` OPF metadata
links.

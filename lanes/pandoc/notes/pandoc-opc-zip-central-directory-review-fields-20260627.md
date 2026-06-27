# OPC ZIP Central Directory Review Fields

Slice: `plib-4yop4`, shared ZIP/OPC package core blocker.

`OpcRelationshipGraph` now carries central-directory variable-field byte
provenance through constructed and raw OPC ZIP manifest preflights. The summary
reports name bytes, central extra-field bytes, central entry-comment bytes,
review-field bytes, entry counts, entry-name buckets, and compact review rows
before XML/package handoff.

This is metadata-only package ingestion support. It adds no new package part
payload reads, does not expose package bytes, and does not invoke Pandoc, office
suites, `ZipArchive`, zip/unzip tools, browsers, external validators, or network
services.

Direct-format parity accounting: no new Pandoc input/output format token is
claimed. The focused shared OPC ZIP manifest behavior matrix gains one package
preflight case in `OpenPackagingConventionsTest.php`; focused PHP validation
remains green with `phpFail=0` for this slice.

Validation:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

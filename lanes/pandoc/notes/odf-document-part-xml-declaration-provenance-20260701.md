# ODF document part XML declaration provenance

Slice: `plib-osst3`, ODF/ODT OpenDocument package ingestion core blocker.

`OdfReader` and compact `OpenDocumentPackage` summaries now carry metadata-only
XML declaration provenance for core ODF XML document parts: `content.xml`,
`styles.xml`, `meta.xml`, and `settings.xml`.

The `documentPartVersions` report records per-part declaration presence,
version, encoding, standalone state, and attribute count, plus package-level
version/encoding/standalone rollups and declaration item records. Raw XML
declaration bytes remain out of the package handoff.

Direct-format parity remains active. No external Pandoc, office suite, TeX or
browser engine, Typst, Node, zip/unzip, validator, or live service is used.

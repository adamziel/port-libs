# EPUB OCF metadata link ZIP provenance

Slice: `plib-m9hfx`

## Scope

This slice extends compact EPUB3 package ingestion for OCF
`META-INF/metadata.xml` link records. It does not shell out to Pandoc,
EPUBCheck, zip/unzip, browser engines, or external validators.

## Change

- Container metadata links now use the same ZIP entry provenance contract as
  OPF package links and OPF collection links.
- Local OCF link targets now report byte length, compressed byte length,
  compression method, compression support, CRC32, and byte-exposure policy.
- If the linked target is also an OPF manifest item whose bytes are not
  exposable, the container link inherits that non-exposable handoff policy.

## Focused test

Added `preserves OCF metadata link ZIP provenance for package handoff` in
`EpubPackageTest.php`, covering an OCF metadata link to a manifest-backed
record stored with an unsupported ZIP compression method.

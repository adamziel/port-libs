# DOCX ZIP platform attribute provenance handoff

DOCX ZIP package provenance now preserves selected source-entry platform
attributes from the native `ZipPackage::entryHandoffPreflight()` path.

The normalized `zipPackage.entries` / `byPackagePath` rows carry creator host
system, external attributes, DOS attributes, internal attributes, Unix mode and
permission bits, executable/writable flags, and platform-attribute issue codes.
`packageProvenance.summary` also exposes aggregate `zipSource*` counters and
the selected platform attribute provenance and issue entry lists.

This is metadata-only review state; DOCX package bytes remain governed by the
existing `docx-zip-entry-metadata-only` exposure policy.

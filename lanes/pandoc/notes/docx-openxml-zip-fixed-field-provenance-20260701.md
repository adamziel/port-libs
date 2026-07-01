# DOCX ZIP fixed-field provenance handoff

DOCX ZIP package provenance now preserves selected source-entry local header and
central directory fixed-field review metadata from
`ZipPackage::entryHandoffPreflight()`.

The normalized `zipPackage.entries` / `byPackagePath` rows carry fixed header
offsets, lengths, raw field offsets, central-vs-local values, match booleans,
and issue codes. `packageProvenance.summary` also exposes `zipSource*` counts
and the selected local-header and central-directory fixed-field entry lists.

This is metadata-only package review state; DOCX bytes remain governed by the
existing `docx-zip-entry-metadata-only` exposure policy.

# ZIP package central-directory variable field mix summaries

## Context

The shared ZIP/OPC package manifest now reports how central-directory
variable fields are distributed across entries. The rollup groups entries by
whether their central-directory record contains only a raw name, raw name plus
extra fields, raw name plus an entry comment, or all three variable fields.

## Provenance

The manifest exposes:

- `centralDirectoryVariableFieldMixSummaryCount`
- `centralDirectoryVariableFieldMixes`
- `centralDirectoryVariableFieldMixSummaries`

Each summary includes entry counts, file/directory counts, central extra-field
and entry-comment entry counts, aggregate central-directory variable-field byte
totals, source-record bytes, directory roots, and entry names.

The rollup is metadata-only. It does not expose extra-field payload bytes or
entry-comment bytes; those remain covered by the existing byte-exposure
policies and hashes.

DOCX OpenXML package provenance carries the same rollup through package
summary, zip package provenance, and package identity.

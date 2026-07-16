# ODF compact package identity manifest attributes 2026-06-30

`plib-7iltj` closes a compact `OpenDocumentPackage` identity parity gap:
manifest root custom attributes, root namespace declarations, file-entry custom
attributes, and file-entry namespace declarations are now part of the
metadata-only package identity payload.

The parser already preserved those fields in manifest review, package inventory,
and `OdfReader` provenance. This slice makes compact package identity hashes
change when those review-relevant manifest attributes or namespace declarations
change, without exposing package bytes.

Direct-format parity accounting is unchanged. This is a native ODF/ODT package
ingestion completeness slice under the existing `odt` path; it adds no reader or
writer format token and invokes no external Pandoc, office, browser, TeX, unzip,
zip, or validator process.

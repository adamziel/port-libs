# Pandoc JSON/Native Textual Raw Constructor Provenance - 2026-07-01

## Scope

- `NativeReader` textual native input now records enclosing `RawBlock` and
  `RawInline` constructor provenance on raw AST nodes.
- The existing textual raw format helper provenance is retained, so
  `Format` helper payloads still round-trip through `PandocJsonWriter` and
  `NativeWriter`.
- The focused regression verifies unchanged raw constructor preservation and
  stale native sidecar regeneration after raw text edits.

## Boundary

This stays inside native PHP JSON/native AST reader and writer paths. It does
not invoke Pandoc, office suites, TeX/browser engines, zip/unzip, Jupyter, Node
tooling, online services, or external validators.

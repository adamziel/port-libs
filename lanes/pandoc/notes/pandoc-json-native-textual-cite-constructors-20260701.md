# Pandoc JSON/Native Textual Cite Constructors - 2026-07-01

## Scope

- `NativeReader` textual native input now maps `Cite` constructors to the
  same shared AST shape used by Pandoc JSON/native JSON input.
- One citation record becomes a `citation` node with `id`, `prefix`,
  `suffix`, mode, note number, and hash fields that `PandocJsonWriter` can
  serialize directly.
- Multiple citation records become a `citation_group` with child `citation`
  nodes plus source display inlines, preserving constructor-complete handoff
  through `PandocJsonWriter` and `NativeWriter`.
- Empty textual native `Cite` record lists and empty citation IDs now fail at
  parse time, matching the JSON reader boundary.

## Boundary

This stays inside native PHP JSON/native AST reader and writer paths. It does
not invoke Pandoc, citeproc, CSL processors, TeX/browser engines, office
suites, `zip`/`unzip`, Node tooling, online services, or external validators.

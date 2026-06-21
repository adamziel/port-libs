# Pandoc Data Format Parity Target - 2026-06-09

Source truth: current Pandoc User's Guide dated 2026-06-03 at
`https://pandoc.org/demo/example2.html`, General options `--from` and `--to`,
cross-checked against upstream source commit
`912bfa5e2e3f5c74eb125dfc19404f67c61ca58b`.

The PHP lane now has a machine-readable denominator in
`src/PandocFormatRegistry.php`:

- 51 upstream input format tokens.
- 75 upstream output format tokens.
- Upstream aliases/deprecated synonyms remain tracked as accepted tokens.
- Current PHP support is recorded as `partial` or `unsupported`; no format is
  marked complete yet.

Current direct PHP coverage is concentrated in Markdown-family readers/writers,
bounded HTML reader/writer slices, bounded LaTeX writer/raw-TeX slices,
Native reader/writer fixture coverage, and partial current Pandoc JSON AST
reader/writer support. Direct package readers/writers such as DOCX, EPUB, ODT,
PPTX, Jupyter notebooks, XML native AST, bibliography formats, wiki formats,
roff, Typst, and PDF remain explicit unsupported or dependency-gated targets.

Next worker gates:

1. Expand JSON native AST reader/writer coverage against upstream JSON/native
   fixture pairs until every shared `AstNode` constructor is covered.
2. Open XML native AST reader/writer parity, then reuse that XML support for
   JATS, DocBook, TEI, OPML, and OpenDocument package slices.
3. Implement ZIP/OPC package primitives before claiming direct DOCX, EPUB, ODT,
   or PPTX input/output support.
4. Keep PDF as an engine-handoff format unless a separate native PDF output
   strategy is approved; do not count TeX/browser/Typst shell-outs as native
   implementation progress.

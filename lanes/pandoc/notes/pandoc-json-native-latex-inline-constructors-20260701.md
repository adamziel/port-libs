# Pandoc JSON/Native LaTeX Inline Constructors - 2026-07-01

## Scope

- `LatexWriter` now renders Pandoc native JSON inline formatting constructors
  carried through shared AST nodes instead of flattening them to plain text.
- Native `Underline` and `Strikeout` constructor provenance maps to
  `\underline{...}` and `\sout{...}` while generic shared AST underline and
  strikeout nodes keep the existing `\ul{...}` and `\st{...}` shorthand output.
- `Superscript`, `Subscript`, `SmallCaps`, and `Quoted` inline constructor
  nodes now survive LaTeX writer handoff as `\textsuperscript{...}`,
  `\textsubscript{...}`, `\textsc{...}`, and TeX quote delimiters.
- The focused regression uses native Pandoc JSON through `NativeReader` and
  verifies the generic shorthand boundary.

## Boundary

This stays inside native PHP Pandoc JSON/native AST reader-to-LaTeX writer
handoff. It does not invoke Pandoc, citeproc, CSL processors, TeX engines,
browser engines, office suites, `zip`/`unzip`, Node tooling, online services,
or external validators.

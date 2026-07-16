# Pandoc FB2 Reader Audit - 2026-06-21

Scope: native PHP reader coverage for upstream Pandoc's FB2 reader golden fixture denominator.

## Upstream Basis

- Pinned upstream checkout: Pandoc `912bfa5e2e3f5c74eb125dfc19404f67c61ca58b`.
- Reader source: `src/Text/Pandoc/Readers/FB2.hs`.
- Reader tests: `test/Tests/Readers/FB2.hs`.
- Golden fixtures: `emphasis`, `titles`, `epigraph`, `poem`, `meta`, and `notes` under `test/fb2/reader`.

## Implemented PHP Surface

- FictionBook root/body/section parsing with section-level headings and section Divs.
- Body and section titles, including multi-paragraph title line breaks.
- Epigraph Divs.
- Poem title, epigraph, stanza subtitle/title, verse line blocks, text author, and date.
- Title-info metadata for authors, book title, annotation/abstract blocks, keywords, and date.
- Notes body pre-scan and note links converted to Pandoc note nodes.
- Inline strong, emphasis, strikethrough, subscript, superscript, code, styles, links, and images.

## Registry Impact

- Upstream inputs remain 51 total.
- PHP input support moves from 32 to 33 partial readers.
- Unsupported upstream inputs move from 19 to 18.

## Verification

- Syntax gate passed for `Fb2Reader`, registry changes, and touched tests.
- FB2/registry focused gate: `3` files, `262` assertions, `0` failures.
- Direct upstream-file smoke parsed all six pinned FB2 fixtures. Local native output is compact and not byte-identical to upstream pretty native output.
- Broad reader/writer smoke including FB2/Jira/PPTX/XLSX: `27` files, `18,566` assertions, `0` failures.

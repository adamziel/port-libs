# Pandoc PPTX Reader Audit - 2026-06-21

Scope: bounded native PHP reader coverage for upstream Pandoc's PPTX input fixture. Output writing is explicitly out of scope for this slice.

## Upstream Basis

- Pinned upstream checkout: Pandoc `912bfa5e2e3f5c74eb125dfc19404f67c61ca58b`.
- Reader test: `test/Tests/Readers/Pptx.hs`.
- Fixture pair: `test/pptx-reader/basic.pptx` and `test/pptx-reader/basic.native`.
- Reader modules used as primary source: `Text.Pandoc.Readers.Pptx`, `Text.Pandoc.Readers.Pptx.Parse`, `Text.Pandoc.Readers.Pptx.Slides`, `Text.Pandoc.Readers.Pptx.Shapes`, and `Text.Pandoc.Readers.Pptx.SmartArt`.

## Implemented PHP Surface

- Root OPC relationship discovery for `ppt/presentation.xml`.
- Presentation slide order from `p:sldIdLst` and presentation relationships.
- Per-slide relationship loading.
- Slide `Header 2` blocks with stable `slide-N` identifiers.
- Title placeholder filtering so title shapes do not duplicate body content.
- Text-box paragraphs, including empty paragraphs.
- Wingdings and explicit bullet detection with contiguous same-level bullet grouping.
- Simple DrawingML tables with first-row headers.
- Picture references as image inlines pointing to internal package media paths.
- SmartArt diagram data/layout relationship resolution, layout class metadata, strong parent nodes, and bullet-list children.

## Registry Impact

- Upstream inputs remain 51 total.
- PHP input support moves from 30 to 31 partial readers.
- Unsupported upstream inputs move from 21 to 20.
- Rich package direct input support moves from 5/6 to 6/6.
- Rich package output support is unchanged: PPTX output remains unsupported with writer/package-assembly diagnostics.

## Verification

- Syntax gate passed for `PptxReader`, registry changes, and touched tests.
- PPTX/registry focused gate: `5` files, `932` assertions, `0` failures.
- Broad reader/writer smoke including PPTX/XLSX: `25` files, `18,446` assertions, `0` failures.
- PDF/markerPDF guard: `2` files, `3,051` assertions, `0` failures.
- Local problematic-PDF transient smoke: `reconstruction=geometry tables=10 geometry=10 cells=114 rects=896 gray_attrs=34 collapsed_guard=clear spaced_guard=hit`.
- Hardcode scan for the local invoice path and pasted visible sample terms returned `0` hits.
- `git diff --check` passed after the final evidence doc update.

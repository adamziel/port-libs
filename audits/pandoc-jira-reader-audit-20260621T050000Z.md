# Pandoc Jira Reader Audit - 2026-06-21

Scope: bounded native PHP reader coverage for upstream Pandoc's Jira wiki reader unit semantics. This is not a full Jira fixture completion claim.

## Upstream Basis

- Pinned upstream checkout: Pandoc `912bfa5e2e3f5c74eb125dfc19404f67c61ca58b`.
- Reader source: `src/Text/Pandoc/Readers/Jira.hs`.
- Unit tests: `test/Tests/Readers/Jira.hs`.
- Larger fixture checked but not closed: `test/jira-reader.jira` and `test/jira-reader.native`.

## Implemented PHP Surface

- Paragraphs, blank-line handling, and hard line breaks inside paragraphs.
- `h1.` through `h6.` headings.
- Bullet and ordered list lines, including simple nesting support.
- `bq.` block quotes and `{quote}` containers.
- `{code:lang}` and `{noformat}` code blocks.
- Jira pipe tables with all-header first-row detection.
- `{panel}` containers and `{color:name}` block containers.
- Inline strong, emphasis, strikeout, underline, subscript, superscript, citation, color spans, anchors, monospaced code, links, attachments, user links, smart links/cards, images, and HTML entities.

## Registry Impact

- Upstream inputs remain 51 total.
- PHP input support moves from 31 to 32 partial readers.
- Unsupported upstream inputs move from 20 to 19.
- Jira is partial, not complete: `jira-reader.jira/native` is parsed but not exact.

## Verification

- Syntax gate passed for `JiraReader`, registry changes, and touched tests.
- Jira/registry focused gate: `3` files, `279` assertions, `0` failures.
- Larger Jira fixture smoke: `blocks=51`, `native_bytes=14556`, `expected_bytes=20887`, `same=no`.
- Broad reader/writer smoke including Jira/PPTX/XLSX: `26` files, `18,516` assertions, `0` failures.

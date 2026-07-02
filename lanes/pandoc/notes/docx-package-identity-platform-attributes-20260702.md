# DOCX package identity platform attributes

Date: 2026-07-02

Slice: `plib-v5q88`

Summary:
- `DocxOpenXmlReader` now carries ZIP platform-attribute rollups through deterministic `packageIdentity` metadata.
- Identity package entries now include metadata-only creator host, external attributes, DOS/internal attributes, Unix mode/permission summaries, and platform-attribute issue codes.
- The focused fixture verifies executable Unix mode, DOS hidden attributes, and internal text attributes through both package inventory and package identity without exposing package bytes or invoking external ZIP/office tooling.

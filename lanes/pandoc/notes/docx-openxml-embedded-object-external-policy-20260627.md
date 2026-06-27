# DOCX OpenXML embedded object external policy

Slice: `plib-riy7u`, Pandoc DOCX OpenXML package ingestion core blocker, 2026-06-27.

This slice extends native PHP DOCX package ingestion so embedded OLE/package
relationships preserve bounded external-target policy metadata. External
embedded-object targets now report target kind, URI scheme, safe/unsafe policy,
unsafe target lists, and issue-code rollups in `docx.embeddedObjects` and
`packageProvenance.summary` without fetching external resources or exposing
embedded package bytes.

Direct-format parity accounting: adds one focused DOCX/OpenXML package-ingestion
PASS case for embedded-object external-target policy. No Pandoc, office suite,
unzip/zip, browser, or external validator is invoked.

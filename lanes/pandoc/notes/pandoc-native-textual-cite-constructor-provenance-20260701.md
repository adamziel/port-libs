# Textual Native Cite Constructor Provenance

Slice: `plib-lanri` JSON/native AST constructor completeness.

`NativeReader` now records canonical `Cite` wrapper native payloads when parsing
textual native citation inlines. Grouped and single citations carry the original
display inline payload, `citationRecordsNative`, and per-record `Citation`
constructor/native provenance, matching the constructor metadata already exposed
by `PandocJsonReader`.

Focused coverage lives in
`lanes/pandoc/tests/NativeReaderTextualCitationConstructorTest.php` and checks
both grouped citations and a single suppress-author citation through JSON and
native writer handoff.

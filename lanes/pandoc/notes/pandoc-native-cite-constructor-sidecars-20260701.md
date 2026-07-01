# Native Cite constructor sidecars

Issue: `plib-gq5cz`

The native text reader now carries `Cite` constructor provenance for textual
native citation inlines in the same metadata-only shape used by the JSON-native
handoff path:

- citation and citation-group nodes receive reusable `constructor`, `native`,
  and `citationRecordsNative` sidecars when the textual `Cite` payload can be
  reconstructed from parsed records and display inlines;
- individual citation records receive `citationConstructor` and
  `citationNative` records with native prefix/suffix inline payloads and
  citation mode constructors;
- JSON and native writers preserve the parsed native citation records when the
  citation AST remains equivalent, and keep regenerating records when edited.

This is a bounded JSON/native AST constructor-completeness slice. It uses only
the in-repo PHP readers, writers, and tests, with no Pandoc binary, office
suite, TeX/browser engine, Node tooling, zip/unzip, validator, or live service.

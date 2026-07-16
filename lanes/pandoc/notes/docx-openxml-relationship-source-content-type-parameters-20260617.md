# DOCX OpenXML Relationship Source Content-Type Parameters

Bead: `plib-klkto`

This slice keeps the DOCX/OpenXML package ingestion recovery scope bounded to relationship source provenance. `DocxOpenXmlReader` already records parsed source content-type parameters on each relationship part; the new rollup carries that existing metadata into `relationshipSources` and existing-source summaries, then adds package-level counters for parameterized relationship sources, parameter names, parameter values, and content-type source buckets.

The focused fixture covers default XML sources, default `.rels` relationship-part sources, override content types, and missing source sidecars. It does not run Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, or live provider tests.

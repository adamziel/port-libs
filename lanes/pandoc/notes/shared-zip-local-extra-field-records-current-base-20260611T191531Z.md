2026-06-11 plib-gqlr0

Scope: shared ZIP/OPC package primitives, under `lanes/pandoc` only.

Current-base blocker slice: `ZipPackage::localHeaderPreflight()` already exposed
local-header byte spans, name/extra offsets, and aggregate local extra-field byte
counts, but package review consumers could not inspect the local extra-field
records attached to those spans.

Change: local-header preflight now includes aggregate local extra-field record
counts/IDs and per-entry parsed local extra-field records with IDs, relative
structure metadata, and absolute package byte offsets for each record and data
payload. Existing local-header span fields and strict/raw strict propagation are
unchanged.

Guardrail: the focused fixture uses native PHP ZIP fixture bytes only. It does
not invoke Pandoc, office suites, zip/unzip, browser renderers, external
validators, online services, live provider tests, or live-service provider tests.

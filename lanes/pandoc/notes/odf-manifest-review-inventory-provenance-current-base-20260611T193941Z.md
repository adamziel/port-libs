2026-06-11 plib-54cpa

Scope: compact ODF/ODT OpenDocument package ingestion, under `lanes/pandoc` only.

Current-base blocker slice: manifest `manifest:version` and
`manifest:preferred-view-mode` were parsed on each file-entry, and package byte
policy diagnostics were already hydrated, but package-review consumers could not
see that provenance uniformly through `manifestReview` and `packageInventory`.

Change: manifest review rows now expose per-entry version and preferred-view-mode.
ZIP package inventory rows now expose manifest version, preferred-view-mode,
declared size, declared-size mismatch state, byte-exposure policy, and manifest
diagnostics for declared parts while preserving null/empty defaults for
undeclared package entries.

Guardrail: the focused fixture uses native `ZipPackage::fromParts` bytes only. It
does not invoke Pandoc, office suites, zip/unzip, browser renderers, external
validators, online services, live provider tests, or live-service provider tests.

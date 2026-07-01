# DOCX package identity path segment positions

## Context

DOCX/OpenXML package ingestion already summarizes package part path-segment
positions for reviewer handoff. This slice carries those existing rollups into
the deterministic package identity payload.

## Identity fields

Package identity now includes:

- `packagePathSegmentPosition*` rollups for first, middle, last, and only
  segment positions.
- `packageCaseFoldPathSegmentPosition*` rollups for case-folded segment
  positions.
- `duplicatePackageCaseFoldPathSegmentPosition*` duplicate case-fold segment
  metadata.

The fields are metadata-only and reuse the existing package summary values.
They do not expose package bytes or XML payload text.

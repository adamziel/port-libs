# DOCX relationship source name characters

Added package provenance summary fields for relationship source part names that contain uppercase bytes, whitespace, literal percent-encoded octets, or non-ASCII bytes.

The review is metadata-only: relationship parsing and target resolution are unchanged. Package-root pseudo-sources are skipped, and flagged package or missing source parts are reported with source path, content type, relationship count, and relationship part metadata.

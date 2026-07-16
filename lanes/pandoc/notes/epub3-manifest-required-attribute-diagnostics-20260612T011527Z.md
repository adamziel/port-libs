# EPUB3 Manifest Required Attribute Diagnostics

Bead: `plib-sjq5j`

EPUB package ingestion now retains OPF manifest items with missing `id`, `href`, or
`media-type` attributes instead of aborting the package parse. The compact
package summary reports the malformed item, the missing required attributes, and
invalid manifest href targets in `validation.manifest` plus the WordPress import
handoff aliases.

This keeps valid spine/nav/package context available for review while still
marking the package invalid through structured diagnostics.

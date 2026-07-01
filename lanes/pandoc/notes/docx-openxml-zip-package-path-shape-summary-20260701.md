# DOCX ZIP package path shape summary handoff

DOCX package provenance now promotes the shared native ZIP manifest path-shape
metadata into `packageProvenance.summary`:

- `zipPackageManifestMaxPathSegmentCount`
- `zipPackageManifestMaxDirectoryDepth`
- `zipPackageManifestDeepestEntryNames`
- `zipPackageManifestDeepestEntryNameCount`

The source remains `ZipPackage::packageManifestPreflight()` and no external ZIP
tooling is used. The focused DOCX package manifest fixture asserts the concrete
deepest entries for the current fixture in central-directory order.

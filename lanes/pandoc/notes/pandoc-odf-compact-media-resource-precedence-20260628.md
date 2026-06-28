# ODF compact media resource package role precedence

Slice: `plib-ncy26` / ODF/ODT OpenDocument package ingestion.

Compact `OpenDocumentPackage` summaries now expose
`manifestReview.mediaResources`, mirroring the rich reader's media-resource
review vocabulary. The summary distinguishes actual document media resources
from image/audio/video-looking package sidecars that are owned by stronger ODF
package roles.

Covered package-role precedence families in this slice:

- Forms package previews (`form-package`)
- Gallery previews (`gallery-package`)
- Linked-resource cache previews (`linked-resource-package`)
- Attachment previews (`attachment-package`)
- Template previews (`template-package`)

The sidecar entries stay out of `mediaParts`, keep their package-specific
`*-package-bytes-blocked` byte exposure policies, and surface
`odf-media-resource-package-role-precedence` as metadata-only review evidence.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 file, 1,963 assertions, 0 failures

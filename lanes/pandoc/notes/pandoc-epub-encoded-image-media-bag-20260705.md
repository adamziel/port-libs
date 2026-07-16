# pandoc-epub-encoded-image-media-bag-20260705

This increment adds a checked-in EPUB/native pair for `encoded-image-media-bag.epub`. The package keeps the OPF manifest href percent-encoded as `images/cover%20art.png` while the ZIP member and media bag entry resolve to `images/cover art.png`.

Counters moved:

- Package/native fixture parity: 75/75 -> 76/76
- Checked-in EPUB/native identity files: 150 -> 152
- Media-bag fixture parity: 6 -> 7
- Media-bag item count: 10 -> 11

The checked-in package/native gate is expected to run with `--require-package-parity=76`, `--require-native-readiness=76`, and `--require-mapped-parity=76`.

The checked-in media-bag gate is expected to run with `--require-media-bag-parity=7` and `--require-media-bag-item-count=11`.

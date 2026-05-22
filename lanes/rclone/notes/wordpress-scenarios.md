# rclone WordPress Scenario

Portable backup/import/export sync for shared hosts and cloud storage providers.

## Current Native Slice

Native in-memory provider contract with advertised hash sets and no-hash provider behavior, object metadata, copy, delete, move, list, ranged/reopenable readers including unknown-size streams and no-low-level-retry sticky errors, cache-backed repeatable readers with upstream limit/buffer constructor semantics, FakeSeeker/NoSeeker reader adapter behavior, PatternReader deterministic fixture bytes, LimitedReadCloser byte-limit and close-error behavior, NoCloseReader close-hiding behavior, GzipReader decompression and provider-close behavior, ContextReader cancellation-before-read behavior, CountingReader streamed-byte accounting, checksum sync plan, case-insensitive provider path lookup, rclone-style path filter rules, hash set/type aliases, multi-hashing, check report sigils, one-way checks, filtered copy-changed planning, no-check-dest transfers, ignore-existing skips, ignore-times unconditional transfers, update-older transfer decisions, modtime-only timestamp repair with no-update-modtime suppression, refresh-times timestamp repair for no-hash providers, immutable modification refusal, backup-dir moves for overwritten and destination-only objects, backup-dir validation, compare-dest skip planning, copy-dest reference copies, suffix and suffix-keep-extension backup names, suffix-only destination backups, filtered destination-only delete planning across rclone delete modes, delete-excluded handling, max-delete/max-delete-size safeguards, checksum manifest parsing and verification including download mode for providers without advertised hashes, `CheckEqualReaders`-style byte comparison for downloaded artifacts, provider-to-provider `CheckDownload` byte/error reporting, ReOpen-style retry/range/seek/readAt/accounting/accounting-error behavior, RepeatableReader-style cached seek/replay/limit behavior, hashsum-style output, `lsf` path/size/hash listings, and `lsjson` list/stat JSON manifests.

## Filtered Backup Example

The fixture in `../fixtures/wordpress-backup-tree.php` models a small WordPress backup set with uploads, cache files, logs, WXR export data, and a SQL dump. The example in `../examples/wordpress-filtered-backup.php` includes uploads plus export/database artifacts while excluding cache, debug logs, and heavyweight design source files before planning changed paths. The current copy-changed test then copies only the included missing/changed artifacts and verifies the next filtered sync is empty.

The checksum and listing slice adds native pieces needed to publish or consume portable backup manifests: md5sum-style checksum files, hashsum output, stdin hash lines, and `lsf`-style path/size/hash listings for filtered WordPress artifacts.

The `../examples/wordpress-lsjson-manifest.php` example emits an rclone-style recursive JSON catalog for portable WordPress backup artifacts with MD5 hashes and metadata, while leaving cache, debug log, and source design files out of the published manifest.

The `../examples/wordpress-checksum-verify.php` example validates a published MD5 manifest against the portable backup set. It uses case-insensitive path matching to model shared-hosting and cloud-provider casing drift while still reporting rclone-style combined check lines.

The `../examples/wordpress-download-checksum-verify.php` example models a provider that does not advertise MD5 or other hashes. Ordinary checksum verification rejects that provider capability, while download mode hashes the portable WordPress backup bytes locally and verifies the same manifest.

The `../examples/wordpress-download-byte-compare.php` example compares restored WXR and SQL artifacts byte-for-byte and shows a corrupted upload object as unequal, matching the native download comparison slice used when checksum metadata is unavailable.

The `../examples/wordpress-provider-download-check.php` example compares two no-hash providers as a restore validation pass. It reports a corrupted uploaded image with `*` and an interrupted database stream with `!`, matching the upstream `CheckDownload` distinction between content differences and download errors.

The `../examples/wordpress-case-insensitive-stat.php` example models an rclone provider that advertises case-insensitive path behavior. Differently-cased upload and database requests resolve to canonical provider paths in `lsjson --stat` output, which is useful when WordPress backup manifests are moved between shared hosts, local filesystems, and cloud providers with different casing rules.

The `../examples/wordpress-reopen-restore.php` example models a transient stream interruption while restoring a WXR export. The native ReOpen reader resumes at the recorded byte offsets and returns the complete artifact, which is the behavior needed for robust WordPress backup restores over flaky provider downloads.

The `../examples/wordpress-unknown-size-reopen-restore.php` example models a cloud provider that reports an unknown object size for a WXR export. The native ReOpen reader keeps retrying with unbounded range opens, restores the complete artifact, and rejects `SeekEnd` for unknown-sized streams like upstream rclone.

The `../examples/wordpress-nonretry-reopen-failure.php` example models a permanent provider-side WXR range failure. The native ReOpen reader surfaces the partial bytes already read, keeps the no-low-level-retry error sticky, and avoids opening another ranged request that upstream rclone would also suppress.

The `../examples/wordpress-repeatable-artifact-scan.php` example models a restore preflight that reads the start of a WXR artifact to identify it, seeks back within the cached prefix, and then streams the full artifact. This maps the upstream repeatable reader behavior needed when a migration tool sniffs or hashes early bytes before handing the same download stream to an importer.

The `../examples/wordpress-repeatable-limited-artifact-scan.php` example adds the upstream limit-buffer constructor behavior. It models a known-length WXR artifact read from a concatenated provider stream, confirms the sniffed header can be replayed, and verifies bytes after the artifact limit are not exposed to the importer.

The `../examples/wordpress-fakeseeker-import-preflight.php` example maps upstream `FakeSeeker` behavior for a known-length but otherwise non-seekable WXR stream. A migration importer can seek to the end before reading to confirm the expected artifact length, rewind to the start, then treat post-read rewind attempts as unsupported like rclone's adapter.

The `../examples/wordpress-pattern-reader-artifact.php` example maps upstream `PatternReader` behavior for deterministic binary fixture generation. A backup smoke test can recreate a generated media artifact from just its length, verify the modulo-251 wrap point, and compare a stable MD5 without storing a large binary fixture in the repo.

The `../examples/wordpress-limited-read-closer-import.php` example maps upstream `LimitedReadCloser` behavior for a fixed-length WXR artifact inside a longer provider stream. It reads only the known WXR member bytes, hides trailing archive bytes from the importer, and ignores a provider cleanup close error after the expected artifact has already been consumed.

The `../examples/wordpress-noclose-upload-body.php` example maps upstream `NoCloser` behavior for WXR upload/request bodies. It keeps a closable provider stream readable while hiding the close method so an HTTP request layer cannot close the underlying stream unexpectedly.

The `../examples/wordpress-gzip-wxr-import.php` example maps upstream `GzipReader` behavior for compressed WXR imports. It decompresses the export body with native zlib and closes the underlying provider stream when the importer is done.

The `../examples/wordpress-cancelled-restore.php` example maps upstream `ContextReader` behavior for canceled restore streams. It reads an initial WXR probe, cancels the import context, then confirms the wrapped provider body is not read again after cancellation.

The `../examples/wordpress-counted-wxr-upload.php` example maps upstream `CountingReader` behavior for streamed WXR upload bodies. It probes the export header, streams the rest of the body, and reports the exact byte count that passed through the request body wrapper.

The `../examples/wordpress-prune-stale-backups.php` example maps a bounded upstream sync/delete behavior. It copies changed included WordPress backup artifacts, deletes stale included destination artifacts such as obsolete uploads and old WXR exports, and leaves excluded cache artifacts untouched unless a future `deleteExcluded` pass explicitly opts into pruning excluded files.

The `../examples/wordpress-prune-delete-limits.php` example maps rclone's destructive delete safeguards for backup cleanup. It plans two stale included artifacts, deletes only the first one with `maxDelete: 1`, surfaces the upstream threshold message, and leaves the next stale upload plus excluded cache artifact in place.

The `../examples/wordpress-backup-dir-prune.php` example maps rclone's `--backup-dir`, `--suffix`, and `--suffix-keep-extension` behavior for WordPress cleanup. It archives a replaced upload and one stale WXR export under a dated backup prefix, then stops before archiving the next stale upload because the max-delete guard fires.

The `../examples/wordpress-copy-dest-backup.php` example maps rclone's `--copy-dest` behavior for WordPress backups. It hydrates included portable artifacts from a warm mirror when they match the source, archives the previous target upload under a dated backup prefix, and leaves excluded cache objects untouched.

The `../examples/wordpress-immutable-archive-sync.php` example maps rclone's `--immutable` behavior for append-only WordPress backup archives. It creates a missing dated SQL artifact, preserves an existing WXR archive, and reports `immutable file modified` if the source later tries to rewrite that existing archive path.

The `../examples/wordpress-update-older-archive-sync.php` example maps rclone's `--update` behavior for WordPress backup artifacts. It refreshes an older SQL dump and a same-window changed upload when checksum mode is enabled, preserves a newer remote WXR recovery export, and leaves excluded cache files untouched.

The `../examples/wordpress-refresh-times-nohash.php` example maps rclone's `--refresh-times` behavior for no-hash providers. It repairs stale WXR and SQL artifact timestamps without replacing their bytes, still copies a missing upload artifact, and leaves excluded cache files untouched.

## Next Task

Map directory modtime handling or metadata update boundaries.

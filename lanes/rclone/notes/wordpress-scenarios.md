# rclone WordPress Scenario

Portable backup/import/export sync for shared hosts and cloud storage providers.

## Current Native Slice

Native in-memory provider contract with advertised hash sets, object metadata, copy, list, ranged/reopenable readers including unknown-size streams and no-low-level-retry sticky errors, cache-backed repeatable readers, checksum sync plan, case-insensitive provider path lookup, rclone-style path filter rules, hash set/type aliases, multi-hashing, check report sigils, one-way checks, filtered copy-changed planning, checksum manifest parsing and verification including download mode for providers without advertised hashes, `CheckEqualReaders`-style byte comparison for downloaded artifacts, provider-to-provider `CheckDownload` byte/error reporting, ReOpen-style retry/range/seek/readAt/accounting/accounting-error behavior, RepeatableReader-style cached seek/replay behavior, hashsum-style output, `lsf` path/size/hash listings, and `lsjson` list/stat JSON manifests.

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

## Next Task

Map `lib/readers RepeatableReader` limit/buffer constructors or another bounded provider contract slice beyond the in-memory checksum/listing/download-check/reopen/repeatable-reader/unknown-size/no-low-level-retry slices.

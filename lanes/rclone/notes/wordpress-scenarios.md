# rclone WordPress Scenario

Portable backup/import/export sync for shared hosts and cloud storage providers.

## Current Native Slice

Native in-memory provider contract with advertised hash sets and no-hash provider behavior, SetTier/GetTier storage-tier updates and `lsf`/`lsjson` tier output, object metadata, object/directory metadata-set updates, server-side copy/move metadata-set propagation, fstest-style object reads with SeekOption/RangeOption semantics, Object.Update path preservation, PutStream and unknown-size source upload/update handling, bounded-depth provider walks, rooted provider path rebasing, subtree purge, PublicLink-style file/directory/root sharing and unlink boundaries, explicit directory metadata/modtimes plus synthetic parent directories, unchecked duplicate-name object entries for dedupe, duplicate directory entries with provider IDs/ParentIDs for dedupe discovery, provider MergeDirs-style directory merging with largest-first duplicate-directory ordering, duplicate-directory list-mode reporting, provider-ID non-list duplicate-directory child rewiring, collision preservation for the file dedupe pass, delayed deepest-first directory modtime/metadata repair after changed child writes, copy, delete, move, list, ranged/reopenable readers including unknown-size streams and no-low-level-retry sticky errors, cache-backed repeatable readers with upstream limit/buffer constructor semantics, FakeSeeker/NoSeeker reader adapter behavior, PatternReader deterministic fixture bytes, LimitedReadCloser byte-limit and close-error behavior, NoCloseReader close-hiding behavior, GzipReader decompression and provider-close behavior, ContextReader cancellation-before-read behavior, CountingReader streamed-byte accounting, checksum sync plan, case-insensitive provider path lookup, ignore-case-sync case-folded match/delete planning plus compare/copy-dest matched-casing behavior on case-sensitive providers without forced renames, fix-case object/directory repair, track-renames hash/modtime/leaf source-only and destination-only candidate matching, provider move/copy feature gates, copy-only move simulation, `ErrorCantMove` copy/delete fallback, fatal tryRename upload/delete fallback, direct and fallback directory moves, single-file `CopyFile`/`MoveFile` same-object no-op, move source deletion, ignore-existing preservation, case-insensitive two-step file casing repair, backup-dir archival, partial-upload temporary cleanup, `RemoveExisting` temporary rename/restore cleanup for provider overwrite workflows, provider server-side copy replacement cleanup, same-remote case-fold copy guards, pre-created destination handle boundaries, rclone-style path filter rules, hash set/type aliases, multi-hashing, by-hash dedupe mode parsing and newest/first/skip/list/interactive keep-quit behavior, by-name dedupe identical cleanup plus newest/skip/rename/size-only/provider-ID/interactive keep-rename boundaries, check report sigils, one-way checks, filtered copy-changed planning, no-check-dest transfers, ignore-existing skips, ignore-times unconditional transfers, update-older transfer decisions, directory DirsEqual-style timestamp comparisons, MkdirModTime/SetDirModTime-style directory timestamp updates, modtime-only timestamp repair with no-update-modtime suppression, refresh-times timestamp repair for no-hash providers, immutable modification refusal, backup-dir moves for overwritten and destination-only objects, backup-dir validation, compare-dest skip planning, copy-dest reference copies, suffix and suffix-keep-extension backup names, suffix-only destination backups, filtered destination-only delete planning across rclone delete modes, delete-excluded handling, max-delete/max-delete-size safeguards, track-renames no-hash fallback and delete-after backup-dir archival, checksum manifest parsing and verification including download mode for providers without advertised hashes, `CheckEqualReaders`-style byte comparison for downloaded artifacts, provider-to-provider `CheckDownload` byte/error reporting, ReOpen-style retry/range/seek/readAt/accounting/accounting-error behavior, RepeatableReader-style cached seek/replay/limit behavior, hashsum-style output, `lsf` path/size/hash/tier listings, and `lsjson` list/stat JSON manifests including explicit directory metadata/modtimes and storage tiers.

The chunksize slice adds native upload chunk-size selection for providers with a maximum multipart part count, including unknown-size streaming fallback, MiB rounding, boundary cases, and deterministic fixed part-range planning for known-size WordPress migration archives.

The sequential chunkedreader slice adds native restore-side range reading for WXR artifacts: a reader opens provider ranges lazily, grows sequential chunks up to a cap, honors one-shot custom `RangeSeek` lengths, and surfaces closed/invalid seek errors like upstream rclone.

The parallel chunkedreader slice adds native restore-side prefetch behavior for known-size WXR artifacts: requested chunks are rounded to rclone's 1 MiB multipart buffer size, the configured stream window is kept prefetched, seeks inside a prefetched range reuse buffered bytes, seeks outside the window restart lazily on the next read, `RangeSeek` ignores custom lengths in parallel mode, and unknown-size objects are rejected for parallel reads.

The parallel cleanup slice adds the remaining provider-failure boundaries needed for robust WXR restores: failed prefetch reads close the failed stream plus already-open ranges, `Close` reports the first provider close failure after closing every prefetched range, and seeks past abandoned ranges ignore close cleanup errors like upstream rclone.

The chunkedreader factory slice adds upstream `New` selection behavior for restore strategy choice: unknown-size streamed WXR exports stay on the sequential reader even when multiple streams are requested, while known-size large WXR/media bundles use the parallel reader with 1 MiB-prefetched provider ranges.

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

The `../examples/wordpress-directory-modtime-sync.php` example maps rclone directory timestamp boundaries for WordPress uploads and incremental export directories. It detects a stale destination upload directory timestamp, copies a changed media object, then applies delayed deepest-first directory metadata repairs from the month directory up through the content root while publishing the source upload directory through `lsjson --metadata`.

The `../examples/wordpress-ignore-case-sync.php` example maps rclone's `--ignore-case-sync` behavior for case-sensitive providers. It treats differently-cased upload and WXR export artifacts as matching without renaming the remote objects, copies only missing portable artifacts, and leaves excluded cache artifacts outside delete planning.

The `../examples/wordpress-ignore-case-copy-dest.php` example maps `--ignore-case-sync` with `--copy-dest` and `--backup-dir`. It hydrates differently-cased upload and WXR export artifacts from a warm mirror, archives the previous remote-cased objects, copies missing portable artifacts at source casing, and keeps excluded cache artifacts outside delete planning.

The `../examples/wordpress-fix-case-sync.php` example maps rclone's `--fix-case` behavior for case-insensitive providers. It repairs differently-cased upload and WXR export paths to the source casing, copies changed or missing portable artifacts, and leaves excluded cache leaf casing untouched except for shared parent directory case changes.

The `../examples/wordpress-track-renames-upload.php` example maps rclone's `--track-renames` behavior for renamed WordPress uploads. It moves a destination-only old upload path to the new source path via provider-side rename, copies missing WXR/SQL artifacts, archives an unmatched stale WXR export with suffix-keep-extension backup-dir handling, and leaves excluded cache objects untouched.

The `../examples/wordpress-provider-move-gates.php` example maps provider move/copy feature gates. It repairs a renamed upload on a provider that lacks direct server-side move but supports server-side copy, then archives an upload month directory through object-move fallback when direct directory moves are unavailable.

The `../examples/wordpress-single-file-move-copy.php` example maps single-file `MoveFile`/`CopyFile` boundaries. It repairs only the casing of a remote upload, preserves a local WXR recovery export when `--ignore-existing` sees an existing remote artifact, and confirms a failed partial upload cleans its temporary object while leaving the prior remote export intact.

The `../examples/wordpress-remove-existing-overwrite.php` example maps rclone's `RemoveExisting` helper for provider overwrite workflows. A long WXR export path is moved to a truncated temporary name before replacement, successful provider copy cleanup deletes the saved old export, and a simulated provider copy failure restores the previous export while preserving the original error.

The `../examples/wordpress-server-side-copy-replace.php` example maps backend server-side copy callers that wrap provider copies with `RemoveExisting`. It publishes a fresh WXR export over an old one, reports the same-lowercase provider guard for case-only source/destination copies, and confirms a failed pre-created destination handle does not leave a placeholder object.

The `../examples/wordpress-provider-copy-metadata.php` example maps provider-specific server-side copy result handling. It preserves OneDrive source modtimes and add-only permission metadata after async copy, refreshes Yandex `rclone_modified`/MD5 metadata after copy, and restores the previous WXR export when OneDrive translates an async access-denied copy job into rclone's `can't copy object - incompatible remotes` boundary.

The `../examples/wordpress-dedupe-hash-archives.php` example maps `rclone dedupe --by-hash --dedupe-mode newest` for portable backup archives. It removes older duplicate WXR copies that share a provider hash, keeps the newest export, and leaves the unrelated SQL backup artifact in place.

The `../examples/wordpress-dedupe-duplicate-names.php` example maps `rclone dedupe` by duplicate remote name for providers that can list multiple objects at the same path. It first removes identical same-name WXR exports, then renames the remaining conflicting exports to numbered names while skipping an existing numbered export.

The `../examples/wordpress-interactive-dedupe-review.php` example maps rclone's interactive dedupe prompt semantics without requiring terminal input. It first removes identical same-name WXR exports, then keeps a reviewer-selected recovered draft export and deletes the older conflicting export.

The `../examples/wordpress-dedupe-duplicate-upload-dirs.php` example maps rclone's duplicate-directory merge pre-pass. It keeps the larger published upload month as the merge target, moves a smaller restored duplicate directory into it, and then renames colliding media objects for manual review.

The native duplicate-directory list-mode discovery path models a provider that returns two upload month directories at the same remote path with distinct provider IDs. It reports the duplicate directory group through ParentID-linked counts without merging or deleting any upload entries.

The `../examples/wordpress-provider-id-duplicate-upload-dirs.php` example maps non-list duplicate-directory dedupe for providers that expose same-path directories with distinct IDs. It keeps the largest published upload month ID, rewires recovered source children to that ID, removes the recovered directory ID from future discovery, and leaves same-name media collisions for the later file dedupe pass.

The `../examples/wordpress-fstest-object-open-update.php` example maps fstest-style provider object boundaries for WXR exports. It reads a leading preview range, fetches the final `</rss>` suffix with a negative-start range, updates the existing export without adopting a temporary source name, and accepts an unknown-size streamed import body while reporting the stored byte length.

The `../examples/wordpress-rooted-upload-purge.php` example maps fstest-style rooted provider listing and purge boundaries for WordPress uploads. It lists direct media files and generated thumbnail directories relative to a monthly upload root, purges a thumbnail subtree through the rooted view, and preserves adjacent upload months plus WXR exports in the same backing provider.

The `../examples/wordpress-public-link-share.php` example maps fstest-style `PublicLink` behavior for WordPress migration handoffs. It publishes a WXR export link, shares a rooted upload-month directory link, treats missing exports as errors, and unlinks the WXR share while leaving the provider objects intact.

The `../examples/wordpress-metadata-set-copy-move.php` example maps fstest-style metadata-set behavior for WordPress migration handoffs. It copies a WXR export and moves a temporary upload into its final path while stamping review metadata, `content-type`, and handoff `mtime` on the destination artifacts without mutating the copied source export.

The `../examples/wordpress-settier-archive.php` example maps fstest/operations SetTier/GetTier behavior for WordPress archival workflows. It applies an archive tier to filtered portable WXR, SQL, and upload artifacts, leaves cache/log/source-design files on their original hot tier, and exposes the resulting tiers through both `lsf --format pT`-style output and `lsjson` stat output.

The `../examples/wordpress-chunked-archive-upload.php` example maps upstream `fs/chunksize.Calculator` behavior for a large consolidated WordPress migration archive. It raises a 5 MiB default chunk size to 12 MiB so a 120,864,818,840-byte WXR/SQL/uploads archive stays inside a 10,000-part provider limit, while unknown-size streaming uploads retain the configured default chunk size.

The `../examples/wordpress-chunked-wxr-restore.php` example maps upstream sequential `fs/chunkedreader` behavior for WXR restores. It reads an initial header chunk, continues through a grown chunk range, then lazily seeks to the closing `</rss>` range without reopening the provider until bytes are requested.

The `../examples/wordpress-parallel-chunked-wxr-restore.php` example maps upstream parallel `fs/chunkedreader` behavior for larger WXR restores. It opens two provider ranges up front, reads across the 1 MiB boundary using the prefetched second range, then seeks to the closing `</rss>` tail without another provider open. The cleanup tests cover the provider-failure side of that same restore path without shelling out to rclone.

The `../examples/wordpress-chunked-reader-factory.php` example maps upstream `fs/chunkedreader.New` selection for WordPress restores. It routes an unknown-size streamed WXR import to sequential range reads and routes a known-size larger WXR/media bundle to parallel range prefetching.

The `../examples/wordpress-listp-batched-manifest.php` example maps upstream `fs/list.Helper` batching for large WordPress backup catalogs. It publishes a 104-entry WXR/SQL/uploads manifest through the upstream 100-entry ListR callback threshold, leaving the final four entries to be sent by `Flush`.

The `../examples/wordpress-list-filter-sort.php` example maps upstream `fs/list.filterAndSortDir` behavior for direct provider listings. It publishes only WXR, SQL, and upload-directory entries from a `site-backups` listing, prunes cache/debug entries through object and directory callbacks, ignores nested provider leaks that do not belong in the direct directory result, and sorts the remaining entries in rclone Remote order.

## Next Task

Map `fs/list.Sorter` identity and key-function ordering behavior, then decide whether an in-memory-only sorter is enough or a bounded external-sort error surface is worth porting.

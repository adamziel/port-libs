# rclone Upstream Inventory

- Upstream: `https://github.com/rclone/rclone`
- Commit: `28d6b0b7b906da70afdc036ba5bb21f3c86613b8`
- Cache: `.upstream-cache/rclone`
- Method: shallow clone with `--filter=blob:none --depth=1`; denominator counted from `git ls-tree -r --name-only HEAD` plus targeted reads of `Makefile`, `COPYING`, `fs/filter/glob.go`, `fs/filter/filter.go`, `fs/filter/*_test.go`, `fs/hash/hash_test.go`, `fs/operations/check.go`, `fs/operations/check_test.go`, `fs/operations/reopen.go`, `fs/operations/reopen_test.go`, `fs/operations/operations.go`, `fs/operations/operations_test.go`, `fs/operations/copy_test.go`, `fs/sync/sync.go`, `fs/sync/sync_test.go`, `fs/accounting/stats.go`, `fs/deletemode.go`, `docs/content/docs.md`, `lib/readers/readfill.go`, `lib/readers/readfill_test.go`, `lib/readers/error.go`, `lib/readers/error_test.go`, `lib/readers/repeatable.go`, `lib/readers/repeatable_test.go`, `lib/readers/fakeseeker.go`, `lib/readers/fakeseeker_test.go`, `lib/readers/noseeker.go`, `lib/readers/noseeker_test.go`, `lib/readers/pattern_reader.go`, `lib/readers/pattern_reader_test.go`, `lib/readers/limited.go`, `lib/readers/noclose.go`, `lib/readers/noclose_test.go`, `lib/readers/gzip.go`, `lib/readers/gzip_test.go`, `lib/readers/context.go`, `lib/readers/context_test.go`, `lib/readers/counting_reader.go`, and selected command files.
- Focused listing reads: `cmd/lsjson/lsjson.go`, `fs/operations/lsjson.go`, and `fs/operations/lsjson_test.go`, including the `StatJSON` branch that compares directory entries with `strings.EqualFold` when the provider advertises `CaseInsensitive`.
- Focused checksum/reader reads: `cmd/checksum/checksum.go`, `cmd/hashsum/hashsum.go`, `fs/operations/check.go`, `fs/operations/check_test.go`, `lib/readers/readfill.go`, `lib/readers/readfill_test.go`, `lib/readers/error.go`, `lib/readers/error_test.go`, `lib/readers/repeatable.go`, `lib/readers/repeatable_test.go`, `lib/readers/fakeseeker.go`, `lib/readers/fakeseeker_test.go`, `lib/readers/noseeker.go`, `lib/readers/noseeker_test.go`, `lib/readers/pattern_reader.go`, `lib/readers/pattern_reader_test.go`, `lib/readers/limited.go`, `lib/readers/noclose.go`, `lib/readers/noclose_test.go`, `lib/readers/gzip.go`, `lib/readers/gzip_test.go`, `lib/readers/context.go`, `lib/readers/context_test.go`, and `lib/readers/counting_reader.go`, including `TestCheckDownload`, `TestCheckEqualReaders`, `TestCheckSumDownload`, `TestReadFill`, `TestErrorReader`, `TestRepeatableReader`, `TestFakeSeeker`, `TestFakeSeekerError`, `TestNoSeeker`, `TestPatternReader`, `TestPatternReaderSeek`, `TestNoCloser`, `TestGzipReader`, `TestContextReader`, the `NewRepeatableReaderSized`, `NewRepeatableLimitReader`, `NewRepeatableReaderBuffer`, and `NewRepeatableLimitReaderBuffer` source constructors, the `NewLimitedReadCloser`/`LimitedReadCloser.Close` branches, the `NoCloser` close-hiding wrapper, the `NewGzipReader` close wrapper, the `NewContextReader` cancellation wrapper, and the `NewCountingReader` byte counter source. No dedicated upstream `counting_reader_test.go` exists at this commit.
- Focused reopen reads: `fs/operations/reopen.go` and `fs/operations/reopen_test.go`, including retry-at-offset behavior, range and seek options, unknown-size range streams, `SeekEnd` rejection for unknown-sized objects, `ReadAt`, close-after-error state, and delayed accounting.
- Focused low-level retry reads: `fs/fserrors/error.go` and the `fserrors.IsNoLowLevelRetryError` branch in `fs/operations/reopen.go`, where no-low-level-retry read errors are made sticky and are not reopened.
- Focused sync/delete/backup-dir reads: `fs/deletemode.go`, `fs/sync/sync.go`, `fs/sync/sync_test.go`, `fs/operations/operations.go`, `fs/operations/operations_test.go`, `fs/operations/copy_test.go`, `fs/accounting/stats.go`, `cmd/delete/delete.go`, `cmd/purge/purge.go`, and `cmd/cleanup/cleanup.go`, including destination-only delete planning, delete mode constants, copy-not-delete behavior, filtered destination behavior, `--delete-excluded`, `--max-delete`, `--max-delete-size`, `BackupDir`, `MoveBackupDir`, `SuffixName`, suffix-only backups, suffix-keep-extension naming, and destructive command boundaries.
- Focused compare/copy-dest reads: targeted `fs/sync/sync.go`, `fs/sync/sync_test.go`, `fs/operations/operations.go`, and `fs/operations/copy_test.go` reads covering `CompareOrCopyDest`, `compareDest`, `copyDest`, `GetCompareDest`, `GetCopyDest`, `BackupDir` validation, `TestSyncCompareDest`, `TestSyncMultipleCompareDest`, `TestSyncCopyDest`, `TestCopyFileCompareDest`, and `TestCopyFileCopyDest`.
- Focused transfer-decision reads: targeted `fs/sync/sync.go`, `fs/sync/sync_test.go`, `fs/operations/operations.go`, `fs/operations/operations_test.go`, `fs/march/march.go`, `fs/fs.go`, and `docs/content/docs.md` reads covering `--ignore-existing`, `--immutable`, `--no-check-dest`, `--ignore-times`, `--update`, `--no-update-modtime`, `NeedTransfer`, `Equal`, `ErrorImmutableModified`, destination listing bypasses, `TestSyncIgnoreExisting`, `TestMoveWithIgnoreExisting`, `TestMoveFileWithIgnoreExisting`, `TestSyncImmutable`, `TestSyncIgnoreTimes`, `TestSyncAfterChangingModtimeOnly`, `TestSyncAfterChangingModtimeOnlyWithNoUpdateModTime`, and `TestSyncWithUpdateOlder`.
- Focused refresh-times reads: targeted `fs/operations/operations.go`, `fs/sync/sync.go`, `fs/config.go`, and `docs/content/docs.md` reads covering `--refresh-times`, the `hash.None && !ci.RefreshTimes` branch in `Equal`, destination timestamp update with absent hashes, common-hash mismatch transfer behavior, and the documented no-effect boundary for size-only/checksum sync modes.

## Counted Test-Related Inventory

- Total upstream repository files at the commit: 2,553
- Go test files: 327
- Backend Go test files: 124
- `fs/` Go test files: 91
- `cmd/` Go test files: 47
- `lib/` Go test files: 43
- `vfs/` Go test files: 19
- `cmdtest/` Go test files: 2
- `fstest` paths: 61
- `fstest` Go test files: 1
- Filter Go test files: 2
- Hash Go test files: 1
- Testdata paths: 836
- Test directory paths: 35
- Integration-related paths counted by path: 3
- Script paths and shell helpers: 43
- Focused `lsjson` static inventory: 2 upstream Go test functions (`TestListJSON`, `TestStatJSON`), 24 named/subtest table cases counted from `fs/operations/lsjson_test.go`, and 11 command flags/options declared in `cmd/lsjson/lsjson.go`.
- Focused case-insensitive `StatJSON` rule: 1 implementation branch in `fs/operations/lsjson.go` maps provider `Features().CaseInsensitive` to case-folded directory-entry matching.
- Focused checksum/check static inventory: 9 upstream Go test functions in `fs/operations/check_test.go`, including 9 `ParseSumFile` line samples over LF/CRLF, 14 `CheckSum` named runs across normal and download modes, 7 `CheckDownload` table runs, 12 `CheckEqualReaders` byte/error fixtures, 10 `ApplyTransforms` path-normalization scenarios, and checksum/hashsum command boundaries in `cmd/checksum/checksum.go` and `cmd/hashsum/hashsum.go`.
- Focused `lib/readers` static inventory: 20 reader helper paths, 9 reader Go test files, 1 `TestReadFill` function with 3 count/error scenarios, 1 `TestErrorReader` function confirming sentinel read-error propagation, 1 `TestRepeatableReader` function with 10 read/cache/seek scenarios, 5 `RepeatableReader` constructor functions including 4 limit/buffer variants, 2 `FakeSeeker` test functions covering pass-through read-seeker wrapping, pre-read `SeekStart`/`SeekCurrent`/`SeekEnd`, non-start read rejection, post-read seek rejection, and sticky EOF/read errors, 1 `NoSeeker` test function covering delegated reads and the upstream `can't Seek` error, 2 `PatternReader` test functions covering zero/ten-byte lengths, modulo-251 byte emission, `SeekStart`/`SeekCurrent`/`SeekEnd`, invalid whence, and negative-position errors, 1 `LimitedReadCloser` source file covering negative-limit passthrough and the close-error suppression branch after complete limited reads, 1 `TestNoCloser` function covering nil passthrough, read-only passthrough, closable-reader wrapping, hidden close, and delegated read errors, 1 `TestGzipReader` function covering gzip decompression and underlying stream close behavior, 1 `TestContextReader` function covering a successful first read followed by `context.Canceled` without additional underlying reads, plus 1 untested `CountingReader` source helper that wraps `io.Reader` and increments `BytesRead` by the returned byte count.
- Focused ReOpen static inventory: 1 upstream Go test function in `fs/operations/reopen_test.go`, 4 mode runs (`Normal`, `WithRangeOption`, `WithSeekOption`, `UnknownSize`), 36 subtest executions across basics, immediate open failure, transient read failures, too many retries, `ReadAt`, `Seek`, unknown-size positive seek and `SeekEnd` error behavior, accounting, delayed accounting, and accounting-error behavior, plus 3 interface assertions for read/seek/close, reader-at, and delay-accounting contracts.
- Focused no-low-level-retry static inventory: 1 upstream error marker constructor (`NoLowLevelRetryError`), 1 marker detection function (`IsNoLowLevelRetryError`), and 1 ReOpen read branch that suppresses low-level reopen attempts when the marker is present.
- Focused sync/delete static inventory: 5 delete mode constants in `fs/deletemode.go`; 10 focused sync delete/filter test functions in `fs/sync/sync_test.go` (`TestSyncAfterRemovingAFileAndAddingAFileDryRun`, `TestSyncAfterRemovingAFileAndAddingAFile`, `TestSyncAfterRemovingAFileAndAddingAFileSubDir`, `TestSyncAfterRemovingAFileAndAddingAFileSubDirWithErrors`, `TestSyncDeleteAfter`, `TestSyncDeleteDuring`, `TestSyncDeleteBefore`, `TestCopyDeleteBefore`, `TestSyncWithExclude`, and `TestSyncWithExcludeAndDeleteExcluded`); 6 focused operations delete/purge/rmdirs test functions in `fs/operations/operations_test.go`; and 3 destructive command files (`delete`, `purge`, `cleanup`) read for command boundaries.
- Focused max-delete static inventory: 3 upstream operations tests (`TestMaxDelete`, `TestMaxDeleteSizeLargeFile`, `TestMaxDeleteSize`), 2 guard branches in `fs/accounting.StatsInfo.DeleteFile`, and 2 documented flags in `docs/content/docs.md`. The guard checks happen before deletion, `--max-delete-size` is cumulative, and negative object sizes are counted as zero.
- Focused backup-dir static inventory: 8 upstream test functions across `fs/sync/sync_test.go`, `fs/operations/copy_test.go`, and `fs/operations/operations_test.go` (`TestSyncBackupDir`, `TestSyncBackupDirWithSuffix`, `TestSyncBackupDirWithSuffixKeepExtension`, `TestSyncBackupDirSuffixOnly`, `TestSyncSuffix`, `TestSyncSuffixKeepExtension`, `TestCopyFileBackupDir`, and `TestMoveFileBackupDir`); 5 implementation functions/branches (`BackupDir`, `MoveBackupDir`, `SuffixName`, `DeleteFileWithBackupDir`, and `DeleteFilesWithBackupDir`); and 2 suffix-keep-extension modes including multi-extension handling.
- Focused compare/copy-dest static inventory: 5 upstream test functions (`TestSyncCompareDest`, `TestSyncMultipleCompareDest`, `TestSyncCopyDest`, `TestCopyFileCompareDest`, and `TestCopyFileCopyDest`); 5 implementation functions (`CompareOrCopyDest`, `compareDest`, `copyDest`, `GetCompareDest`, and `GetCopyDest`); 6 `BackupDir` validation branches for same remote, destination/source overlap, same-dir single-file backups, suffix-only destination backups, internal empty config, and missing server-side move/copy support; and 2 copy-dest/backup-dir interaction points where overwritten destination objects are moved before the reference object is copied into place.
- Focused `--ignore-existing` / `--immutable` / `--no-check-dest` / `--ignore-times` / `--update` / `--no-update-modtime` static inventory: 8 upstream test functions across `fs/sync/sync_test.go` and `fs/operations/operations_test.go` (`TestSyncIgnoreExisting`, `TestMoveWithIgnoreExisting`, `TestMoveFileWithIgnoreExisting`, `TestSyncImmutable`, `TestSyncIgnoreTimes`, `TestSyncAfterChangingModtimeOnly`, `TestSyncAfterChangingModtimeOnlyWithNoUpdateModTime`, and `TestSyncWithUpdateOlder`); 5 transfer-decision implementation points (`NeedTransfer`, `Equal`, `syncCopyMove.pairChecker`, `CopyFile` / `MoveFile` destination lookup, and `fs/march` destination bypass); 2 no-check-dest destination bypass branches in `fs/march/march.go`; 1 exported immutable error sentinel (`ErrorImmutableModified`); 4 modtime/update branches in `NeedTransfer`/`Equal`; and 6 documented flag sections in `docs/content/docs.md`.
- Focused `--refresh-times` static inventory: 1 config flag in `fs/config.go`, 1 documented flag section in `docs/content/docs.md`, 1 `hash.None && !ci.RefreshTimes` decision branch in `operations.Equal`, and 2 native boundary cases mapped here: absent common hashes update destination modtime without transfer when `refreshTimes` is enabled, while a common hash mismatch still transfers source bytes.

## Runner Status

The prior missing Go/module/tooling blocker is resolved for a bounded upstream runner. On 2026-05-22, this lane installed Go 1.25.0 under `/home/claude/.local/go-toolchains/go1.25.0`, rebuilt the upstream binary at `28d6b0b`, supplied `rsync` 3.4.1 through a local Fedora RPM extraction and wrapper, and used a temporary mount-namespace overlay for `/etc/mime.types`.

The focused cleanup command passed for the packages that previously exposed local environment gaps:

```text
go test -skip '^TestIntegration' ./backend/huaweidrive ./cmd/gitannex ./cmd/serve/http ./fs/accounting ./fs/logger
```

The broad bounded command then passed for 299 upstream Go packages:

```text
GOFLAGS='-p=1' go test -timeout 20m -skip '^TestIntegration' $(go list ./... | grep -Ev '^github.com/rclone/rclone/cmd/(mount|mount2|serve/docker)$')
```

Environment notes for the passing run:

- `go version go1.25.0 linux/amd64`
- `rclone v1.75.0-beta.1.28d6b0b`, statically linked from the upstream checkout
- `rsync 3.4.1`
- `TZ=UTC`, `CI=true`, `RCLONE_CONFIG=/notfound`
- UID 1001 after dropping namespace capabilities, so permission-denial tests still run as an ordinary user
- temporary `/etc/mime.types` overlay inside the mount namespace only, without modifying the host `/etc`

This is bounded upstream runner evidence, not full provider/mount parity. Live-service provider tests were skipped with `-skip '^TestIntegration'`. `cmd/mount`, `cmd/mount2`, and `cmd/serve/docker` were excluded because they require FUSE/container mount permissions. Full `make quicktest` / `go test ./...` parity and `make test` / `fstest/test_all` provider parity remain open for a later environment that can run the live-service and mount fixtures.

## Mapped Native Slice

The PHP slice maps selected semantics from `fs/filter/glob_test.go`, `fs/filter/filter_test.go`, and existing lane checksum planning:

- Path glob conversion for `*`, `**`, `?`, leading `/`, brace alternation, regexp escapes, and invalid glob detection.
- First-match include/exclude rule ordering with `!` rule reset.
- Case-insensitive matching via the upstream `ignore_case` option.
- Sync planning that skips objects excluded by rclone-style filters.
- WordPress backup planning that includes uploads, WXR exports, and SQL dumps while excluding cache/log/source files.
- Destination-only delete planning for rclone sync modes `off`, `before`, `during`, `after`/default, and `only`, with the same final candidate set for before/during/after/only and no deletion in off mode.
- Filtered destination deletion: excluded destination objects are not deleted by default, but `deleteExcluded` includes destination entries ignored by the normal filter, matching the upstream `SyncWithExclude` and `SyncWithExcludeAndDeleteExcluded` boundary.
- Copy remains copy-only: changed/missing source objects are copied without pruning destination-only files until the explicit delete planning pass, matching the upstream `CopyDeleteBefore` boundary.
- WordPress backup pruning copies included uploads/WXR/SQL artifacts and deletes stale included remote artifacts while leaving excluded cache/log/source artifacts untouched.
- `fs/accounting DeleteFile` max-delete safeguards: deletion stops before the next object when `deletes+1` would exceed `--max-delete`, and the upstream threshold message is surfaced.
- `fs/accounting DeleteFile` max-delete-size safeguards: deletion stops before the next object when cumulative deleted bytes plus the candidate size would exceed `--max-delete-size`; unknown/negative sizes count as zero like upstream.
- WordPress backup cleanup can now enforce a max-delete guard, removing the first stale included artifact while leaving the next stale backup artifact and excluded cache files in place for review.
- `fs/operations MoveBackupDir` semantics: overwritten destination objects and destination-only delete candidates are moved into a backup directory before replacement/removal, preserving the old bytes and overwriting prior backup objects at the same path.
- `fs/operations SuffixName` semantics: backup names can append a suffix after the whole path, or before the kept extension when suffix-keep-extension is enabled; the PHP slice includes the upstream `file.tar.gz` versus `file.badextension.gz` distinction.
- Suffix-only backup behavior: when no backup directory is configured but a suffix is present, the destination acts as the backup location, so caller filters must exclude the suffix to prevent backup files from being selected for deletion in a later sync.
- WordPress backup cleanup can archive overwritten uploads and stale WXR exports under a dated backup prefix with suffix-keep-extension naming while still respecting max-delete before the next destructive move.
- `fs/operations BackupDir` validation: configured backup roots must be on the same remote as the destination, must not overlap source or destination roots for sync, cannot be the same source/destination directory for single-file backup without a suffix, suffix-only backups use the destination root, and backup remotes must support server-side move or copy.
- `fs/operations CompareOrCopyDest` compare-dest behavior: matching reference objects suppress source transfers while leaving an old or missing destination unchanged.
- Multiple `--compare-dest` references are consulted in order until one equal reference object is found.
- `fs/operations CompareOrCopyDest` copy-dest behavior: matching reference objects are copied to the destination instead of uploading source bytes, and stale references fall back to the source transfer path.
- `--copy-dest` plus `--backup-dir` moves an overwritten destination object to the backup root before copying the equal reference object into place.
- WordPress backup sync can hydrate missing or replaced portable artifacts from a warm mirror while archiving the previous target upload and preserving excluded cache objects.
- `--ignore-existing` transfer decisions skip any source object that already exists at the destination, even when the destination bytes differ, while still copying missing source objects.
- `--immutable` transfer decisions create missing destination objects but refuse to replace a differing existing destination object with the upstream `immutable file modified` error, leaving the destination bytes unchanged.
- `--no-check-dest` copy-style decisions do not consult the destination before transfer, so every included source object is sent and existing destination bytes are overwritten in the in-memory provider without backup-dir archival because no destination object was checked.
- WordPress append-only archive sync can create a missing dated SQL artifact while preserving an existing WXR backup and surfacing `immutable file modified` if the source later rewrites that backup path.
- `--ignore-times` transfer decisions send existing destination objects unconditionally, even when size, hash, and modtime already match.
- Default modtime-only differences with equal size and hash update the destination timestamp without transferring bytes; `--no-update-modtime` suppresses that timestamp repair.
- `--update` / `--update-older` skips destination objects newer than source, transfers older destination objects when size/hash differ, and treats near-equal mtimes as size-only unless `--checksum` is set.
- WordPress update-older archive sync can refresh stale SQL and same-window changed upload artifacts while preserving a newer remote WXR recovery export and excluded cache files.
- `--refresh-times` repairs destination timestamps without transferring when source and destination have equal size, different modtimes, and no common hashes; without the flag, the same no-hash condition transfers because upstream cannot prove equality safely.
- `--refresh-times` still transfers when a common provider hash exists and differs, matching the upstream branch that only accepts absent hashes when refresh-times is enabled.
- WordPress no-hash archive sync can repair WXR and SQL artifact timestamps while preserving existing bytes and still copying a missing upload artifact.
- `fs/operations` / `cmd/lsjson` list and stat JSON shapes for paths, names, sizes, directory entries, recursive listings, file-only and dir-only modes, omitted modtime/mimetype fields, selected hash output, and metadata.
- Case-insensitive provider `StatJSON` lookup: differently-cased file and directory requests resolve to the provider's canonical object or directory path while still honoring file-only and dir-only filters.
- `fs/operations CheckSum`-style verification of parsed SUM files against provider objects, including match, differ, file-only-in-provider, file-only-in-sum, one-way, filter, duplicate-sum, mixed-case hash, and case-insensitive path transform behavior.
- `fs/operations CheckSum --download` behavior where ordinary checksum mode rejects a provider that does not advertise the requested hash, while download mode hashes object bytes locally and preserves the same match/differ/missing reports.
- `fs/operations CheckEqualReaders` plus `lib/readers ReadFill` behavior for 64 KiB chunk filling, equal byte streams, byte differences, length differences, and read-error precedence before byte-difference reporting.
- `fs/operations CheckDownload` provider-to-provider verification: size differences become ordinary `*` differences before download, equal-size objects are compared byte-for-byte, and open/read failures are reported through `!` error lines with failed-download wrapping.
- `fs/operations ReOpen` retry/range reader behavior: initial open failures, reopen after transient read failures at the already-read offset, inclusive range end handling, seek-offset starts, `ReadAt` without changing the current position, seek bounds, closed-reader errors, and delayed accounting.
- `fs/operations ReOpen` no-low-level-retry and accounting-error behavior: no-low-level-retry read errors are returned without opening a new ranged reader, remain sticky for subsequent reads, and accounting callback errors are propagated without retrying the underlying object stream.
- `fs/operations ReOpen` unknown-size mode: objects reporting size `-1` can stream from a range start without a bounded length, reopen retries keep using unbounded range reads, positive seeks past the actual byte length are accepted, and `SeekEnd` returns the upstream unknown-size seek error.
- `lib/readers RepeatableReader` cache-backed read/seek behavior: bytes read from the underlying reader are cached, cached prefixes can be replayed after seeking, seeking beyond the cached prefix is rejected, and `SeekCurrent`/`SeekEnd` are resolved against the cached data.
- `lib/readers RepeatableReader` limit/buffer constructor behavior: limit constructors stop reading at the upstream `io.LimitReader` byte count, buffer constructors treat the supplied byte slice as zero-length capacity rather than preloaded cache, and the combined limit-buffer constructor maps both behaviors.
- `lib/readers FakeSeeker` and `NoSeeker` provider-contract behavior: existing read-seekers are passed through, non-seekable readers can be seeked before any bytes are read to discover known length, reads after a non-zero pre-read seek fail until rewound, all seeks after data is read fail, EOF/read errors remain sticky, and `NoSeeker` delegates reads while rejecting every seek with the upstream `can't Seek` error.
- `lib/readers PatternReader` deterministic fixture behavior: byte `i` is emitted as `i % 251`, the next byte resets after `SeekStart`, `SeekCurrent`, and `SeekEnd`, past-end seeks are accepted and read as EOF, and invalid whence or negative positions raise upstream-shaped errors.
- `lib/readers LimitedReadCloser` byte-limit and close behavior: non-negative limits cap reads to the upstream `io.LimitedReader` byte count, negative limits pass the original reader through, close errors propagate while bytes remain unread, and close errors are ignored after the limited body is fully consumed.
- `lib/readers NoCloser` close-hiding behavior: nil is returned unchanged, read-only readers are returned unchanged, readers that also expose `Close` are wrapped so `Close` is no longer visible, and read errors still propagate from the underlying reader.
- `lib/readers GzipReader` close behavior: gzip streams are decompressed with native PHP zlib, `Close` closes the underlying provider body because the gzip layer alone would not, and provider close errors take precedence.
- `lib/readers ContextReader` cancellation behavior: the context error is checked before each read, successful reads delegate unchanged before cancellation, and canceled reads return the context error without advancing the underlying stream.
- `lib/readers CountingReader` byte-accounting behavior: each successful read delegates to the underlying reader and increments `BytesRead` by the number of bytes actually returned; failed reads that return no bytes do not advance the count in the PHP exception model.

## Current PHP Verification

- Rclone-only PHP lane check on 2026-05-22 before the no-low-level-retry/accounting-error slice: 38 tests, 280 assertions, 0 failures.
- Rclone-only PHP lane check on 2026-05-22 after the no-low-level-retry/accounting-error slice: 40 tests, 289 assertions, 0 failures.
- Rclone-only PHP lane check on 2026-05-22 after the RepeatableReader limit/buffer constructor slice: 43 tests, 310 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the RepeatableReader limit/buffer constructor slice: 73 test files, 4,155 assertions, 0 failures in the current shared dirty worktree.
- Rclone-only PHP lane check on 2026-05-22 after the FakeSeeker/NoSeeker slice: 5 rclone test files, 48 tests, 331 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the FakeSeeker/NoSeeker slice: 75 test files, 4,264 assertions, 2 failures in unrelated `lanes/esbuild/tests/TypeScriptModuleLowererTest.php` const-enum formatting tests; the rclone lane tests in that run all passed.
- Rclone-only PHP lane check on 2026-05-22 after the PatternReader slice: 6 rclone test files, 52 tests, 1,373 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the PatternReader slice: 78 test files, 5,431 assertions, 1 failure in unrelated `lanes/readability/tests/ArticleExtractorTest.php` nested wrapper simplification; the rclone lane tests in that run all passed.
- Rclone-only PHP lane check on 2026-05-22 after the LimitedReadCloser slice: 7 rclone test files, 57 tests, 1,386 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the LimitedReadCloser slice: 80 test files, 5,390 assertions, 22 failures in unrelated `lanes/readability/tests/ArticleExtractorTest.php`, all reporting missing `PortLibs\Readability\ArticleExtractor::effectiveBaseUri()`; the rclone lane tests in that run all passed.
- Rclone-only PHP lane check on 2026-05-22 after the NoCloseReader slice: 7 rclone test files, 60 tests, 1,394 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the NoCloseReader slice: 82 test files, 5,679 assertions, 2 failures in unrelated `lanes/dolt/tests/StatusTableTest.php` status row ordering/ignored-row key order checks; the rclone lane tests in that run all passed.
- Rclone-only PHP lane check on 2026-05-22 after the GzipReader slice: 8 rclone test files, 64 tests, 1,404 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the GzipReader slice: 86 test files, 5,779 assertions, 7 failures in unrelated `lanes/esbuild/tests/TypeScriptNamespaceLowererTest.php`, all reporting missing `PortLibs\Esbuild\TypeScriptNamespaceLowerer::namespaceStatementAt()`; the rclone lane tests in that run all passed.
- Rclone-only PHP lane check on 2026-05-22 after the ContextReader slice: 9 rclone test files, 67 tests, 1,412 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the ContextReader slice: 89 test files, 6,056 assertions, 0 failures in the current shared dirty worktree.
- Rclone-only PHP lane check on 2026-05-22 after the CountingReader slice: 10 rclone test files, 71 tests, 1,432 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the CountingReader slice: 93 test files, 6,257 assertions, 0 failures in the current shared dirty worktree.
- Rclone-only PHP lane check on 2026-05-22 after the sync delete-planning slice: 11 rclone test files, 75 tests, 1,452 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the sync delete-planning slice: 95 test files, 6,292 assertions, 1 failure in unrelated `lanes/pandoc/tests/MarkdownReaderTest.php` (`PortLibs\Pandoc\MarkdownReader::parseLinkDestinationAndTitle()` missing); all rclone tests in that root run passed.
- Rclone-only PHP lane check on 2026-05-22 after the max-delete/max-delete-size slice: 11 rclone test files, 78 tests, 1,467 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the max-delete/max-delete-size slice: 98 test files, 6,487 assertions, 7 failures in unrelated lanes: missing Dolt fixture `lanes/dolt/fixtures/wp-procedure-history.php` and six `lanes/esbuild/tests/TypeScriptNamespaceLowererTest.php` namespace lowering expectation failures. All rclone tests in that root run passed.
- Required root `php tools/run-tests.php` rerun on 2026-05-22 after concurrent Dolt/Esbuild lane updates landed: 98 test files, 6,532 assertions, 0 failures in the current shared dirty worktree.
- Rclone-only PHP lane check on 2026-05-22 after the backup-dir/suffix slice: 11 rclone test files, 82 tests, 1,491 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the backup-dir/suffix slice: 100 test files, 6,747 assertions, 0 failures in the current shared dirty worktree.
- Rclone-only PHP lane check on 2026-05-22 after the compare-dest/copy-dest/backup validation slice: 11 rclone test files, 88 tests, 1,518 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the compare-dest/copy-dest/backup validation slice: 103 test files, 7,052 assertions, 0 failures in the current shared dirty worktree.
- Rclone-only PHP lane check on 2026-05-22 after the no-check-dest/ignore-existing/immutable slice: 11 rclone test files, 91 tests, 1,534 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the no-check-dest/ignore-existing/immutable slice: 104 test files, 7,219 assertions, 0 failures in the current shared dirty worktree.
- Required root `php tools/run-tests.php` reruns later on 2026-05-22 after unrelated lane changes landed found non-rclone failures while the rclone tests still passed: one run reported 106 test files, 7,449 assertions, 2 failures in unrelated `lanes/lightningcss/tests/CssMinifierTest.php` animation shorthand expectations; the latest filtered rerun reported 106 test files, 7,476 assertions, 1 failure in unrelated `lanes/pandoc/tests/MarkdownReaderTest.php` pipe-table classification.
- Rclone-only PHP lane check on 2026-05-22 after the ignore-times/update/modtime slice: 11 rclone test files, 96 tests, 1,556 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the ignore-times/update/modtime slice: 108 test files, 7,657 assertions, 5 failures in unrelated LightningCSS tests, all caused by missing `PortLibs\LightningCSS\CssMinifier::rewriteAnimationGroup()`; all rclone tests in that root run passed.
- Required root `php tools/run-tests.php` rerun on 2026-05-22 after concurrent fixes landed: 108 test files, 7,734 assertions, 0 failures in the current shared dirty worktree.
- Rclone-only PHP lane check on 2026-05-22 after the refresh-times slice: 11 rclone test files, 98 tests, 1,571 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22 after the refresh-times slice first found 1 unrelated esbuild fixture failure, then passed after concurrent non-rclone lane updates landed: 110 test files, 7,961 assertions, 0 failures in the current shared dirty worktree.

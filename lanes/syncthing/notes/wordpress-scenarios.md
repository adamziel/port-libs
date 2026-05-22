# syncthing WordPress Scenario

Resumable media/content synchronization for local-first WordPress and Playground folders.

## Current Native Slice

Native scanner-style content blocks now match Syncthing's `lib/scanner/blocks_test.go`
fixtures for empty files, SHA-256 block hashes, exact coverage checks, block-list
hashing, optional hash validation, and upstream per-file block size selection.
Protocol version vectors now cover update/merge/compare ordering, and FileInfo
conflict decisions now cover invalid flag handling, block lineage conflicts,
winner ordering, and tombstone construction. FileInfo equivalence now maps a
focused slice of upstream `lib/protocol/bep_fileinfo_test.go`: block-list
equality shortcuts, local invalid flag equivalence, permission/block ignore
options, symlink target checks, modification time windows, and Unix ownership
matching by numeric IDs or resolved names. Protocol validation now maps focused
upstream `protocol_test.go` and `wireformat.go` behavior: filename canonicality,
request maximum-size and traversal rejection, FileInfo index consistency checks,
and slash/NFC normalization for outgoing request and index update names. The
BEP wire slice now maps upstream `bep_hello.go`, `bep_hello_test.go`,
`bep_request_response.go`, `bep_clusterconfig.go`, `proto/bep/bep.proto`, and
`protocol.go` behavior: Hello magic/length/protobuf frames, old/unknown hello
magic rejection, Request/Response proto3 field numbers, ClusterConfig folder
and device field numbers, Header message type/compression fields, and
uncompressed post-auth frame lengths. The compressed BEP slice now maps focused
upstream `TestWriteCompressed`, `TestLZ4Compression`, and
`TestLZ4CompressionUpdate` behavior: raw LZ4 blocks carry the upstream
big-endian uncompressed-length prefix, the Syncthing 1.18.6 compatibility
fixture decodes and re-encodes exactly, LZ4 post-auth frames decompress before
protobuf decoding, compression is skipped below the 128-byte threshold, metadata
mode leaves responses uncompressed, and incompressible payloads fall back to
uncompressed frames. The upstream denominator is still a static inventory rather
than runner parity, but this slice also counted 658 static Go test/benchmark
entry points across 141 upstream `_test.go` files. The Index/IndexUpdate slice
now maps focused upstream `bep_index_updates.go`, `bep_fileinfo.go`,
`vector.go`, and `proto/bep/bep.proto` behavior: Index and IndexUpdate frame
types, repeated FileInfo payloads, last/previous sequence fields, block
offset/size/hash payloads, sorted version vector counters, invalid flag
projection from local flags, no-permission and deleted bits, modified_by, raw
block size, symlink targets, blocks_hash and previous_blocks_hash, and Unix
owner/group UID/GID platform data. The DownloadProgress slice now maps focused
upstream `bep_download_progress.go`, `proto/bep/bep.proto`,
`TestUnmarshalFDPUv16v17`, and `lib/model/devicedownloadstate.go` behavior:
message type 5 frames, folder/update payloads, append and forget update types,
version vector payloads, unpacked repeated block indexes including index zero,
block size byte accounting with the upstream minimum-block fallback, same-version
append accumulation, version replacement, and version-matched forget deletion.
The old v0.14.16/v0.14.17 update fixtures are decoded without rejecting legacy
enum/string/index shapes that Go protobuf unmarshalling accepts. The outgoing
sent-download slice now maps focused upstream `sentdownloadstate.go`,
`progressemitter.go`, `progressemitter_test.go`, and `sharedpullerstate.go`
behavior: active puller filtering by folder, file kind, and `TempIndexMinBlocks`;
no update for new files with no temporary blocks; new-block-only append deltas
when `AvailableUpdated` advances; no update when block lists change without the
timestamp invariant; forget+append replacement when the version or puller
creation identity changes, including empty append updates that clear old
temporary availability; and versioned forgets for completed or errored pulls.
The progress-emitter boundary now maps the adjacent upstream
`ProgressEmitter.computeProgressUpdates`, temporary-index subscribe/unsubscribe,
`clearLocked`, `Deregister`, `BytesCompleted`, and `sharedPullerState.Progress`
semantics: per-device folder subscriptions receive grouped DownloadProgress
messages; disconnected devices have sent state discarded without forget traffic;
unshared folders are silently removed from sent state; disabling the emitter
returns cleanup forget updates before clearing state; and event-style progress
summaries expose Syncthing's block-to-byte estimate for WordPress import UI.
The scheduler/connection boundary now maps the upstream `ProgressEmitter.Serve`
timer gate and `progressUpdate.send` ordering: no event is emitted until the
registry count or latest puller update changes, active pulls schedule the next
interval, idle state does not, unchanged block lists are not retried, deregister
cleanup emits a final forget and stops the interval, and DownloadProgress
messages are converted to native BEP wire frames by a connection adapter after
the local sent-state has already advanced.
The inbound temporary-block slice now maps the adjacent upstream
`model.DownloadProgress`, `blockAvailabilityFromTemporaryRLocked`, and
`RequestGlobal` boundary: DownloadProgress messages from unknown or unshared
folders are ignored, accepted updates emit RemoteDownloadProgress-style
per-file block-count summaries, availability includes `fromTemporary`
candidates from shared devices with matching advertised file versions, and a
planned WordPress media block request sets `Request.fromTemporary` when the
only source is a peer's temporary file.

The example in `examples/wordpress-media-resume.php` shows how WordPress or
Playground import tooling can resume a partially synchronized upload by trusting
only blocks whose hashes still match the local bytes, then continuing at the
next byte offset. The FileInfo slice gives that same workflow a native boundary
for concurrent media edits, deleted uploads, and remote-invalid entries before a
higher-level sync planner chooses the final WordPress object state.
`examples/wordpress-fileinfo-equivalence.php` shows the adjacent decision:
scanner-only metadata noise can be treated as equivalent while actual media byte
changes still force a sync decision.
`examples/wordpress-index-validation.php` shows a WordPress media index update
being normalized to Syncthing wire paths and checked before dispatch while a
traversal request for `wp-config.php` is rejected.
`examples/wordpress-bep-request-frame.php` emits a native BEP Request frame for
the next missing WordPress media block, then decodes it back to prove the
folder, wire path, block number, byte range, and SHA-256 block hash survive the
wire boundary without shelling out.
`examples/wordpress-cluster-config.php` advertises a WordPress media folder and
Playground importer device as a native BEP ClusterConfig frame, then decodes it
back to prove the folder label, device addresses, compression preference, max
sequence, and frame type survive the wire boundary.
`examples/wordpress-compressed-metadata-frame.php` sends a larger WordPress
media ClusterConfig through metadata compression and decodes it back, showing
native LZ4 reduces repeated folder/device metadata while preserving the same
BEP message type and protobuf payload semantics.
`examples/wordpress-index-update-frame.php` sends a native BEP IndexUpdate for
a WordPress media upload, preserving normalized wire paths, FileInfo sequence
metadata, version counters, block hashes, and aggregate blocks_hash values
across the protobuf and post-auth frame boundary.
`examples/wordpress-download-progress-frame.php` sends a native BEP
DownloadProgress append update for a partially downloaded WordPress media file,
decodes it back, applies it to the remote temporary-download state, and shows
that the advertised temporary block and byte count can be used before a later
forget update clears the remote state.
`examples/wordpress-sent-download-progress.php` shows the inverse outgoing
state: a WordPress media pull first advertises temporary blocks 0 and 1, then
emits only the newly available block 4, and finally sends the versioned forget
that clears the remote peer's temporary availability when the pull completes or
errors.
`examples/wordpress-progress-emitter.php` coordinates two subscribed WordPress
peers, grouping native DownloadProgress frames per device/folder, emitting only
the newly available media block on the second pass, and exposing completed-byte
progress for an import status surface.
`examples/wordpress-progress-scheduler.php` wraps that emitter in the native
scheduler and wire connection adapter, showing an idle timer pass, an initial
WordPress media temporary-block advertisement, and a later delta frame that
contains only the newly available block.
`examples/wordpress-inbound-temporary-request.php` applies a remote peer's
temporary-block advertisement to a shared WordPress media folder, emits the
same event summary the model would expose, and plans a native BEP Request frame
with `fromTemporary` set for the advertised media block.

## Next Task

Port inbound `Request.fromTemporary` serving semantics: try the advertised
temporary file first, validate the requested block hash, fall back to the final
file when the temp data is unavailable or invalid, and surface WordPress media
restore errors without shelling out.

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
owner/group UID/GID platform data.

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

## Next Task

Port native DownloadProgress append/forget payloads and frames from focused
upstream `proto/bep/bep.proto` and protocol inbox handling, including version
vectors, block indexes, and block size fields for resumable WordPress media
transfers.

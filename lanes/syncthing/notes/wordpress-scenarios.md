# syncthing WordPress Scenario

Resumable media/content synchronization for local-first WordPress and Playground folders.

The route-registry watcher recovery slice now exposes bounded WordPress/local
REST routes for due filesystem watcher restarts. `GET
/syncthing/db/watch/restarts` returns only restarts whose backoff has elapsed,
and `POST /syncthing/db/watch/restarts/complete` acknowledges one folder's
completed recovery without consuming already queued media events before their
normal delayed scan window. This reuses the existing watcher scheduler and
cleanup-status component; no new support component is needed.

The route-registry pending watcher-status slice adds `GET
/syncthing/db/watch/status` for WordPress REST clients that need to show queued
media edits before the delayed scan window fires. The route returns the existing
watch scheduler status map, including pending paths, event types, in-progress
paths, next scan time, due state, and any watcher restart state, without
draining queued events or forcing an early scan. Dependency closure: no new
support component is needed; this reuses the existing bounded watcher scheduler
and route-registry components.

The route-registry one-folder watcher-status slice lets REST clients pass a
`folder` payload to `GET /syncthing/db/watch/status`, `GET
/syncthing/db/watch/restarts`, and `GET /syncthing/db/watch/cleanups`. The
routes return only that folder's watcher, due-restart, or recent-cleanup row, or
an empty map when the folder has no matching state, while preserving the
aggregate all-folder response when the filter is omitted. Dependency closure:
no new support component is needed; this reuses the existing bounded watcher
scheduler and route-registry components.

The route-registry route-catalog slice adds `GET /syncthing/db/routes` for
WordPress REST clients that need to discover the local scan/watch API surface
without pulling a large all-routes list. Clients can filter by HTTP method and
path prefix, then page through route metadata with bounded `offset`/`limit`
arguments and `nextOffset` bookkeeping. Dependency closure: no new support
component is needed; this reuses the existing bounded route-registry component.

The route-registry scan-status slice adds `GET /syncthing/db/scan/status` for
WordPress REST clients that need a bounded list of many local-first folders
without starting a scan. The route reports each folder's running or paused state,
latest checkpoint REST status when one exists, delayed scan timing, optional
single-folder filtering, state filtering, and bounded pagination. Dependency
closure: no new support component is needed; this reuses the existing bounded
scan scheduler, checkpoint store, and route-registry components.

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
uncompressed frames. The control-message slice now maps focused upstream
`proto/bep/bep.proto`, `protocol.go`, and `protocol_test.go` behavior for Ping
and Close messages: Ping frames use an empty protobuf payload with BEP message
type 6, Close frames preserve the reason string in protobuf field 1 with BEP
message type 7, close reasons participate in the normal metadata compression
decision path, and the static inventory counted eight upstream ping/close and
close-race test functions without hydrating the full checkout. The upstream
denominator is still a static inventory rather
than runner parity, but this slice also counted 658 static Go test/benchmark
entry points across 141 upstream `_test.go` files. The raw request/response
exchange slice now maps focused upstream `protocol.go`, `errors.go`,
`protocol_test.go`, `model.go`, and `proto/bep/bep.proto` behavior: outbound
requests receive monotonically increasing IDs, matching responses resolve only
pending requests, late unknown responses are ignored, no-error/generic/
no-such-file/invalid-file codes map to the same error classes as upstream,
connection close drains pending requests as closed, and dispatcher request
size/filename validation rejects invalid messages before request handling. The
bounded BEP session slice now maps focused upstream `writerLoop` and
`dispatcherLoop` behavior from `protocol.go` and `protocol_test.go`: ordinary
post-auth writes are gated behind the local ClusterConfig frame, unknown message
types are skipped for future compatibility, inbound Close is accepted before
ClusterConfig, the first known inbound non-Close message must be ClusterConfig,
Ping/Request/Index/DownloadProgress before readiness close with a protocol
error, inbound Response frames resolve only pending request IDs, and inbound
WordPress media Request frames validate before a native handler emits a BEP
Response. The stream-frame slice maps adjacent upstream `readHeader`,
`readMessageAfterHeader`, `readerLoop`, `writeMessage`, and
`writeCompressedMessage` boundaries: PHP streams read exactly one post-auth
frame at a time using the upstream uint16 header length, protobuf Header bytes,
uint32 message length, max-message guard, and exact payload bytes; truncated or
oversized frames fail before protobuf decoding; and a stream-read WordPress
media Request can be dispatched through the bounded BEP session to produce a
native BEP Response. The stream-backed model callback slice maps the adjacent
upstream `Model`/`rawModel` dispatch boundary: inbound Index, IndexUpdate, and
DownloadProgress frames can invoke registered session handlers or per-read
handler bundles after the normal ClusterConfig-first decode/validation path;
callback return values are surfaced on the session event for local catalog and
progress bookkeeping; and thrown callback errors close the session as handling
errors rather than protocol errors. The device identity slice
now maps focused upstream `deviceid.go`, `deviceid_test.go`, `luhn.go`, and
`luhn_test.go` behavior: raw certificate bytes hash to a 32-byte device ID,
canonical IDs use Syncthing's base32 plus four Luhn32 check digits and
seven-character chunks, old no-check-digit IDs and copy/paste variants with
spaces, lowercase, or common typo digits parse back to the same canonical form,
short IDs expose the first seven base32 characters, and malformed lengths,
base32 data, or check digits are rejected before a peer is accepted. The
Index/IndexUpdate slice
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
The unavailable-peer boundary now maps adjacent upstream `model.Closed`,
`fileAvailabilityRLocked`, `blockAvailabilityFromTemporaryRLocked`, and
`RequestGlobal` behavior: once native connection tracking is enabled,
disconnected devices are removed from full-file availability, their temporary
download state is dropped, later DownloadProgress updates from that device are
ignored until it reconnects, and pullBlock fails before issuing a request when
no connected peer has the required version.
The device-activity slice now maps focused upstream `deviceactivity.go` and
`deviceactivity_test.go` behavior: least-busy selection returns the first
candidate with the lowest outstanding request count, repeated selection checks
do not mutate activity, `using` and `done` adjust the peer's outstanding count
by device ID regardless of full-file or temporary availability, empty
availability has no selection, and WordPress media request planning can shift a
block from a busy full-file peer to a less-busy temporary-block peer while
preserving `Request.fromTemporary`. A bounded upstream runner was executed for
this focused slice only: `go test ./lib/model -run '^TestDeviceActivity$'
-count=1` passed in a throwaway worktree at commit
`3962a237232473c20a44945a6c8ce8c930375360`; this is not full upstream runner
parity.
The FileInfoBatch slice now maps focused upstream `fileinfobatch.go` and
`fileinfobatch_test.go` behavior: pending FileInfo entries track uncompressed
FileInfo protobuf bytes, batches become full at 1000 files or 250 KiB,
`FlushIfFull` is a no-op below those limits, successful `Flush` resets file
and size state, returned or thrown flush errors are sticky until `Reset`, and
appending to a failed batch is rejected. A bounded upstream runner was executed
for this focused slice only: `go test ./lib/model -run
'^TestFileInfoBatchError$' -count=1` passed in a throwaway worktree at commit
`3962a237232473c20a44945a6c8ce8c930375360`; this is not full upstream runner
parity.
The block-pull ordering slice now maps focused upstream
`lib/model/blockpullreorderer.go`, `blockpullreorderer_test.go`, and
`lib/config/blockpullorder.go` behavior: in-order leaves blocks untouched,
random ordering shuffles block indexes, standard ordering sorts the local and
remote Syncthing DeviceIDs, chunks blocks by device count with the same ceiling
division as upstream, starts with the local device's chunk, then appends the
remaining chunks in whole-chunk shuffled order. A bounded upstream runner was
executed for this focused slice only: `go test ./lib/model -run
'Test_chunk|Test_inOrderBlockPullReorderer_Reorder|Test_standardBlockPullReorderer_Reorder'
-count=1` passed in a throwaway worktree at commit
`3962a237232473c20a44945a6c8ce8c930375360`; this is not full upstream runner
parity.
The pull job queue slice now maps focused upstream `lib/model/queue.go` and
`queue_test.go` behavior: Push appends pull jobs, Pop moves the next queued
file into progress, Done removes the first matching in-progress file,
BringToFront moves only queued files ahead of their peers, Jobs paginates
in-progress files before queued files while preserving upstream skip counts, and
Reset clears both lists. A bounded upstream runner was executed for this focused
slice only: `go test ./lib/model -run
'TestJobQueue|TestBringToFront|TestQueuePagination' -count=1` passed in a
throwaway worktree at commit `3962a237232473c20a44945a6c8ce8c930375360`; this
is not full upstream runner parity.
The service-map slice now maps focused upstream `lib/model/service_map.go` and
`service_map_test.go` behavior: adding a keyed service starts it, overwriting
the same key stops the previous service before the replacement starts, `Stop`
halts a service but keeps it retrievable from the map, `Remove` and
`RemoveAndWait` delete keyed services with the upstream `service not found`
boundary for absent keys, and `Each` can remove matching services during
iteration while stopping early on callback errors. A bounded upstream runner was
executed for this focused slice only: `go test ./lib/model -run
'^TestServiceMap$' -count=1` passed in a throwaway worktree at commit
`3962a237232473c20a44945a6c8ce8c930375360`; this is not full upstream runner
parity.
The folder-completion slice now maps focused upstream `lib/model/model.go`,
`lib/model/model_test.go`, and `lib/model/folderstate.go` behavior:
`newFolderCompletion` computes completion from global and needed bytes,
`FolderCompletion.add` recomputes aggregate completion across folders,
downloaded temporary bytes are subtracted from needed bytes without underflow,
remote folder states render as upstream API strings, `Map` exposes the
completion/global/need/sequence payload shape, and delete-only work reports 95%
instead of 100% complete. A bounded upstream runner was executed for this
focused slice only: `go test ./lib/model -run
'TestAddFolderCompletion|TestCompletionEmptyGlobal' -count=1` passed in a
throwaway worktree at commit `3962a237232473c20a44945a6c8ce8c930375360`; this
is not full upstream runner parity. The WordPress example
`wordpress-folder-completion.php` shows a media folder progress payload after
temporary downloaded-byte credit plus a delete-only cleanup row for sync UI.
The indexhandler sender slice now maps focused upstream
`lib/model/indexhandler.go` and `indexhandler_test.go` behavior: the first
send from sequence zero emits a full Index, subsequent sends emit IndexUpdate
with `sentPrevSequence`, local sequence tracking advances through database
holes separately from sent sequence tracking, a full batch can still include
one trailing delete so rename add/delete pairs stay together, receive-encrypted
folders skip local receive-only changes, receive-only FileInfo versions are
stripped before indexing, encrypted finalized sizes can subtract trailer bytes,
and received regular files produce DownloadProgress forget updates while
directories, symlinks, and deletes are ignored. A bounded upstream runner was
executed for this focused slice only: `go test ./lib/model -run
'^TestIndexhandlerConcurrency$' -count=1` passed in a throwaway worktree at
commit `3962a237232473c20a44945a6c8ce8c930375360`; this is not full upstream
runner parity. The WordPress example `wordpress-index-handler.php` shows a
private media folder sending only remote-visible changes while advancing over a
local receive-only draft.
The indexhandler registry slice now maps the adjacent upstream
`newIndexHandler`, `indexHandlerRegistry.AddIndexInfo`,
`RegisterFolderState`, `RemoveAllExcept`, `folderPausedLocked`, and
`folderRunningLocked` behavior: ClusterConfig device index IDs decide whether a
peer can receive delta indexes or must be reset with a full index, remote index
IDs decide whether stored remote index data should be kept, dropped, or
replaced, index info received while the local folder is paused stays pending
until the folder is registered as running, running handlers are paused and
resumed without replacement on local folder pause/resume, new ClusterConfig
index info replaces an existing handler, unshared folders remove both running
and pending handlers, and newly started handlers schedule a pull. This is a
static upstream mapping from targeted reads of `lib/model/indexhandler.go` and
`lib/model/model.go`, not additional upstream runner parity. The WordPress
example `wordpress-index-handler-registry.php` shows a receive-encrypted
private media folder accepting pending peer index info only after the local
runner resumes.
The receive-index slice now maps the adjacent upstream `model.Index`,
`model.IndexUpdate`, `handleIndex`, `indexHandlerRegistry.ReceiveIndex`,
`indexHandler.receive`, `makeForgetUpdate`, and `logSequenceAnomaly` behavior:
full incoming indexes drop the prior remote file view before storing the new
FileInfo batch, delta indexes preserve existing remote FileInfos by name,
received regular files clear matching temporary DownloadProgress state through
forget updates, missing folders return the upstream no-such-folder boundary,
paused handlers return the upstream paused-folder boundary without scheduling a
pull, accepted receive work schedules a pull, RemoteIndexUpdated event payloads
carry device/folder/items/sequence/version, unexpected previous/last sequence
claims are recorded as failure-style anomalies without rejecting the batch, and
duplicate remote sequence numbers are rejected before storing the batch. The
WordPress example `wordpress-receive-index.php` shows a Playground peer's media
index replacing temporary block availability with a scheduled pull against the
remote FileInfo state. That receive path can now update an attached
`FolderIndexState` too: full Index messages reset the remote peer's folder
state before recalculating global/need metadata, while IndexUpdate messages
preserve existing remote files and add only the delta. The same example reports
the local WordPress media files still needed after the peer index and the
remote device availability for the current global file.

The filesystem watcher lifecycle slice now includes removed-folder delayed
dispatch cleanup for WordPress media folders: if a folder is removed/unshared
after filesystem events were queued but before their Syncthing-style watch
delay expires, native `FolderWatchScanScheduler::scanDueWatchEvents()` drops
the stale queued paths and restart state instead of scanning or exposing stale
watch status. Paused folders still preserve queued events for resume. Focused
verification for this slice passed `FolderWatchScanSchedulerTest.php` with 152
assertions, and `wordpress-fs-watch-scan-scheduler.php` shows the
`removedDuringDispatchScan` smoke returning zero folders/errors with null
removed-folder status. Dependency closure: no new support component is needed;
the slice reuses the existing bounded watcher scheduler/aggregator components.
The inbound request-serving slice now maps focused upstream `model.Request`,
`readOffsetIntoBuf`, `scanner.Validate`, `fs.IsInternal`, `fs.TempName`,
`fs.IsTemporary`, and `protocol.TestRequestMaxSize`
behavior: shared devices can read regular file ranges, `fromTemporary` requests
try the `.syncthing.<basename>.tmp` sibling first, the temporary bytes must
match the requested SHA-256 block hash, stale or short temporary reads fall
back to the finalized file, final-file hash mismatches return no-such-file, and
internal/traversal/symlink paths are rejected before disk reads. Direct
RequestServer calls now apply the upstream request-size guard too: zero-length
and oversized WordPress media block requests are rejected before disk reads,
while exactly `MaxRequestSize` is still accepted. A bounded upstream runner was
executed for this focused slice only: `go test ./lib/protocol -run
'^TestRequestMaxSize$' -count=1` passed in a throwaway worktree at commit
`3962a237232473c20a44945a6c8ce8c930375360`; this is not full upstream runner
parity. The WordPress example `wordpress-request-size-guard.php` shows
Playground media request validation at zero, oversized, and maximum accepted
sizes. Temporary-file detection follows upstream basename-prefix semantics:
both `.syncthing.` and `~syncthing~` mark a file as temporary regardless of the
active platform prefix, while parent directories with those names do not make a
normal media leaf temporary.
The request-serving boundary now also maps upstream ignore and receive-encrypted
guards from `model.Request` and `lib/ignore`: explicit ignored matches are
rejected with invalid-file before any temporary or finalized bytes are served,
basic `.stignore` prefixes such as `(?i)`, `(?d)`, and unrooted/rooted glob
expansion produce explicit match results, included ignore snippets are loaded
relative to the current ignore file, `#escape=` directives can switch the
escape character before local patterns, escaped glob metacharacters/bracket
ranges/brace alternatives follow focused upstream `gobwas/glob` behavior, and
receive-encrypted folders skip finalized-file hash validation for encrypted
hash tokens while still requiring temporary bytes to validate before they can
be served.
The receive-encrypted envelope slice maps focused upstream
`lib/protocol/encryption.go`, `lib/protocol/encryption_test.go`,
`lib/model/sharedpullerstate.go`, and `lib/model/folder_sendrecv.go`
boundaries without claiming full crypto parity: encrypted requests add the
40-byte nonce/tag overhead per block and pad small plaintext requests to the
upstream 1024-byte minimum, inbound encrypted request geometry subtracts that
overhead before dispatch, synthetic encrypted parent directories are detected
with the upstream `.syncthing-enc` and 200-character component rules, and
receive-encrypted file trailers append a FileInfo wire payload followed by a
big-endian payload length.
The encrypted request/response data slice now maps the adjacent
`encryptedConnection.Request` and `encryptedModel.Request` behavior: outgoing
trusted requests derive the encrypted name/hash token, inflate offsets by the
per-block 40-byte overhead, request at least 1024 plaintext bytes plus
nonce/tag overhead from the untrusted peer, decrypt returned bytes, and trim the
padding back to the original requested WordPress media block size. Inbound
receive-encrypted requests decrypt the name/hash/geometry before model serving,
then pad short plaintext responses to 1024 bytes before XChaCha20-Poly1305
encryption.
The encrypted request-serving integration now maps the adjacent
`encryptedModel.Request` to the native disk boundary: an untrusted encrypted BEP
request is decrypted to the plaintext WordPress media name, hash, offset, and
size; `RequestServer` performs the normal shared-folder, path, and hash checks;
successful bytes are padded and encrypted for the peer; and stale-hash errors
remain no-such-file responses without encrypted payload data.
The cluster-config encryption consistency slice maps focused upstream
`model.ccCheckEncryption`, `TestCcCheckEncryption`, and adjacent
`TestClusterConfigEncrypted` behavior: untrusted devices cannot share plain
folders, both advertised encryption tokens are impossible, local
receive-encrypted plus remote encrypted configuration is impossible, token/plain
configuration mismatches return the upstream error boundaries, receive-encrypted
folders adopt a cluster-advertised token before requesting a cluster-config
resend, stored-token mismatches surface the upstream different-password error,
and remote encrypted peers can derive and compare exact `PasswordToken` bytes
from configured passwords. The password-token slice maps
`lib/protocol/encryption.go` and `TestKeyDerivation`: folder keys are derived
with Syncthing's scrypt parameters (`N=32768`, `r=8`, `p=1`, `keyLen=32`) over
`knownBytes(folderID)`, then encrypted with native AES-CMAC-SIV using the same
zero-length AEAD nonce associated-data boundary as upstream. A temporary Go
oracle produced fixed fixture bytes only; the PHP implementation does not call
Go at runtime. During probing, `sudo -n dnf install -y php-sodium` installed
`php-sodium`/`libsodium`, but this PHP build exposes only high-level scrypt, so
the exact N/r/p KDF is implemented in lane PHP rather than delegated to sodium.
The deterministic receive-encrypted transform slice now maps the adjacent
`encryptName`, `decryptName`, `KeyGenerator.FileKey`, `encryptBlockHash`, and
legacy block-hash fallback boundaries from `lib/protocol/encryption.go` and
`lib/protocol/encryption_test.go`: filenames are AES-SIV encrypted, encoded as
unpadded base32-hex, and slashified with `.syncthing-enc` synthetic parent
components; invalid encrypted-name paths and invalid base32 quanta fail before
plaintext is exposed; per-file keys use HKDF-SHA256 over `folderKey ||
filename` with salt `syncthing`; and encrypted block hash tokens are stable for
the same hash at the same offset while differing for the same hash at a
different offset because the big-endian offset is associated data.
The encrypted metadata slice now maps upstream `encryptBytes`, `DecryptBytes`,
`encryptFileInfo`, `DecryptFileInfo`, `TestEnDecryptBytes`,
`TestEnDecryptFileInfo`, and `TestEncryptedFileInfoConsistency`: XChaCha20-
Poly1305 payloads use Syncthing's 24-byte nonce prefix plus 16-byte tag
overhead, the upstream encrypted `hello world` fixture decrypts with the
derived file key, FileInfo protobuf field 19 carries encrypted metadata, fake
encrypted FileInfo wrappers expose encrypted names, 0644 permissions, the
upstream fixed modified time, sequence numbers, deterministic fake versions,
padded encrypted block sizes, encrypted byte offsets, and offset-bound block
hash tokens, and decryption restores the plaintext metadata while preserving
the untrusted peer's sequence number.
The receive-encrypted finalization slice now maps the adjacent upstream
`sharedPullerState.finalizeEncrypted`, `writeEncryptionTrailer`,
`prepareFileInfoForIndex`, and decrypt-tool trailer verification boundaries:
completed encrypted file bytes get a FileInfo wire trailer written at the
encrypted data size, the locally indexed size includes that trailer, outbound
index metadata subtracts the host-local trailer size, and verification reloads
the trailer, decrypts FileInfo metadata, decrypts every encrypted block, trims
only the final padded block, and validates plaintext SHA-256 block hashes before
recovering the WordPress media bytes.
The encrypted index collection slice maps upstream `encryptedConnection.Index`,
`encryptedConnection.IndexUpdate`, `encryptedModel.Index`,
`encryptedModel.IndexUpdate`, `encryptFileInfos`, and `decryptFileInfos`:
outgoing Index and IndexUpdate payloads normalize WordPress paths before
encrypting every FileInfo wrapper for the untrusted peer, while incoming
encrypted collections decrypt those wrappers and keep folder, last sequence,
previous sequence, and peer-controlled FileInfo sequence metadata intact.
The encrypted DownloadProgress slice maps upstream
`encryptedConnection.DownloadProgress` and `encryptedModel.DownloadProgress`:
temporary-block progress for folders with registered encryption keys is
intentionally dropped instead of translated to encrypted temporary files, while
plain WordPress media folders still forward unchanged to the normal progress
connection and model-level temporary-download state.
The receive-encrypted scan cleanup slice maps upstream `folder.go` and
`folder_sendrecv.go` boundaries for synthetic encrypted parent directories:
missing `.syncthing-enc` parent trees are created for pulled encrypted files
without scheduling a follow-up scan, scanned synthetic parent directories are
never written into the local index, and empty synthetic parent directories are
removed while non-empty parents remain on disk as container paths only.

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
`examples/wordpress-request-response-exchange.php` tracks the next layer of
that request lifecycle: a stale media block response maps to no-such-file for a
retry, the retry response completes with restored bytes, and a disconnected
peer drains an outstanding WordPress media request as connection-closed.
`examples/wordpress-cluster-config.php` advertises a WordPress media folder and
Playground importer device as a native BEP ClusterConfig frame, then decodes it
back to prove the folder label, device addresses, compression preference, max
sequence, and frame type survive the wire boundary.
`examples/wordpress-compressed-metadata-frame.php` sends a larger WordPress
media ClusterConfig through metadata compression and decodes it back, showing
native LZ4 reduces repeated folder/device metadata while preserving the same
BEP message type and protobuf payload semantics.
`examples/wordpress-close-frame.php` emits a native BEP Close frame with a
WordPress media maintenance reason and decodes it back so import tooling can
notify a peer before intentionally disconnecting.
`examples/wordpress-device-id.php` derives a Syncthing device ID from raw
WordPress Playground peer certificate bytes, accepts a lowercase space-separated
copy from an admin surface, and exposes the canonical and short peer IDs used
for pairing.
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
`examples/wordpress-temporary-peer-disconnect.php` shows a WordPress editor
peer advertising a temporary media block, then disconnecting before the pull;
the native tracker drops the editor's temporary availability and falls back to
a still-connected CDN peer for the same media block.
`examples/wordpress-temporary-request-server.php` serves the other side of that
flow: a WordPress media restore request arrives with `fromTemporary` set, stale
temporary bytes are rejected by the block hash, the finalized media file is
served as a native BEP Response frame, and any restore error is surfaced as a
response code rather than a shell command failure.
`examples/wordpress-temporary-media-scan.php` shows the publishing side of the
same boundary: a WordPress media scan filters Syncthing `.syncthing.` and
`~syncthing~` temporary basenames before exposing finalized uploads.
`examples/wordpress-encrypted-media-request.php` adds the encrypted-folder
variant: a private export path is blocked by a native ignore match, while a
receive-encrypted media object can be restored from finalized encrypted bytes
even when the request hash is an opaque encrypted token instead of a SHA-256
digest.
`examples/wordpress-ignore-include-escape.php` shows a WordPress media folder
loading a shared private-export ignore snippet via `#include`, then using a
custom `#escape=|` rule to block a literal filename containing `*` without
blocking an ordinary public media request.
`examples/wordpress-receive-encrypted-envelope.php` shows the adjacent
untrusted-peer boundary: a WordPress media block request is reshaped into an
encrypted-name request with padded size, per-block overhead, a deterministic
encrypted filename, and an offset-bound block-hash token, while the encrypted
file bytes carry a recoverable normalized FileInfo trailer for later metadata
reconstruction. It also wraps the WordPress media FileInfo into Syncthing's
encrypted metadata field 19, showing the fake untrusted-peer name, fake version,
encrypted size, block size, metadata byte count, decrypted plaintext name, and a
full encrypted IndexUpdate collection round trip. The same example now includes
an encrypted response block, showing 1024-byte padded plaintext, 40-byte
nonce/tag overhead, and trusted-side trimming back to the requested media block.
`examples/wordpress-encrypted-request-server.php` runs that encrypted request
through the native request server: the encrypted BEP request is decoded,
plaintext media bytes are served only when the decrypted hash matches disk, the
wire response stays encrypted for the untrusted peer, and a stale encrypted hash
token becomes a no-such-file response.
`examples/wordpress-receive-encrypted-finalization.php` shows the completed
receive-encrypted file boundary for a private WordPress export: native encrypted
block bytes are finalized with a metadata trailer, the local index keeps the
trailer bytes while the remote index size strips them, and verification rejects
extra bytes before the trailer before recovering the plaintext media payload and
block hash.
`examples/wordpress-pull-receive-encrypted-finalize.php` shows the same trailer
boundary through the pull temporary-file promotion path: an encrypted media
block is written into a `.syncthing` temporary path, the FileInfo trailer is
appended during final close, the final local size includes the trailer, and
remote index preparation strips the host-local trailer bytes.
`examples/wordpress-encrypted-download-progress.php` shows why receive-encrypted
private media folders do not advertise or accept temporary-block progress:
encrypted-folder progress is suppressed before a BEP frame or remote progress
event is created, while a plain WordPress media folder still emits a message
type 5 frame and updates temporary block counts.
`examples/wordpress-encryption-consistency.php` shows the cluster-config
boundary for that same WordPress media folder: a plain untrusted peer is
rejected, a receive-encrypted peer's real Syncthing password token is accepted
and marked for cluster-config resend, and a stale local token produces the
upstream different-password error.
`examples/wordpress-receive-encrypted-parent-cleanup.php` shows the scanned
parent-directory boundary: a private WordPress media object creates its
synthetic encrypted parent path without a scan event, the non-empty parent is
not indexed, and an abandoned empty synthetic parent is removed.
`examples/wordpress-bep-session.php` ties the BEP session state together for a
WordPress media peer: Ping is blocked before ClusterConfig, ClusterConfig makes
post-auth frames available, an inbound media Request is served through a native
handler and emitted as a BEP Response, and a remote Close drains a pending media
request as connection-closed.
`examples/wordpress-bep-stream-io.php` shows the same WordPress media exchange
over a PHP stream boundary: ClusterConfig and Request frames are written into a
stream, read back with exact Syncthing post-auth framing, dispatched through the
session, and answered with a native BEP Response.
`examples/wordpress-bep-model-callbacks.php` extends that stream boundary to
Index, IndexUpdate, and DownloadProgress: the stream dispatcher invokes local
WordPress media callbacks that update a catalog entry and a temporary-block
progress map without shelling out.
`examples/wordpress-file-info-batch.php` shows three WordPress media FileInfo
entries being accumulated into a native FileInfoBatch, flushed into one
IndexUpdate frame with previous/last sequence metadata, and reset after the
flush so later filesystem scan chunks can reuse the same batch lifecycle.
`examples/wordpress-block-pull-order.php` shows a large WordPress media archive
being split into upstream standard pull chunks across three sorted Syncthing
device IDs, with the local Playground peer's assigned chunk requested first and
the remaining chunks following as whole ranges.
`examples/wordpress-pull-job-queue.php` shows a WordPress media pull queue
where a private export is bumped ahead of ordinary uploads, appears in the
in-progress page before queued media, and is removed from progress after
completion while the remaining queued files keep FIFO order.
`examples/wordpress-service-map.php` shows WordPress media and receive-encrypted
folder workers using upstream service-map lifecycle semantics: a private folder
worker can be stopped while its retained configuration remains inspectable, a
media indexer can be replaced for a rescan after stopping the old worker, and a
stopped folder can be removed after maintenance.
`examples/wordpress-folder-completion.php` shows a WordPress media sync status
payload using upstream completion math: temporary downloaded bytes reduce the
remaining need count, delete-only work is displayed as 95% complete, and an
aggregate payload keeps the API-style completion fields stable for UI code.
The folder index state slice maps focused upstream sqlite global/need behavior
from `internal/db/sqlite/db_global_test.go`, `folderdb_global.go`,
`folderdb_counts.go`, and `folderdb_update.go`: version-vector winners decide
which FileInfo is global, local and remote needed-file lists follow the same
deleted/ignored/remote-invalid boundaries as upstream, directories and symlinks
contribute to need counts, alphabetic pagination is stable,
`AllNeededGlobalFiles` pull ordering supports alphabetic, smallestFirst,
largestFirst, oldestFirst, newestFirst, and random ordering before pagination,
`AllGlobalFilesPrefix`-style filtering returns all current globals for an empty
prefix and only stable prefix-range globals for a WordPress uploads subtree,
`GetGlobalAvailability`-style device lists include remote peers with the same
version as the current global file, and remote drop/reset recalculation can
promote the local or another remote version. `DropDevice` is now represented as
a no-op for missing peers, a full remote-file removal plus global recalculation
for remote peers, and a local-device guard before mutation. A full Index reset
from one remote does not erase another remote's need for a re-added media file.
A bounded
upstream runner was executed for this focused slice only:
`go test ./internal/db/sqlite -run
'^(TestNeed|TestNeedDeleted|TestDontNeedIgnored|TestLocalDontNeedDeletedMissing|TestRemoteDontNeedDeletedMissing|TestNeedRemoteSymlinkAndDir|TestNeedPagination)$'
-count=1` passed in a throwaway worktree at commit
`3962a237232473c20a44945a6c8ce8c930375360`; this is not full upstream runner
parity. The WordPress example `wordpress-global-need-state.php` shows a
Playground peer editing a media file while an ignored same-version local export
does not trigger a false redownload, and now exposes smallest-first and
newest-first media queues for prioritizing small previews or recent uploads. A
bounded upstream runner was also executed for this focused pull-order slice
only: `go test ./internal/db/sqlite -run '^TestBasics/AllNeededNamesLocal$'
-count=1` passed in a throwaway worktree at commit
`3962a237232473c20a44945a6c8ce8c930375360`; this is not full upstream runner
parity. A bounded upstream runner was also executed for the prefix/drop slice:
`go test ./internal/db/sqlite -run
'TestBasics/AllGlobalPrefix|TestDropDevice' -count=1` passed in a throwaway
worktree at the same commit with `ok
github.com/syncthing/syncthing/internal/db/sqlite 0.028s`; this is not full
upstream runner parity.
The block-diff slice now maps focused upstream `lib/model/folder_sendrecv.go`
and `folder_sendrecv_test.go` behavior: `blockDiff(src, tgt)` returns target
blocks already present at the same index separately from target blocks still
needed, treats an empty target as no work, treats an empty source as a full
target copy, stops comparing once the source is shorter than the target, and
uses the target block offsets/sizes in the returned need list. The native tests
copy the upstream 12-case `TestDiff` table plus the three `TestDiffEmpty`
boundaries. A bounded upstream runner was executed for this focused slice only:
`go test ./lib/model -run 'TestDiff|TestDiffEmpty' -count=1` passed in a
throwaway worktree at commit `3962a237232473c20a44945a6c8ce8c930375360` with
`ok github.com/syncthing/syncthing/lib/model 0.011s`; this is not full upstream
runner parity. The WordPress example `wordpress-block-diff-planner.php` shows a
changed media block being selected for a native BEP request while unchanged
block ranges are reused locally.
The pull-work planning slice now connects that diff boundary to focused
upstream `handleFile`, `reuseBlocks`, `copierRoutine`, `sharedPullerState`, and
`BlockInfo.IsEmpty` semantics: target blocks already present in a
`.syncthing.<name>.tmp` file are removed from copy/pull work, receive-encrypted
folders do not reuse temporary blocks, sparse all-zero blocks are skipped only
when no temporary file is being reused, current-file hash matches are counted as
origin copies, other local indexed hash matches are counted as elsewhere
copies, and remaining blocks become pending pulls with upstream-style progress
and temporary-availability indexes. A bounded upstream runner was executed for
this focused slice only: `go test ./lib/model -run
'^(TestHandleFile|TestHandleFileWithTemp|TestCopierFinder|TestPullEmptyBlock)$'
-count=1` passed in a throwaway worktree at the same commit with `ok
github.com/syncthing/syncthing/lib/model 0.093s`; this is not full upstream
runner parity. The WordPress example now shows a partially downloaded media
temporary file preserving an already-correct block, copying an unchanged local
block, and requesting only the changed media block.
The pullBlock retry slice now maps focused upstream `pullBlock` behavior from
`lib/model/folder_sendrecv.go`: candidate peers are selected by least-busy
device activity, each candidate is tried once and removed after an error or
invalid response, activity is incremented only while the request callback is
active, returned plaintext bytes must match the requested block length and
SHA-256 hash, all-zero blocks skip network requests, and receive-encrypted
folders trust opaque encrypted hash-token responses without local SHA-256
verification. This slice is backed by static targeted reads of upstream
`pullBlock`, `verifyBuffer`, `deviceActivity.using/done`, `RequestGlobal`, and
the zero-block/receive-encrypted branches rather than a new upstream runner.
The WordPress example `wordpress-block-pull-retry.php` shows a stale CDN peer
failing validation before an editor laptop supplies verified media bytes, with
both peer activity counters returning to zero after the request attempts.
The temporary-finalization slice now maps the adjacent upstream
`sharedPullerState.tempFile`, `copyDone`, `pullDone`, `finalClose`,
`tempFileInWritableDir`, `finalizeEncrypted`, `writeEncryptionTrailer`,
`performFinish`, `deleteItemOnDisk`, `moveForConflict`, and sparse all-zero block boundaries: verified copied and
pulled blocks are written into Syncthing temporary names, temporary files are
created or reopened with final permissions OR `0600` so read-only private media
can still be assembled after a restart, sparse zero blocks are marked available
without a network request, final close waits until every target block is accounted for,
receive-encrypted temporary files append the FileInfo trailer before promotion,
successful close renames the temp file into place and emits the
`dbUpdateHandleFile` update type, conflicting existing regular files are moved
to `.sync-conflict-YYYYMMDD-HHMMSS-device` siblings before the pulled file is
published, existing regular files are rechecked against the current scanned
`FileInfo` before any conflict, archive, or overwrite decision so unscanned
local edits schedule a follow-up scan and leave the pulled temp file reusable,
case-only target names now follow upstream `TestPullCaseOnlyPerformFinish`:
case-sensitive finalization can promote the differently cased target, while
case-detecting finalization returns the upstream `uses different upper or
lowercase` error without scheduling a scan or emitting a database update,
tracked existing directories and symlinks are deleted before a
pulled regular file is promoted, `MaxConflicts` keeps only the newest conflict
copies after `moveForConflict`, descendant versions replace without conflict
copies, non-conflicting regular-file replacements can archive the previous file
under a Syncthing-style `~YYYYMMDD-HHMMSS` `.stversions` name, conflicts still
prefer `.sync-conflict` copies over version archives, guarded tracked-directory
replacement now preserves unknown or changed children, records upstream-style
scan requests, and fails with the `contains changed files, scheduling scan`
error before destructive removal, nondeletable ignored directory children stop
replacement with the upstream `contains ignored files` error while leaving scan
requests empty, receive-only changed children in a receive-only folder now allow
directory replacement to finish while scheduling the directory for a later scan
that can resurrect the local change, abandoned Syncthing temporary children can still be removed so
replacement can continue, second close attempts are no-ops, and failed pulls close while leaving
the temporary file for a later retry. This is a
static targeted mapping from upstream `sharedpullerstate.go`,
`sharedpullerstate_test.go`, `folder_sendrecv.go`,
`lib/protocol/bep_fileinfo.go`, and `lib/versioner`, plus a focused upstream
`go test ./lib/versioner -run 'TestTaggedFilename|TestTrashcanArchiveRestoreSwitcharoo|TestTrashcanRestoreDeletedFile'`
runner pass and a focused upstream
`go test ./lib/model -run 'TestPullDeleteUnscannedDir|TestPullDeleteIgnoreChildDir' -count=1`
pass. The unscanned-existing-file guard was cross-checked against upstream
`performFinish`/`scanIfItemChanged` plus a bounded upstream
`go test ./lib/model -run 'TestPullCaseOnlyPerformFinish|TestDeleteIgnorePerms' -count=1`
pass, not full upstream runner parity. The WordPress
example `wordpress-pull-temporary-finalize.php` shows a
media file assembled from one origin copy, one sparse zero block, and one
pulled block before final promotion. `wordpress-pull-temp-permissions.php`
shows a private media draft resuming from a read-only `.syncthing` temp file,
temporarily restoring owner write access, and finalizing back to restricted
WordPress permissions. `wordpress-pull-conflict-replacement.php` shows a
concurrent local WordPress media crop retained as a `.sync-conflict` sibling
before a Playground peer's version is promoted.
`wordpress-pull-directory-replacement.php` shows a stale generated media
directory being removed before a Playground archive file is promoted without a
conflict copy.
`wordpress-pull-version-archive.php` shows a non-conflicting previous WordPress
media version moved into `.stversions` before the Playground peer's file is
promoted.
`wordpress-pull-directory-scan-guard.php` shows a generated media directory
with an unknown local thumbnail preserved for a follow-up scan while the pulled
archive remains in its temporary file for retry.
`wordpress-pull-ignored-directory.php` shows a local private review cache
preserved by ignore rules when a Playground peer offers a replacement archive;
the pulled archive remains in its temporary file and no scan is scheduled for
the ignored path.
`wordpress-pull-receive-only-directory.php` shows a receive-only WordPress media
directory replaced by a Playground archive while the directory name is scheduled
for scanning so the local-only editor crop can be resurrected like upstream.
`wordpress-pull-case-only-conflict.php` shows a case-detecting local-first
media/plugin sync preserving the existing lowercase asset and the pulled temp
file while surfacing Syncthing's no-scan case-conflict error.
`wordpress-pull-unscanned-local-edit.php` shows a WordPress editor's local crop
preserved when the on-disk file no longer matches the scanned `FileInfo`; the
remote file stays in `.syncthing.<name>.tmp` and the media path is scheduled
for a scan before any replacement is attempted.
The receive-encrypted variant
`wordpress-pull-receive-encrypted-finalize.php` shows the trailer appended
during native temporary-file promotion, with local finalized size and remote
index size reported separately.
The post-promotion database-update slice now maps upstream `dbUpdaterRoutine`
around `performFinish`: pulled `FileInfo` updates are batched with the same
1000-file/250 KiB `FileInfoBatch` boundaries, changed directories are fsync
candidates for handle-file, shortcut-file, and handle-directory jobs,
sequences are reset to zero before local database update callbacks, timed ticks
flush partial batches, invalid and metadata-only updates do not emit received
file markers, successful batch updates emit RemoteChangeDetected-style events
for non-invalid files, and each flushed batch emits only the last received
file/delete candidate like upstream. This is a static targeted mapping from
`lib/model/folder_sendrecv.go` `dbUpdaterRoutine`, `lib/model/fileinfobatch.go`,
and `lib/model/folder.go` `updateLocalsFromPulling`/`emitDiskChangeEvents`,
not additional upstream runner parity. `wordpress-pull-db-updater.php` shows a
finalized Playground media pull being committed into a local WordPress index,
with the media parent directory fsync boundary, RemoteChangeDetected-style
payload, and ReceivedFile-style marker recorded.
The finisher lifecycle slice now maps upstream `finisherRoutine` around
`sharedPullerState.finalClose`: not-ready puller states leave queue/progress
state untouched, the first closed state completes the pull queue job, successful
finalization hands `dbUpdateHandleFile` to the DB updater and records
upstream-shaped block stats, failed final close records a `finishing:` temp pull
error, normal folders deregister progress-emitter state, receive-encrypted
folders skip that deregistration branch, and one ItemFinished-style event is
emitted for each handled state. This is backed by static targeted reads of
`lib/model/folder_sendrecv.go`, `lib/model/sharedpullerstate.go`, and
`lib/model/progressemitter.go`, plus a focused upstream pass:
`go test ./lib/model -run 'TestDeregisterOnFailInCopy|TestDeregisterOnFailInPull' -count=1`.
`wordpress-pull-finisher.php` shows a finalized Playground media pull completing
the queue, deregistering progress, emitting the event payload, recording block
stats, and handing off the database update.
The folder-error slice now maps upstream `sendReceiveFolder.pull`,
`pullerIteration`, `newPullError`, and `folder.Errors`: each pull clears
persistent pull errors, each puller iteration resets `tempPullErrors`, duplicate
errors for one path keep the first `syncing:` message, context cancellation is
ignored, only the final iteration's temporary errors are promoted into
persistent `pullErrors`, `FolderErrors` events include scan and pull errors
sorted by path, and a pull is in sync only when no items changed and no pull
errors were promoted. `wordpress-folder-errors.php` shows a failed Playground
media pull surfacing as a persistent WordPress media folder error while keeping
the temporary file for retry.
The pull-scanner slice now maps upstream `pullScannerRoutine` around the
scan-channel close boundary: finalization and deletion scan paths are collected
while pulling is still active, duplicate paths collapse into one pending scan
candidate, file and directory candidates remain classified for native PHP
bookkeeping, no scan callback fires before the pull scanner closes, close emits
one post-pull scan batch, repeat close attempts are idempotent, and a failed
`performFinish` path such as `file modified but not rescanned` still queues the
media file for a deferred scan. This is backed by static targeted reads of
`lib/model/folder_sendrecv.go` around `pullScannerRoutine`, `finisherRoutine`,
`performFinish`, `scanIfItemChanged`, `checkToBeDeleted`, and
`deleteDirOnDiskHandleChildren`, not full upstream runner parity.
`wordpress-post-pull-scan-scheduler.php` shows a local-first WordPress media
edit and stale Playground export folder remaining unscheduled while the pull is
open, then being emitted as one de-duplicated post-pull scan batch.
The directory/symlink item lifecycle slice now maps upstream `handleDir`,
`handleSymlink`, and `handleSymlinkCheckExisting`: native PHP creates or updates
WordPress upload directories and symlink aliases, emits ItemStarted and
ItemFinished payloads with upstream `dir`/`symlink` update types, schedules
`dbUpdateHandleDir` and `dbUpdateHandleSymlink`, moves a conflicting regular
file to a `.sync-conflict` sibling before replacing it with a remote directory,
queues the conflict copy for scan, and treats empty symlink targets as
incompatible entries without scheduling a database update. This is backed by
static targeted reads plus a focused upstream pass:
`go test ./lib/model -run 'TestCopyOwner|TestSRConflictReplaceFileByDir|TestSRConflictReplaceFileByLink|TestPullCaseOnlyDir|TestPullCaseOnlySymlink' -count=1`.
`wordpress-directory-symlink-lifecycle.php` shows a Playground peer creating a
dated upload directory and a `current/latest` symlink while the native DB updater
records the remote-change and fsync boundaries.
The delete item lifecycle slice now maps upstream `deleteFile`, `deleteDir`,
`checkToBeDeleted`, and `deleteDirOnDiskHandleChildren`: native PHP removes
tracked regular files and directories with upstream ItemStarted/ItemFinished
`delete` payloads, schedules `dbUpdateDeleteFile` and `dbUpdateDeleteDir`, does
not follow a symlinked parent when accepting a tombstone, accepts case-only
delete conflicts as database-only tombstones without scanning or deleting the
local sibling, moves a conflicting local regular file to a `.sync-conflict`
copy before accepting a remote delete, preserves unscanned local directories
while queuing a deferred scan, and removes `(?d)` ignored/temporary directory
children before deleting an otherwise empty remote-deleted directory. This is
backed by targeted reads plus a focused upstream pass:
`go test ./lib/model -run 'TestDeleteBehindSymlink|TestPullDeleteUnscannedDir|TestPullDeleteCaseConflict|TestPullDeleteIgnoreChildDir|TestIssue3164' -count=1`.
`wordpress-delete-lifecycle.php` shows a Playground peer deleting an old upload
and a deletable private cache directory while the native DB updater records
RemoteChangeDetected-style delete events and a ReceivedFile-style marker only
for the deleted regular file.
The process-deletions slice now maps upstream `processDeletions`: pending
regular-file tombstones are coalesced by path before deletion, those file
tombstones are processed before any directory tombstones, and directory
tombstones are processed in reverse order so nested upload folders are removed
depth-first after their deleted children are gone. This is backed by static
targeted reads of `lib/model/folder_sendrecv.go` around `processNeeded` and
`processDeletions`, plus the existing focused upstream delete lifecycle pass:
`go test ./lib/model -run 'TestDeleteBehindSymlink|TestPullDeleteUnscannedDir|TestPullDeleteCaseConflict|TestPullDeleteIgnoreChildDir|TestIssue3164' -count=1`.
`wordpress-process-deletions.php` shows a Playground peer deleting a stale
media file before removing its now-empty month and year upload directories,
with the native DB updater recording the same delete job order.
The rename-shortcut slice now maps upstream `processNeeded` and `renameFile`:
pending regular-file tombstones are bucketed by block identity, a same-block
target file can be satisfied by renaming an existing local source through a
Syncthing temporary name instead of pulling bytes again, the consumed source
tombstone is removed from later deletion processing, source delete and target
update ItemStarted/ItemFinished events share the rename result, changed source
or target files are queued for scan without becoming permanent pull errors, and
successful shortcuts schedule `dbUpdateHandleFile` for the target followed by
`dbUpdateDeleteFile` for the source. This is backed by targeted reads of
`lib/model/folder_sendrecv.go` around `processNeeded`, `popCandidate`,
`renameFile`, `checkToBeDeleted`, and `performFinish`, plus a focused upstream
pass:
`go test ./lib/model -run 'TestPullCaseOnlyRename' -count=1`.
`wordpress-rename-shortcut.php` shows a staged WordPress media import being
renamed into the dated uploads folder without re-downloading the same block
content, while the native DB updater records the target update and source
tombstone.
The metadata-shortcut slice now maps upstream `processNeeded` and
`shortcutFile`: needed regular files whose current local FileInfo has the same
block identity skip full handle-file work, emit ItemStarted/ItemFinished
`metadata` payloads, update permissions unless ignored, apply the upstream
mtime, schedule `dbUpdateShortcutFile`, fsync the parent directory through the
DB updater, and avoid `ReceivedFile` notifications because no content was
transferred. Missing on-disk files fail the metadata path without creating empty
placeholders, matching the upstream `Chtimes` error boundary. This is backed by
targeted reads of `lib/model/folder_sendrecv.go` around `processNeeded`,
`shortcutFile`, and `dbUpdaterRoutine`; no additional upstream package test was
found for this exact branch. `wordpress-metadata-shortcut.php` shows an existing
WordPress media item receiving a permissions/mtime-only update while a different
needed file remains queued for full pull work.
The receive-encrypted metadata-shortcut slice now maps the upstream
`shortcutFile` branch for receive-encrypted folders: after a same-block
metadata-only update, native PHP rewrites the encrypted FileInfo trailer at the
encrypted data-size boundary, truncates any previous trailer, expands the local
database FileInfo size by the new trailer length before scheduling
`dbUpdateShortcutFile`, preserves the encrypted data bytes, and still suppresses
`ReceivedFile` notifications because no content was transferred. This is backed
by targeted reads of `lib/model/folder_sendrecv.go` around `shortcutFile` and
`lib/model/sharedpullerstate.go` around `writeEncryptionTrailer`; no direct
upstream package test was found for this exact branch.
`wordpress-receive-encrypted-metadata-shortcut.php` shows an untrusted private
media folder updating only encrypted metadata while the local finalized file's
trailer and DB size are refreshed without re-downloading the encrypted block.
The receive-encrypted metadata shortcut retry slice now maps adjacent upstream
failure boundaries around `shortcutFile` and `writeEncryptionTrailer`: native
PHP opens the finalized encrypted file in existing-file read/write mode like
upstream `OpenFile` without create, trailer write failures leave the old
finalized bytes and database state untouched while recording a retryable pull
error, missing synthetic `.syncthing-enc` parent paths do not create placeholder
directories or files, and stale longer trailer bytes are truncated when the new
trailer is successfully committed. This is backed by targeted reads of
`lib/model/folder_sendrecv.go:1252-1319` and
`lib/model/sharedpullerstate.go:365-410`; no direct upstream package test was
found for this exact branch. `wordpress-receive-encrypted-shortcut-retry.php`
shows a private WordPress media export where a read-only finalized encrypted
file keeps the old trailer, emits a pull error, and avoids `dbUpdateShortcutFile`
until a later retry can rewrite the metadata trailer.
The bounded pull-iteration retry slice now maps upstream `pull`,
`pullerIteration`, and `newPullError` behavior: a pull clears old persistent
pull errors, each iteration starts with a fresh `tempPullErrors` map, the loop
continues while an iteration changed at least one item and stops after either a
zero-change iteration or three changed iterations, and only the final
iteration's temporary errors are promoted to a `FolderErrors` event. This is
backed by targeted reads of `lib/model/folder_sendrecv.go:184-235`,
`lib/model/folder_sendrecv.go:240-260`, and
`lib/model/folder_sendrecv.go:1896-1916`; no direct upstream package test was
found for this exact branch. `wordpress-receive-encrypted-retry-loop.php` shows
a private WordPress media metadata pull where a transient trailer-write error is
cleared before the next bounded iteration succeeds, so no persistent folder
error is emitted.
The platform metadata slice now maps upstream `setPlatformData` at the
metadata-only shortcut and `performFinish` boundaries: native FileInfo
PlatformData carries Unix owner/group IDs plus Linux xattr name/value pairs,
`shortcutFile` applies ownership and xattrs before `dbUpdateShortcutFile`,
`performFinish` applies the same metadata to the Syncthing temp file before
promotion and `dbUpdateHandleFile`, unsupported PHP xattr APIs are treated like
upstream unsupported xattr filesystems, and explicit xattr failures leave the
file retryable without a database update. This is backed by targeted reads of
`lib/model/folder_sendrecv.go` around `shortcutFile`, `performFinish`,
`setPlatformData`, `lib/model/folder_sendrecv_unix.go` around `syncOwnership`,
and `proto/bep/bep.proto` PlatformData/XattrData fields, plus focused upstream
runner evidence:
`go test ./lib/model -run '^TestCopyOwner$' -count=1`.
`wordpress-platform-metadata-shortcut.php` shows an existing WordPress media
file receiving permissions, mtime, ownership, and import-source xattrs without
retransferring the media bytes.
The SetXattr replacement slice now maps upstream
`BasicFilesystem.SetXattr`: current permitted xattrs are listed/read through the
configured filter, stale permitted xattrs absent from the desired FileInfo
PlatformData are removed, unchanged values are skipped, changed and new values
are set, filter-denied host attributes are preserved, and an empty desired
xattr set removes only permitted current attributes. Removal failures abort
before new xattrs are set, leaving the item retryable like upstream
`setPlatformData` errors. This is backed by targeted reads of
`lib/fs/basicfs_xattr_unix.go` around `SetXattr` and
`lib/fs/basicfs_test.go` around `TestXattr`, plus focused upstream runner
evidence: `go test ./lib/fs -run '^TestXattr$' -count=1` passed in
`.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing` with
`ok github.com/syncthing/syncthing/lib/fs 0.013s`.
`wordpress-xattr-replacement.php` shows a WordPress media file dropping stale
Syncthing-managed import metadata, preserving a host-managed security xattr,
skipping an unchanged import-source value, and setting changed/new media
identity metadata.
The xattr no-follow and unsupported-filesystem slice now maps adjacent upstream
boundaries from `BasicFilesystem` and `setPlatformData`: Linux/BSD paths use
`Llistxattr`, `Lgetxattr`, `Lremovexattr`, and `Lsetxattr` so symlink xattrs
belong to the link rather than the target, unsupported-platform `GetXattr` and
`SetXattr` return the upstream `ErrXattrsNotSupported` sentinel, and
`setPlatformData` ignores that sentinel while still surfacing ordinary
list/get/set/remove failures as retryable metadata errors. This is backed by
targeted reads of `lib/fs/basicfs_xattr_unix.go`,
`lib/fs/basicfs_xattr_linuxish.go`, `lib/fs/basicfs_xattr_unsupported.go`,
`lib/model/folder_sendrecv.go`, `lib/scanner/walk.go`, and
`lib/fs/platform_common.go`, plus a refreshed focused upstream
`go test ./lib/fs -run '^TestXattr$' -count=1` run in the hydrated
local-capacity worktree. `wordpress-xattr-unsupported.php` shows a WordPress
media file on a shared-hosting/no-xattr filesystem continuing sync without
attempting metadata writes.
The scanner platform metadata slice now maps upstream `scanner.CreateFileInfo`
and `filesystem.PlatformData`: native `FileInfoScanner` validates canonical
relative paths, reads lstat ownership for files, directories, and symlinks,
preserves symlink targets without following them, filters xattrs before reading
values from the link path for symlinks, applies upstream-style per-entry and
total xattr size caps, propagates platform-data read failures, and can hash file
blocks for a complete WordPress media `FileInfo`. This is backed by targeted
reads of `lib/scanner/walk.go`
around `CreateFileInfo`, `lib/fs/platform_common.go`, and
`lib/fs/basicfs_xattr_unix.go`, plus focused upstream runner evidence:
`go test ./lib/scanner -run '^TestScanOwnershipPOSIX$' -count=1` and
`go test ./lib/fs -run '^TestXattr$' -count=1` both passed in
`.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing`.
`wordpress-scanner-platform-metadata.php` shows a local-first WordPress media
file scanned with owner/group IDs, filtered `user.wordpress.*` xattrs, block
hashing, and xattr-ignored equivalence for hosts that cannot scan xattrs.
The xattr hard-error and deterministic listing-order slice now maps upstream
`BasicFilesystem.GetXattr`, `BasicFilesystem.SetXattr`, `listXattr`, and
`setPlatformData`: listed xattr names are processed in sorted order before
filtered reads, list/get/remove/set errors stop later metadata writes and return
retryable metadata errors, and missing PHP host xattr functions behave like the
upstream unsupported-xattrs sentinel. `wordpress-xattr-hard-error-retry.php`
shows a WordPress media metadata update where a read-only xattr removal error
preserves the stale host metadata, blocks new metadata writes, and prevents the
database update until a later retry succeeds.
The scanner walk ignore-pruning slice now maps focused upstream `Walk`,
`WalkWithoutHashing`, `scan`, `walkAndHashFiles`, `Matcher.Match`,
`fs.IsTemporary`, `fs.IsInternal`, `ignoredParent`, and `handleItem`
boundaries. Native `FileInfoScanner::walk()` accepts slash-rooted sub paths,
skips Syncthing internal and temporary names, prunes ignored directories when
the matcher says they are safe to skip, keeps walking ignored directories when
negated child patterns require it, emits ignored ancestor directories before an
included descendant, and can hash regular WordPress media files while walking.
`wordpress-scanner-walk-includes.php` shows an ignore-all WordPress media scan
that still emits the included public upload ancestor chain and hashed media
file while excluding `.stfolder`, `.stignore`, private exports, and stale
`.syncthing.*.tmp` files.
The scanner block-size hysteresis slice now maps upstream
`TestBlocksizeHysteresis` and `walkRegular`: PHP selects the normal Syncthing
block size from file length, but retains the current indexed `FileInfo` block
size when the new and old sizes differ by at most a factor of two in either
direction. `FileInfoScanner::scan()` and `FileInfoScanner::walk()` can accept
current FileInfo state so a WordPress media rescan does not unnecessarily
change block boundaries for an existing indexed upload. `wordpress-scanner-block-hysteresis.php`
shows a small local-first media file retaining its prior 256 KiB block size and
prints the upstream 500 MiB hysteresis cases.
The scanner unchanged-file slice now maps upstream `walkRegular`,
`updateFileInfo`, `FileInfo.IsEquivalentOptional`, and `TestWalkReceiveOnly`:
`FileInfoScanner::walk()` skips current files that are equivalent after
ignoring block lists, masks the scanner's configured local flags during
equivalence checks, forces a rescan when a prior ignored state differs from the
new receive-only state, and carries the prior `blocksHash` into
`previousBlocksHash` when a rescan is emitted. `wordpress-scanner-unchanged-shortcut.php`
shows an unchanged WordPress media upload producing no second scan item while a
prior ignored local state forces a receive-only rescan with conflict lineage.
The scanner current-file equivalence slice now also maps the upstream
`IgnorePerms` and `ModTimeWindow` boundaries used by `walkRegular`, `walkDir`,
and `walkSymlink`: permission-only changes are skipped when the folder ignores
permissions, scanned regular files and directories advertise `NoPermissions`
under that setting, one-second filesystem timestamp drift can be ignored inside
the configured window, the boundary remains strict (`diff < window`), and
unchanged symlink targets are skipped instead of re-emitted. The WordPress
example `wordpress-scanner-ignoreperms-window.php` shows a shared-hosting media
file where chmod noise and FAT-style timestamp truncation do not create a
spurious sync item, while strict scanning still detects both changes.
The scanner normalization slice now maps upstream `normalizePath`,
`applyNormalization`, `errUTF8Normalization`, `errUTF8Conflict`, and
`TestNormalization`: native walking validates UTF-8 path names, reports
non-NFC names when auto-normalization is disabled, renames decomposed UTF-8
WordPress media paths to NFC before emitting `FileInfo` when enabled, and
refuses to replace an existing normalized sibling. The WordPress example
`wordpress-scanner-normalization.php` shows a decomposed upload filename from a
Mac-style export being rejected by strict scanning, then normalized on disk and
hashed under the NFC Syncthing wire name.
The scanner progress slice now maps the upstream `ProgressTickIntervalS`,
`newByteCounter`, `Blocks`, and `FolderScanProgress` boundaries: native PHP
buffers changed regular files before hashing so the progress denominator is
`1 + bytes-to-hash`, emits folder/current/total/rate payloads as hashing
advances, preserves the existing walk order for directories and files, and
emits no progress for unchanged or metadata-only walks. The WordPress example
`wordpress-scanner-progress-events.php` shows a media-library scan reporting
Syncthing-style progress totals while producing hashed `FileInfo` entries.
The scanner error/cancel slice now maps adjacent upstream `handleError`,
`ScanResult.Err`, `isWarnableError`, `parallelHasher.hashFiles`, and
`TestStopWalk` boundaries: direct scans remain strict, while opt-in
`FileInfoScanner::walk()` callbacks can collect per-path scan or hash errors
and can stop traversal or queued progress hashing before another file starts.
`wordpress-scanner-error-cancel.php` shows a WordPress media scan reporting a
retryable metadata read error, hashing one changed upload, and cancelling
before the queued thumbnail is returned as a partial un-hashed `FileInfo`.
The scanner Windows executable-bit slice now maps the host-specific upstream
`updateFileInfo` branch in `lib/scanner/walk.go`: for regular files on
Windows, the scanner copies executable bits from the current indexed
`FileInfo` onto newly scanned permissions before equivalence checks. The native
PHP scanner exposes a deterministic `platformFamily` override so this
Linux-hosted lane can test the Windows branch without shelling out to Syncthing.
`wordpress-scanner-windows-exec-bits.php` shows a Windows/IIS-style WordPress
plugin asset with disk mode `0644` retaining indexed `0755` executable bits and
avoiding a spurious permission-only scan item.
The scanner Windows symlink slice now maps the adjacent upstream
`handleItem`/`walkSymlink` host boundary in `lib/scanner/walk.go`: POSIX scans
emit symlink `FileInfo` entries without following their targets, while Windows
scans ignore symlink entries entirely. `wordpress-scanner-windows-symlink-skip.php`
shows Windows-mode WordPress media scans skipping file and directory symlink
aliases so they are not advertised as synced content on hosts where upstream
Syncthing does not support symlinks.
The scanner symlink-parent sub-walk guard now maps upstream `scan` and
`osutil.TraversesSymlink(filepath.Dir(sub))` behavior: a direct sub request for
a symlink itself can still advertise the symlink `FileInfo`, but a direct sub
request below a symlinked parent is skipped instead of walking through the
alias. `wordpress-scanner-symlink-parent-sub.php` shows a WordPress media
library alias being advertised while a direct scan for a file below that alias
is skipped and the canonical media path remains scannable.

## Test Run Notes

On 2026-05-23, the focused scanner tests
`php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php` passed 1
file, 59 assertions, and 0 failures after adding upstream
`TestWalkReceiveOnly` and `TestBlocksizeHysteresis` mappings on top of the existing `TestWalkSub`,
`TestRecurseInclude`/`TestIncludedSubdir`, `TestSkipIgnoredDirs`,
`TestNotExistingError`, and `TestIssue4799` mappings. The focused block-list
test `php tools/run-tests.php lanes/syncthing/tests/BlockListTest.php` passed 1
file, 35 assertions, and 0 failures. The focused xattr metadata test
`php tools/run-tests.php lanes/syncthing/tests/PlatformMetadataApplierTest.php`
passed 1 file, 24 assertions, and 0 failures. The full Syncthing lane suite
`php tools/run-tests.php lanes/syncthing/tests` passed 39 files, 2061
assertions, and 0 failures. The WordPress example
`php lanes/syncthing/examples/wordpress-xattr-hard-error-retry.php` ran
successfully and reported a retryable `setting xattrs: remove
user.wordpress.old-import: read-only filesystem` error with no new xattr writes
or database update. The new WordPress example
`php lanes/syncthing/examples/wordpress-scanner-walk-includes.php` ran
successfully and emitted the included public upload chain plus a hashed public
media file while keeping private and internal paths out of the scan. The new
WordPress example `php lanes/syncthing/examples/wordpress-scanner-block-hysteresis.php`
ran successfully and reported default 128 KiB block sizing, retained 256 KiB
current-file sizing, and upstream 500 MiB hysteresis examples. The new
WordPress example `php lanes/syncthing/examples/wordpress-scanner-unchanged-shortcut.php`
ran successfully and reported `unchangedSecondScanItems=0`,
`ignoredPriorStateForcesRescan=true`, and `previousBlocksHashCarried=true`.
Later on 2026-05-23, after the scanner `IgnorePerms`/mod-time-window slice,
`php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php` passed
1 file, 79 assertions, and 0 failures; `php tools/run-tests.php
lanes/syncthing/tests` passed 39 files, 2081 assertions, and 0 failures; and
`php lanes/syncthing/examples/wordpress-scanner-ignoreperms-window.php` ran
successfully with `strictPermissionChangeItems=1`,
`ignorePermsPermissionChangeItems=0`, `strictOneSecondMtimeChangeItems=1`,
and `windowedOneSecondMtimeChangeItems=0`.

Focused upstream `go test ./lib/fs -run '^TestXattr$' -count=1` passed in
`.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing` with
`ok github.com/syncthing/syncthing/lib/fs 0.006s`. Static upstream inventory
remains 610 Test functions, 48 Benchmark functions, and 0 Fuzz functions across
141 `_test.go` files; the full upstream runner remains unexecuted because the
primary cache is blob-filtered/no-checkout with mass tracked deletions, and
broad `go test ./...` would require hydrating the full checkout, downloading
and building the full Go module graph, and running the integration test tree.
Focused upstream scanner walk evidence was refreshed with
`go test ./lib/scanner -run 'TestWalk$|TestWalkSub|TestRecurseInclude|TestIncludedSubdir|TestSkipIgnoredDirs|TestNotExistingError|TestIssue4799' -count=1`,
which passed in `.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing`
with `ok github.com/syncthing/syncthing/lib/scanner 0.024s`.
Focused upstream scanner block-size evidence was refreshed with
`go test ./lib/scanner -run '^TestBlocksizeHysteresis$' -count=1`, which passed
in the same hydrated worktree with
`ok github.com/syncthing/syncthing/lib/scanner 1.556s`.
Focused upstream scanner unchanged-file evidence was refreshed with
`go test ./lib/scanner -run '^TestWalkReceiveOnly$' -count=1`, which passed in
the same hydrated worktree with
`ok github.com/syncthing/syncthing/lib/scanner 0.007s`.
Focused upstream evidence for the previous scanner equivalence batch was refreshed with
`go test ./lib/model -run '^TestModTimeWindow$' -count=1`, which passed in
`.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing` with
`ok github.com/syncthing/syncthing/lib/model 0.028s`, and
`go test ./lib/scanner -run '^TestWalkSymlinkUnix$' -count=1`, which passed in
the same worktree with `ok github.com/syncthing/syncthing/lib/scanner 0.016s`.
Focused upstream evidence for this normalization batch was refreshed with
`go test ./lib/scanner -run '^TestNormalization$' -count=1`, which passed in
`.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing` with
`ok github.com/syncthing/syncthing/lib/scanner 0.024s`.

Before root PHP harnesses, this worker ran the required
`pgrep -af '^php tools/run-tests\.php( |$)'` check before starting any root
harness. It returned no active root harness, so this worker ran
`php tools/run-tests.php`; that root run exited red with 190 test files, 20674
assertions, and 1 failure in the moving aggregate. A later required pgrep found
another root harness active as PID 1341411 owned by `claude`, so this worker did
not start a duplicate root rerun at that time. After the active harness cleared,
this worker reran `php tools/run-tests.php` captured to
`.upstream-cache/syncthing-root-rerun.log`; it passed 191 test files, 20725
assertions, and 0 failures. No Syncthing lane-local test failed.
For this batch, the required pre-root
`pgrep -af '^php tools/run-tests\.php( |$)'` check again returned no active
root harness, and `php tools/run-tests.php` passed 193 test files, 20939
assertions, and 0 failures.
For the scanner normalization batch, the focused upstream
`go test ./lib/scanner -run '^TestNormalization$' -count=1` passed with
`ok github.com/syncthing/syncthing/lib/scanner 0.024s`. The focused lane test
`php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php` passed
1 file, 87 assertions, and 0 failures; the full lane run
`php tools/run-tests.php lanes/syncthing/tests` passed 39 files, 2089
assertions, and 0 failures; and
`php lanes/syncthing/examples/wordpress-scanner-normalization.php` ran
successfully, reporting the strict normalization error, `decomposedPathExists=false`,
and `normalizedPathExists=true`. The required pre-root
`pgrep -af '^php tools/run-tests\.php( |$)'` check returned no active root
harness, so this worker ran `php tools/run-tests.php`; it passed 193 test
files, 21102 assertions, and 0 failures.
For the scanner progress batch, focused upstream
`go test ./lib/scanner -run '^TestVerify$|^TestWalk$' -count=1` passed in
`.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing` with
`ok github.com/syncthing/syncthing/lib/scanner 0.010s`. The focused lane test
`php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php` passed
1 file, 97 assertions, and 0 failures; the full lane run
`php tools/run-tests.php lanes/syncthing/tests` passed 39 files, 2099
assertions, and 0 failures; and
`php lanes/syncthing/examples/wordpress-scanner-progress-events.php` ran
successfully, reporting FolderScanProgress-style totals `0/14`, `8/14`, and
`13/14` while hashing changed WordPress media files.
The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
returned no active root harness, so this worker ran `php tools/run-tests.php`;
it passed 196 test files, 21368 assertions, and 0 failures.
For the scanner Windows executable-bit batch, targeted upstream static reads of
`lib/scanner/walk.go` counted one `updateFileInfo` branch copying current
executable bits with `dst.Permissions |= (src.Permissions & 0o111)`, and
`lib/scanner/walk_test.go` counted the Windows-only `TestScanOwnershipWindows`
test function. The focused upstream command
`go test ./lib/scanner -run '^TestScanOwnershipWindows$' -count=1 -v` passed by
skipping on this Linux host with `--- SKIP: TestScanOwnershipWindows` and
`ok github.com/syncthing/syncthing/lib/scanner 0.007s`; this is static
host-specific evidence, not executed Windows runner parity. The focused lane
test `php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php`
passed 1 file, 101 assertions, and 0 failures; the full lane run
`php tools/run-tests.php lanes/syncthing/tests` passed 39 files, 2103
assertions, and 0 failures; and
`php lanes/syncthing/examples/wordpress-scanner-windows-exec-bits.php` ran
successfully with `posixPermissionChangeItems=1`,
`windowsPermissionChangeItems=0`, and `windowsAdvertisedPermissions=0755`.
The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
returned no active root harness, so this worker ran `php tools/run-tests.php`;
it passed 196 test files, 21507 assertions, and 0 failures.
For the scanner error/cancel batch, targeted upstream reads covered
`lib/scanner/walk.go` `handleError`, `isWarnableError`, context cancellation
checks, and `lib/scanner/blockqueue.go` `parallelHasher.hashFiles` error
handling. The focused upstream command
`go test ./lib/scanner -run '^TestStopWalk$' -count=1` passed in
`.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing` with
`ok github.com/syncthing/syncthing/lib/scanner 0.155s`. The focused lane test
`php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php` passed
1 file, 107 assertions, and 0 failures; the full lane run
`php tools/run-tests.php lanes/syncthing/tests` passed 39 files, 2109
assertions, and 0 failures; and
`php lanes/syncthing/examples/wordpress-scanner-error-cancel.php` ran
successfully with one scan error, one `8/14` progress event, and no partial
thumbnail `FileInfo`. The required pre-root
`pgrep -af '^php tools/run-tests\.php( |$)'` check returned no active root
harness, so this worker ran `php tools/run-tests.php`; it passed 197 test
files, 21712 assertions, and 0 failures.
For this scanner Windows symlink batch, targeted upstream reads covered
`lib/scanner/walk.go` `handleItem` and the Windows-only `walkSymlink` early
return, plus `lib/scanner/walk_test.go` `TestWalkSymlinkUnix`. The refreshed
focused upstream command `go test ./lib/scanner -run '^TestWalkSymlinkUnix$'
-count=1` passed in `.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing`
with `ok github.com/syncthing/syncthing/lib/scanner 0.013s`. The Windows
branch remains static host-specific evidence, not executed Windows runner
parity. The focused lane test
`php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php` passed
1 file, 113 assertions, and 0 failures; and
`php lanes/syncthing/examples/wordpress-scanner-windows-symlink-skip.php` ran
successfully, reporting POSIX symlink entries and Windows-mode skipped symlink
paths.
The full lane run `php tools/run-tests.php lanes/syncthing/tests` passed 39
files, 2115 assertions, and 0 failures. The required pre-root
`pgrep -af '^php tools/run-tests\.php( |$)'` check returned no active root
harness, so this worker ran `php tools/run-tests.php`; it passed 198 files,
21849 assertions, and 0 failures.

## Next Task

Map scanner subdir traversal guards for paths below symlinked parents.

## 2026-05-23 Scanner Resume Checkpoints

Targeted upstream reads covered `lib/scanner/walk.go` context cancellation and
progress buffering, `lib/scanner/blockqueue.go` hasher cancellation, and
upstream `TestStopWalk`. The refreshed focused upstream command
`go test ./lib/scanner -run '^TestStopWalk$' -count=1` passed in
`.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing` with
`ok github.com/syncthing/syncthing/lib/scanner 0.131s`.

Native PHP now exposes `FileInfoScanner::walkWithCheckpoint()`, which preserves
the existing `walk()` behavior while returning completed `FileInfo` entries,
the first cancelled path, and normalized resume subs. A later scan can pass the
checkpoint files as current state and hash only the remaining queued media item.
`wordpress-scanner-resume-checkpoint.php` demonstrates a WordPress media scan
cancelled after `hero.jpg`, then resumed to hash only `thumb.jpg`.

Verification for this batch:

- `php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php`
  passed 1 file, 124 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests` passed 39 files, 2126
  assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-scanner-resume-checkpoint.php` ran
  successfully and reported cancellation at
  `wp-content/uploads/2026/05/thumb.jpg` plus a resumed thumbnail-only scan.
- The required pre-root gate first saw transient PID 2226546
  (`php tools/run-tests.php lanes/readability/tests`), which exited before owner
  sampling; a second exact gate was clear, so this worker ran
  `php tools/run-tests.php`, which passed 198 files, 22059 assertions, and 0
  failures.

## 2026-05-23 Scanner Temporary Cleanup

Targeted upstream reads covered `lib/scanner/walk.go` `Config.TempLifetime`,
the fixed scan `now`, the `fs.IsTemporary` branch that skips temporary names
and removes stale regular temp files, `lib/model/folder.go` wiring from
`KeepTemporariesH`, and `lib/fs/tempname.go` basename-prefix detection. No
direct upstream package test for this cleanup branch was found; this batch uses
static targeted upstream evidence plus native PHP behavior coverage.

Native PHP `FileInfoScanner` now keeps the upstream default 24-hour temporary
lifetime while allowing an explicit `tempLifetimeSeconds` override. During
walks, temporary basenames are omitted from FileInfo output, stale regular temp
files older than the configured lifetime are unlinked, fresh temp files are
kept for reuse, and temp directories are left untouched. The WordPress example
`wordpress-scanner-temporary-cleanup.php` shows a media publish scan advertising
only the finalized upload while removing stale Unix and Windows temporary
files.

Verification for this batch:

- `php -l lanes/syncthing/src/FileInfoScanner.php` passed.
- `php -l lanes/syncthing/tests/FileInfoScannerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-scanner-temporary-cleanup.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php`
  passed 1 file, 131 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests` passed 39 files, 2133
  assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-scanner-temporary-cleanup.php` ran
  successfully and reported one advertised finalized media item, fresh temp
  kept, stale Unix temp removed, and stale Windows temp removed.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned no active root harness, so this worker ran `php tools/run-tests.php`;
  it passed 198 files, 22201 assertions, and 0 failures.

## 2026-05-23 Scanner Symlink Parent Sub Guard

Targeted upstream reads covered `lib/scanner/walk.go` where each configured
sub-walk calls `osutil.TraversesSymlink(filepath.Dir(sub))` before walking, plus
`lib/osutil/traversessymlink.go` and `traversessymlink_test.go` for the
component-by-component symlink and missing-path boundaries. Focused upstream
commands passed in `.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing`:
`go test ./lib/scanner -run 'TestWalkSub|TestIssue4799|TestWalkSymlinkUnix' -count=1`
with `ok github.com/syncthing/syncthing/lib/scanner 0.016s`, and
`go test ./lib/osutil -run '^TestTraversesSymlink$|^TestIssue4875$' -count=1`
with `ok github.com/syncthing/syncthing/lib/osutil 0.011s`.

Native PHP `FileInfoScanner::walk()` now checks each normalized sub's parent
components with `lstat()` before walking. Directly walking the symlink path
still emits the symlink FileInfo on POSIX, but walking `linked-library/file.jpg`
is skipped when `linked-library` is a symlink, matching upstream's sub-walk
guard. The WordPress example `wordpress-scanner-symlink-parent-sub.php`
demonstrates this for a media-library alias.

Verification for this batch:

- `php -l lanes/syncthing/src/FileInfoScanner.php` passed.
- `php -l lanes/syncthing/tests/FileInfoScannerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-scanner-symlink-parent-sub.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php`
  passed 1 file, 138 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests` passed 39 files, 2140
  assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-scanner-symlink-parent-sub.php` ran
  successfully and reported `subBelowSymlinkParentSkipped=true`.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned no active root harness. The first root run later exited red in the
  moving aggregate with 198 files, 22337 assertions, and 3 failures. A second
  gated root run captured to `.upstream-cache/syncthing-root-rerun.log` waited
  on the root lock and then passed 198 files, 22371 assertions, and 0 failures.

## 2026-05-23 Scanner Sub-Walk Diagnostics

Targeted upstream reads covered `lib/scanner/walk.go` configured sub handling,
`lib/osutil/traversessymlink.go`, `traversessymlink_test.go`,
`TestNotExistingError`, and `TestIssue4799`. The relevant upstream boundary is:
`osutil.TraversesSymlink(filepath.Dir(sub))` blocks paths below symlinked
parents and regular-file parent components, returns no error for missing parent
components, and direct file or symlink sub roots are handled by the subsequent
filesystem walk. Focused upstream commands passed in
`.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing`:
`go test ./lib/scanner -run 'TestNotExistingError|TestIssue4799|TestWalkSub' -count=1`
with `ok github.com/syncthing/syncthing/lib/scanner 0.010s`, and
`go test ./lib/osutil -run '^TestTraversesSymlink$|^TestIssue4875$' -count=1`
with `ok github.com/syncthing/syncthing/lib/osutil 0.008s`.

Native PHP `FileInfoScanner` now exposes `diagnoseSubWalk()` and the
`ScannerSubWalkDiagnostic` value object. The diagnostic distinguishes allowed
sub roots, missing direct roots, missing parent components, not-a-directory
parent components, and traverses-symlink parent components while preserving the
scanner's no-result output for blocked or missing subs. The WordPress example
`wordpress-scanner-sub-walk-diagnostics.php` shows a media-library preflight
for an allowed symlink alias, a blocked path below that alias, a path below a
regular-file parent, a missing parent, and a missing direct upload root.

Verification for this batch:

- `php -l lanes/syncthing/src/FileInfoScanner.php` passed.
- `php -l lanes/syncthing/src/ScannerSubWalkDiagnostic.php` passed.
- `php -l lanes/syncthing/tests/FileInfoScannerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-scanner-sub-walk-diagnostics.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php`
  passed 1 file, 151 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests` passed 39 files, 2153
  assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-scanner-sub-walk-diagnostics.php`
  ran successfully and reported `allowed`, `traverses-symlink`,
  `not-a-directory`, `missing-parent`, and `missing` statuses.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned no active root harness, so this worker ran `php tools/run-tests.php`;
  it passed 199 files, 22555 assertions, and 0 failures.

## 2026-05-23 Scanner Walk Failure Boundary

Targeted upstream reads covered `lib/scanner/walk.go` `scan`,
`walkFailureEventDesc`, `isWarnableError`, `handleError`, configured-sub
`Filesystem.Walk` calls, and the scanner tests `TestNotExistingError`,
`TestIssue4799`, `TestWalkSub`, `TestStopWalk`, and `TestIncludedSubdir`.
Focused upstream `go test ./lib/scanner -run
'TestNotExistingError|TestIssue4799|TestWalkSub|TestStopWalk|TestIncludedSubdir'
-count=1` passed in
`.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing` with
`ok github.com/syncthing/syncthing/lib/scanner 0.152s`.

Native PHP `FileInfoScanner` now treats directory listing failures as
path-level scan errors instead of silently converting them to empty directories.
This keeps siblings walkable and keeps these per-path scan failures separate
from Syncthing's global `Failure` event boundary for warnable whole-walk
aborts. The scanner also exposes the upstream Failure event description and an
`isWarnableWalkFailure()` helper for the context-cancellation boundary. The
WordPress example `wordpress-scanner-error-cancel.php` now shows a media-library
scan with both a retryable metadata error and a blocked private-cache directory
listing error, while reporting no global Failure event for those path-level
scan errors.

Verification for this batch:

- `php -l lanes/syncthing/src/FileInfoScanner.php` passed.
- `php -l lanes/syncthing/tests/FileInfoScannerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-scanner-error-cancel.php` passed.
- `php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php`
  passed 1 file, 160 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests` passed 39 files, 2162
  assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-scanner-error-cancel.php` ran and
  reported metadata and private-cache scan errors plus an empty
  `walkFailureEvents` list.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned active root harness PID `2609498`; owner evidence from
  `ps -o pid,user,ppid,etime,stat,args -p 2609498` showed
  `2609498 claude 2609497 00:17 R php tools/run-tests.php`. No duplicate root
  run was started; root result remains pending for the supervisor/integrator.

## 2026-05-23 Scanner Failure Event Collector

Targeted upstream reads covered `lib/scanner/walk.go` `EventLogger.Log`,
`events.Failure`, `walkFailureEventDesc`, root and configured-sub
`Filesystem.Walk` abort handling, and the existing scanner tests
`TestNotExistingError`, `TestIssue4799`, `TestWalkSub`, `TestStopWalk`, and
`TestIncludedSubdir`. Refreshed focused upstream evidence passed in
`.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing`:
`go test ./lib/scanner -run 'TestNotExistingError|TestIssue4799|TestWalkSub|TestStopWalk|TestIncludedSubdir' -count=1`
with `ok github.com/syncthing/syncthing/lib/scanner 0.188s`.

Native PHP now adds `FolderScanEventCollector`, a folder-scoped event sink that
can be passed directly to `FileInfoScanner` as progress, path-error, and
Failure-event callbacks. Whole-walk root aborts now emit the upstream `Failure`
event through that callback before rethrowing, while context cancellation and
deadline errors remain suppressed by the existing `isWarnableWalkFailure()`
boundary. Path-level scan errors remain separate from global Failure events.
The WordPress example `wordpress-scanner-failure-events.php` shows a media
folder scan abort caused by an unavailable volume and records the resulting
folder-scoped Failure event for UI or REST status reporting.

Verification for this batch:

- `php -l lanes/syncthing/src/FileInfoScanner.php` passed.
- `php -l lanes/syncthing/src/FolderScanEventCollector.php` passed.
- `php -l lanes/syncthing/tests/FolderScanEventCollectorTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-scanner-failure-events.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanEventCollectorTest.php`
  passed 1 file, 13 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php`
  passed 1 file, 160 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests/FolderErrorTrackerTest.php`
  passed 1 file, 43 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests` passed 40 files, 2175
  assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-scanner-failure-events.php` ran and
  reported one folder-scoped `Failure` event and no path-level scan errors.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned no active exact root harness. The root harness then waited on the
  root lock and completed red with 201 files, 23071 assertions, and 1 failure:
  the unrelated readability test `maps Mozilla wordpress fixture articleBody
  images and Jetpack cleanup` expected `NULL` and got `Sarah Gooding`.

## 2026-05-23 Scanner Checkpoint Event Result

Targeted upstream reads covered `lib/scanner/walk.go` `EventLogger.Log` for
`FolderScanProgress`, path-level `ScanResult` errors emitted by `handleError`,
`events.Failure`, `walkFailureEventDesc`, `isWarnableError`, and scanner tests
`TestNotExistingError`, `TestIssue4799`, `TestWalkSub`, `TestStopWalk`, and
`TestIncludedSubdir`. Refreshed focused upstream evidence passed in
`.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing`:
`go test ./lib/scanner -run 'TestNotExistingError|TestIssue4799|TestWalkSub|TestStopWalk|TestIncludedSubdir' -count=1`
with `ok github.com/syncthing/syncthing/lib/scanner 0.180s`.

Native PHP now lets `FileInfoScanResult` carry a `FolderScanEventCollector`.
`FileInfoScanner::walkWithCheckpoint()` can compose collector progress,
path-error, and Failure callbacks with any caller callbacks, so resumable scan
results persist completed paths, checkpoint subs, `FolderScanProgress` events,
path scan errors, and global Failure events together. Existing callers without
a collector keep the previous checkpoint `toArray()` shape. The WordPress
example `wordpress-scanner-checkpoint-events.php` shows a media scan whose
checkpoint includes completed uploads, progress events, a private-cache
directory listing error, and no global Failure event.

Verification for this batch:

- `php -l lanes/syncthing/src/FileInfoScanResult.php` passed.
- `php -l lanes/syncthing/src/FileInfoScanner.php` passed.
- `php -l lanes/syncthing/src/FolderScanEventCollector.php` passed.
- `php -l lanes/syncthing/tests/FileInfoScannerTest.php` passed.
- `php -l lanes/syncthing/tests/FolderScanEventCollectorTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-scanner-checkpoint-events.php`
  passed.
- `php -l lanes/syncthing/examples/wordpress-scanner-failure-events.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php lanes/syncthing/tests/FolderScanEventCollectorTest.php`
  passed 2 files, 181 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests` passed 40 files, 2183
  assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-scanner-checkpoint-events.php` ran
  and reported completed files, two `FolderScanProgress` events, one
  private-cache scan error, and no Failure events.
- `php lanes/syncthing/examples/wordpress-scanner-failure-events.php` ran and
  reported one folder-scoped `Failure` event and no path-level scan errors.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned no active exact root harness, so this worker ran
  `php tools/run-tests.php`; it completed red with 202 files, 23312
  assertions, and 3 unrelated aggregate failures visible in the readability
  lane output. No lane commit was made.

## 2026-05-23 Scanner Checkpoint Status Payload

Targeted upstream reads covered `lib/scanner/walk.go` `CurrentFiler`,
`ScanResult`, `FolderScanProgress`, `handleError`, and the unchanged-file
checks in `walkRegular`, `walkDir`, and `walkSymlink`, plus
`lib/scanner/walk_test.go` `TestWalkReceiveOnly`, `TestStopWalk`,
`TestNotExistingError`, `TestIncludedSubdir`, and `fakeCurrentFiler`. Refreshed
focused upstream evidence passed in
`.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing`:
`go test ./lib/scanner -run '^(TestWalkReceiveOnly|TestStopWalk|TestNotExistingError|TestIncludedSubdir)$' -count=1`
with `ok github.com/syncthing/syncthing/lib/scanner 0.115s`.

Native PHP now adds `FolderScanCheckpoint`, a durable folder-scan status value
object. It merges repeated `FileInfoScanResult` attempts by keeping the latest
`FileInfo` per path for CurrentFiler-style resume scans, appending progress,
path-error, and Failure events, preserving cancellation state only while the
latest attempt is cancelled, and emitting a WordPress REST-friendly status
payload. The WordPress example
`wordpress-folder-scan-status-payload.php` shows a media scan cancelled after
one hashed upload, resumed with checkpoint current files, and published as a
merged status payload with attempts, completed upload paths, latest progress,
and scan/failure counts.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderScanCheckpoint.php` passed.
- `php -l lanes/syncthing/tests/FolderScanCheckpointTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-folder-scan-status-payload.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanCheckpointTest.php`
  passed 1 file, 33 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php lanes/syncthing/tests/FolderScanEventCollectorTest.php lanes/syncthing/tests/FolderScanCheckpointTest.php`
  passed 3 files, 214 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests` passed 41 files, 2216
  assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-folder-scan-status-payload.php` ran
  successfully and reported a complete two-attempt REST-style folder status.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned no active exact root harness, so this worker ran
  `php tools/run-tests.php`; it passed 204 files, 23532 assertions, and 0
  failures.

## 2026-05-23 Folder Scan Service Persistence

Targeted upstream reads covered `lib/model/model.go` `ScanFolders`,
`ScanFolder`, and `ScanFolderSubdirs`, `lib/model/folder.go` `Scan`,
`doInSync`, `scanSubdirs`, `newScanBatch`, `Flush`, `CurrentFiler`, and
`clearScanErrors`, plus upstream tests `TestIssue3804`, `TestIssue3829`, and
`TestFolderAPIErrors`. Focused upstream evidence passed in
`.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing`:
`go test ./lib/model -run '^(TestIssue3804|TestIssue3829|TestFolderAPIErrors)$' -count=1`
with `ok github.com/syncthing/syncthing/lib/model 0.046s`.

Native PHP now adds `FolderScanService`, `FolderScanCheckpointStore`, and
`FolderScanCheckpointSnapshot`. The service wraps `FileInfoScanner` with a
folder-scoped checkpoint store, resumes cancelled scans with the checkpoint's
current files and resume subs, refreshes a bounded TTL on every successful
publish, expires stale snapshots before reuse, and rejects stale writes through
revision-based conflict checks. The WordPress example
`wordpress-folder-scan-service.php` shows a media scan cancelled after one
hashed upload, resumed by a later request, and exposed as a route payload with
revision, update time, expiry, attempts, completed paths, and merged progress
events.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderScanCheckpointConflictException.php`
  passed.
- `php -l lanes/syncthing/src/FolderScanCheckpointSnapshot.php` passed.
- `php -l lanes/syncthing/src/FolderScanCheckpointStore.php` passed.
- `php -l lanes/syncthing/src/FolderScanService.php` passed.
- `php -l lanes/syncthing/tests/FolderScanServiceTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-folder-scan-service.php` passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanServiceTest.php`
  passed 1 file, 36 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests/FileInfoScannerTest.php lanes/syncthing/tests/FolderScanEventCollectorTest.php lanes/syncthing/tests/FolderScanCheckpointTest.php lanes/syncthing/tests/FolderScanServiceTest.php`
  passed 4 files, 250 assertions, and 0 failures.
- `go test ./lib/model -run '^(TestIssue3804|TestIssue3829|TestFolderAPIErrors)$' -count=1`
  passed with `ok github.com/syncthing/syncthing/lib/model 0.046s`.
- `php tools/run-tests.php lanes/syncthing/tests` passed 42 files, 2252
  assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-folder-scan-service.php` ran
  successfully and reported a cancelled revision 1 status plus a completed
  revision 2 status with refreshed expiry.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned active root harness PID `2836374`; owner evidence from
  `ps -o pid,user,ppid,etime,stat,args -p 2836374` showed
  `2836374 claude 2836373 00:07 R php tools/run-tests.php`. No duplicate root
  run was started; root result remains pending for the supervisor/integrator.

## 2026-05-23 WordPress Option Checkpoint Store

Targeted upstream evidence reused the model scan API boundary for synchronous
folder scan publication: `ScanFolders`, `ScanFolder`, `ScanFolderSubdirs`,
folder `Scan`, `doInSync`, `scanSubdirs`, scan batch flush/update boundaries,
`CurrentFiler`, and `clearScanErrors`, plus upstream tests `TestIssue3804`,
`TestIssue3829`, and `TestFolderAPIErrors`. Refreshed focused upstream evidence
passed in `.upstream-cache/port-go-local-capacity-20260523T0034Z/syncthing`:
`go test ./lib/model -run '^(TestIssue3804|TestIssue3829|TestFolderAPIErrors)$' -count=1`
with `ok github.com/syncthing/syncthing/lib/model 0.032s`.

Native PHP now adds `FolderScanCheckpointRepository` and
`WordPressOptionCheckpointStore`. `FolderScanService` accepts the repository
interface, so the same scan publish path can use the existing in-memory store or
a WordPress option-style store. The option store persists schema-versioned
checkpoint snapshots, preserves full `FileInfo` resume metadata, deletes expired
options before reuse, hashes unsafe or long folder IDs into stable option names,
and can use an optional compare-and-swap callback to turn concurrent writes into
`FolderScanCheckpointConflictException`. The WordPress example
`wordpress-folder-scan-option-store.php` shows a media scan cancelled after one
hashed upload, reloaded by a later request through option storage, and resumed as
a revision-2 REST-style status payload.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderScanCheckpointRepository.php` passed.
- `php -l lanes/syncthing/src/FolderScanCheckpointStore.php` passed.
- `php -l lanes/syncthing/src/FolderScanService.php` passed.
- `php -l lanes/syncthing/src/WordPressOptionCheckpointStore.php` passed.
- `php -l lanes/syncthing/tests/WordPressOptionCheckpointStoreTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-folder-scan-option-store.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/WordPressOptionCheckpointStoreTest.php`
  passed 1 file, 23 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanServiceTest.php lanes/syncthing/tests/WordPressOptionCheckpointStoreTest.php`
  passed 2 files, 59 assertions, and 0 failures.
- `go test ./lib/model -run '^(TestIssue3804|TestIssue3829|TestFolderAPIErrors)$' -count=1`
  passed with `ok github.com/syncthing/syncthing/lib/model 0.032s`.
- `php tools/run-tests.php lanes/syncthing/tests` passed 43 files, 2275
  assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-folder-scan-option-store.php` ran
  successfully and reported option storage with stored revision 2.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned no active exact root harness, so this worker ran
  `php tools/run-tests.php`; it passed 207 files, 23945 assertions, and 0
  failures.

## 2026-05-23 SQLite Checkpoint Store

Targeted upstream evidence reused the synchronous model scan publication
boundary and refreshed it in the hydrated local-capacity worktree:
`go test ./lib/model -run '^(TestIssue3804|TestIssue3829|TestFolderAPIErrors)$' -count=1`
passed with `ok github.com/syncthing/syncthing/lib/model 0.033s`. A bounded
SQLite DB runner also passed:
`go test ./internal/db/sqlite -run '^(TestBasics|TestNeed)$' -count=1` with
`ok github.com/syncthing/syncthing/internal/db/sqlite 0.042s`. This is focused
package evidence for the scan API and durable database boundary, not full
`go test ./...` parity.

Native PHP now adds `FolderScanCheckpointPayloadCodec` and
`SqliteCheckpointStore`. The SQLite store persists checkpoint snapshots as
schema-versioned JSON, keeps revision/update/expiry as row columns, deletes
expired rows before reuse, rejects stale revisions with
`FolderScanCheckpointConflictException`, lists unexpired folder snapshots in a
stable order, and reopens the same checkpoint database across PHP requests.
`wordpress-folder-scan-sqlite-store.php` shows a local-first WordPress media
scan cancelled after one hashed upload, then resumed by a later request through
a file-backed SQLite checkpoint database.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderScanCheckpointPayloadCodec.php` passed.
- `php -l lanes/syncthing/src/SqliteCheckpointStore.php` passed.
- `php -l lanes/syncthing/tests/SqliteCheckpointStoreTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-folder-scan-sqlite-store.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/SqliteCheckpointStoreTest.php`
  passed 1 file, 24 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanServiceTest.php lanes/syncthing/tests/WordPressOptionCheckpointStoreTest.php lanes/syncthing/tests/SqliteCheckpointStoreTest.php`
  passed 3 files, 83 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-folder-scan-sqlite-store.php` ran
  successfully and reported stored revision 2 from the SQLite row.
- `go test ./lib/model -run '^(TestIssue3804|TestIssue3829|TestFolderAPIErrors)$' -count=1`
  passed with `ok github.com/syncthing/syncthing/lib/model 0.033s`.
- `go test ./internal/db/sqlite -run '^(TestBasics|TestNeed)$' -count=1`
  passed with `ok github.com/syncthing/syncthing/internal/db/sqlite 0.042s`.
- `php tools/run-tests.php lanes/syncthing/tests` passed 44 files, 2299
  assertions, and 0 failures.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned active root harness PIDs `2992314` and `2992617`. Owner evidence from
  `ps -o pid,user,ppid,etime,stat,args -p 2992314,2992617` showed
  `2992314 claude 2984797 00:24 Rs php tools/run-tests.php` and
  `2992617 claude 2992616 00:11 S php tools/run-tests.php`. No duplicate root
  run was started; root result remains pending for the supervisor/integrator.

## 2026-05-23 Multi-Folder Scan Scheduler

Targeted upstream evidence covered `model.ScanFolders`, `ScanFolder`,
`ScanFolderSubdirs`, folder `Scan`, and `doInSync` from `lib/model/model.go`
and `lib/model/folder.go`: Syncthing captures the configured folder IDs, scans
each folder through the same synchronous runner boundary, records only folders
that return errors in the aggregate map, and rejects paused or missing folders
before invoking the folder runner. Refreshed focused upstream runner evidence:
`go test ./lib/model -run '^(TestIssue3804|TestIssue3829|TestFolderAPIErrors)$' -count=1`
passed with `ok github.com/syncthing/syncthing/lib/model 0.039s`. Full
`go test ./...` remains unexecuted for the recorded blob-filter/cache/budget
reasons.

Native PHP now adds `FolderScanScheduler` and `FolderScanSchedulerResult`.
The scheduler registers multiple `FolderScanService` instances, scans every
registered folder while collecting per-folder errors, preserves paused/missing
folder boundaries, forwards slash-normalized and empty subdir scans, and lets
each folder service keep its own checkpoint revision, expiry, and stale-publish
conflict behavior. `wordpress-multi-folder-scan-scheduler.php` shows a REST
payload for local-first WordPress media and content folders where the first scan
publishes both folders and a later scan reports a paused content folder while
still refreshing the media checkpoint.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderScanScheduler.php` passed.
- `php -l lanes/syncthing/src/FolderScanSchedulerResult.php` passed.
- `php -l lanes/syncthing/tests/FolderScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-multi-folder-scan-scheduler.php`
  passed.
- `go test ./lib/model -run '^(TestIssue3804|TestIssue3829|TestFolderAPIErrors)$' -count=1`
  passed with `ok github.com/syncthing/syncthing/lib/model 0.039s`.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanSchedulerTest.php`
  passed 1 file, 35 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanSchedulerTest.php lanes/syncthing/tests/FolderScanServiceTest.php lanes/syncthing/tests/WordPressOptionCheckpointStoreTest.php lanes/syncthing/tests/SqliteCheckpointStoreTest.php`
  passed 4 files, 118 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-multi-folder-scan-scheduler.php` ran
  successfully and reported a two-folder REST payload with one paused-folder
  error on the second scan.
- `php tools/run-tests.php lanes/syncthing/tests` passed 45 files, 2334
  assertions, and 0 failures.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned active root harness PIDs `3056982` and `3057590`. Owner evidence from
  `ps -o pid,user,ppid,etime,stat,args -p 3056982,3057590` showed
  `3056982 claude 2996694 00:22 Rs php tools/run-tests.php` and
  `3057590 claude 2989965 00:20 Ss php tools/run-tests.php`. No duplicate root
  run was started; root result remains pending for the supervisor/integrator.
- A later pre-root check returned no active exact root harness, so this worker
  ran `php tools/run-tests.php`. The exact root run exited 1 with 213 files,
  23462 assertions, and 192 failures in the moving aggregate. A follow-up
  failure-header capture rerun exited 1 with 213 files, 24502 assertions, and
  5 failures, all in `lanes/rclone/tests/OneDriveListRTest.php`. The focused
  Rclone file then passed on its own with 1 file, 25 assertions, and 0 failures.
  A later exact root sample exited 1 with 213 files, 24565 assertions, and 1
  failure. The final root sample exited 0 with 213 files, 24572 assertions, and
  0 failures.

## 2026-05-23 Folder Scan API Coordinator

Targeted upstream evidence covered `lib/api/api.go` `postDBScan`, which maps a
non-empty `folder` query to `ScanFolderSubdirs(folder, subs)` and falls back to
`ScanFolders()` when no folder is supplied; `lib/rc/rc.go` `Rescan` and
`RescanSubs`, which send `folder` plus repeated `sub` query values; and
`lib/model/model.go` `ScanFolders`/`ScanFolderSubdirs`, which collect
per-folder errors and reject paused/missing folders before invoking the folder
runner. Refreshed focused upstream evidence passed in the hydrated local
worktree:
`go test ./lib/model -run '^(TestIssue3804|TestIssue3829|TestFolderAPIErrors|TestPausedFolders)$' -count=1`
with `ok github.com/syncthing/syncthing/lib/model 0.039s`. Full
`go test ./...` remains unexecuted for the recorded blob-filter/cache/budget
reasons.

Native PHP now adds `FolderScanApiCoordinator` and `FolderScanApiResponse`.
The coordinator accepts WordPress REST-style payloads for all-folder scans,
single-folder scans with `sub`/`subs`/`subdirs`, and selected folder maps,
normalizes subdir inputs using the scanner-compatible slash/dot/traversal
rules, rejects unknown folder IDs before scanning, preserves per-folder paused
errors as HTTP-style partial responses, and recursively redacts absolute local
paths from status payloads before returning them to a REST route.
`wordpress-folder-scan-api-coordinator.php` shows a media/content scan request
where a paused content folder returns a 207 partial response while the media
checkpoint still publishes.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderScanApiCoordinator.php` passed.
- `php -l lanes/syncthing/src/FolderScanApiResponse.php` passed.
- `php -l lanes/syncthing/tests/FolderScanApiCoordinatorTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-folder-scan-api-coordinator.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanApiCoordinatorTest.php`
  passed 1 file, 32 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanApiCoordinatorTest.php lanes/syncthing/tests/FolderScanSchedulerTest.php lanes/syncthing/tests/FolderScanServiceTest.php lanes/syncthing/tests/WordPressOptionCheckpointStoreTest.php lanes/syncthing/tests/SqliteCheckpointStoreTest.php lanes/syncthing/tests/FileInfoScannerTest.php lanes/syncthing/tests/FolderScanCheckpointTest.php lanes/syncthing/tests/FolderScanEventCollectorTest.php`
  passed 8 files, 364 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-folder-scan-api-coordinator.php` ran
  successfully and reported a 207 partial REST payload with one paused-folder
  error.
- `php tools/run-tests.php lanes/syncthing/tests` passed 46 files, 2366
  assertions, and 0 failures.
- `go test ./lib/model -run '^(TestIssue3804|TestIssue3829|TestFolderAPIErrors|TestPausedFolders)$' -count=1`
  passed with `ok github.com/syncthing/syncthing/lib/model 0.039s`.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned active root harness PID `3225311`. Owner evidence from
  `ps -o pid,user,ppid,etime,stat,args -p 3225311` showed
  `3225311 claude 3225310 00:28 R php tools/run-tests.php`. No duplicate root
  run was started; root result remains pending for the supervisor/integrator.

## 2026-05-23 Folder Scan API Request Queue

Targeted upstream evidence for this slice extends the scan API coordinator
mapping with `lib/model/folder.go`'s one-buffered scan scheduling boundaries:
`ScheduleScan` and `SchedulePull` enqueue one notification while the folder is
busy, and exported `Scan` runs through the synchronous `doInSync` folder runner
before scanning subdirectories. The queue also reuses the existing upstream
evidence for `postDBScan`, `Rescan`/`RescanSubs`, `ScanFolders`,
`ScanFolderSubdirs`, `TestIssue3804`, `TestIssue3829`,
`TestFolderAPIErrors`, and `TestPausedFolders`. Refreshed focused upstream
evidence passed in the hydrated local worktree:
`go test ./lib/model -run '^(TestIssue3804|TestIssue3829|TestFolderAPIErrors|TestPausedFolders)$' -count=1`
with `ok github.com/syncthing/syncthing/lib/model 0.041s`. Full
`go test ./...` remains unexecuted for the recorded blob-filter/cache/budget
reasons.

Native PHP now adds `FolderScanApiRequestQueue`. It accepts WordPress REST-style
scan payloads, uses `FolderScanApiCoordinator` request normalization for the
accepted request surface, coalesces matching pending or running folder/subdir
requests, bounds pending work, exposes pending/running/completed status, records
the completed coordinator response, and invokes the coordinator only after a
queued request is explicitly started. `wordpress-folder-scan-api-queue.php`
shows duplicate media/content scan requests coalescing into one queued worker
item before publishing both checkpoints.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderScanApiCoordinator.php` passed.
- `php -l lanes/syncthing/src/FolderScanApiRequestQueue.php` passed.
- `php -l lanes/syncthing/tests/FolderScanApiRequestQueueTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-folder-scan-api-queue.php` passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanApiRequestQueueTest.php`
  passed 1 file, 43 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanApiRequestQueueTest.php lanes/syncthing/tests/FolderScanApiCoordinatorTest.php lanes/syncthing/tests/FolderScanSchedulerTest.php lanes/syncthing/tests/FolderScanServiceTest.php lanes/syncthing/tests/FolderScanCheckpointTest.php lanes/syncthing/tests/WordPressOptionCheckpointStoreTest.php lanes/syncthing/tests/SqliteCheckpointStoreTest.php`
  passed 7 files, 226 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-folder-scan-api-queue.php` ran
  successfully and reported queued, coalesced, running, and completed REST-style
  statuses.
- `php tools/run-tests.php lanes/syncthing/tests` passed 47 files, 2409
  assertions, and 0 failures.
- `go test ./lib/model -run '^(TestIssue3804|TestIssue3829|TestFolderAPIErrors|TestPausedFolders)$' -count=1`
  passed with `ok github.com/syncthing/syncthing/lib/model 0.041s`.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned no active exact root harness. `php tools/run-tests.php` then briefly
  waited on the root lock instead of running concurrently and completed with
  217 files, 25059 assertions, and 0 failures. A later post-run exact sample
  observed another active root harness PID `3310538`; owner evidence from
  `ps -o pid,user,ppid,etime,stat,args -p 3310538` showed
  `3310538 claude 3226739 00:11 Rs php tools/run-tests.php`, so this lane did
  not start any further root run. A later exact sample observed root PID
  `3311713`; owner evidence showed
  `3311713 claude 3311710 00:23 R php tools/run-tests.php`.

## 2026-05-23 Delayed Scan Scheduling

Targeted upstream evidence for this slice covered `lib/api/api.go`
`postDBScan`, where a non-empty `folder` request scans selected subdirectories
first and then parses `next` as seconds before calling `model.DelayScan`;
`lib/model/model.go` `DelayScan`, which silently ignores missing folder
runners; and `lib/model/folder.go` `DelayScan`, the `scanDelay` select case,
and `scanTimer.Reset(next)`. A targeted search of upstream `lib/api` and
`lib/model` tests did not find a dedicated upstream test for the `next` branch,
so this slice is backed by static source reads plus adjacent focused runner
evidence. Refreshed focused upstream evidence passed in the hydrated local
worktree:
`go test ./lib/model -run '^(TestIssue3804|TestIssue3829|TestFolderAPIErrors|TestPausedFolders)$' -count=1`
with `ok github.com/syncthing/syncthing/lib/model 0.043s`. Full
`go test ./...` remains unexecuted for the recorded blob-filter/cache/budget
reasons.

Native PHP now maps that delayed scan timing boundary. `FolderScanScheduler`
can record a selected folder's next scan time, expose scheduled/due status,
clear scheduled scans when a folder is paused or removed, and publish the next
checkpoint only when `scanDueDelayedFolders()` reaches the due time.
`FolderScanApiCoordinator` accepts parseable `next`, `delay`, or `nextSeconds`
payloads after successful selected-folder scans and ignores malformed delay
values like upstream `strconv.Atoi` errors. `FolderScanApiRequestQueue` now
keeps requests with different next timings distinct while still coalescing
equivalent pending/running requests, and a later completed request resets the
scheduled time. `wordpress-delayed-scan-request.php` shows a WordPress REST
media-folder scan accepting `next`, reporting the scheduled status before it is
due, skipping early checkpoint publication, and publishing revision 2 when the
delayed scan becomes due.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderScanScheduler.php` passed.
- `php -l lanes/syncthing/src/FolderScanApiCoordinator.php` passed.
- `php -l lanes/syncthing/src/FolderScanApiRequestQueue.php` passed.
- `php -l lanes/syncthing/tests/FolderScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/tests/FolderScanApiCoordinatorTest.php` passed.
- `php -l lanes/syncthing/tests/FolderScanApiRequestQueueTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-delayed-scan-request.php` passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanSchedulerTest.php lanes/syncthing/tests/FolderScanApiCoordinatorTest.php lanes/syncthing/tests/FolderScanApiRequestQueueTest.php`
  passed 3 files, 157 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-delayed-scan-request.php` ran
  successfully and reported accepted, scheduled-before-due, before-due, and
  due-result REST payloads.
- `go test ./lib/model -run '^(TestIssue3804|TestIssue3829|TestFolderAPIErrors|TestPausedFolders)$' -count=1`
  passed with `ok github.com/syncthing/syncthing/lib/model 0.043s`.
- `php tools/run-tests.php lanes/syncthing/tests` passed 47 files, 2456
  assertions, and 0 failures.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned active root harness PID `3356750`. Owner evidence from
  `ps -o pid,user,ppid,etime,stat,args -p 3356750` showed
  `3356750 claude 3338007 01:01 Rs php tools/run-tests.php`. No duplicate root
  run was started; root result remains pending for the supervisor/integrator.

## 2026-05-23 Filesystem Watcher Scan Scheduling

Targeted upstream evidence for this slice covered `lib/model/folder.go`
`watchChan`, `startWatch`, `monitorWatch`, `scanOnWatchErr`, and the Serve
select case that invokes `scanSubdirs(ctx, fsEvents)` when the watcher
aggregator emits paths. The aggregation behavior is mapped from
`lib/watchaggregator/aggregator.go` and `aggregator_test.go`, with adjacent
filesystem watcher boundary reads from `lib/fs/basicfs_watch.go` and
`basicfs_watch_test.go`. Focused upstream runner evidence passed in the
hydrated local worktree:
`go test ./lib/watchaggregator -run '^(TestAggregate|TestInProgress|TestDelay|TestNoDelay)$' -count=1`
with `ok github.com/syncthing/syncthing/lib/watchaggregator 7.239s`.
Full `go test ./...` remains unexecuted for the recorded blob-filter/cache and
budget reasons.

Native PHP now maps the watcher delay boundary through
`FolderWatchEventAggregator` and `FolderWatchScanScheduler`.
`FolderWatchEventAggregator` records non-remove, remove, and mixed filesystem
events, ignores exact paths that Syncthing itself is currently modifying,
rolls crowded directories and whole-folder overflow into parent scans, delays
repeat modifications until they settle, and releases batches in upstream
non-remove, mixed, then remove order. `FolderWatchScanScheduler` keeps
folder-scoped watcher status, skips paused/missing folders without consuming
pending events, and dispatches due watcher batches through
`FolderScanScheduler::scanFolderSubdirs()` so WordPress media checkpoints
advance only when the watcher delay has expired. The example
`wordpress-fs-watch-scan-scheduler.php` shows two upload events coalescing into
one `wp-content/uploads/2026/05` subdir scan, no early checkpoint publication,
and a completed checkpoint when the watcher batch becomes due.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderWatchEventAggregator.php` passed.
- `php -l lanes/syncthing/src/FolderWatchScanScheduler.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchEventAggregatorTest.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php` passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchEventAggregatorTest.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 2 files, 35 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php` ran
  successfully and reported pending watcher status, before-due no-op scan
  status, dispatched batch metadata, and a completed checkpoint.
- `php tools/run-tests.php lanes/syncthing/tests` passed 49 files, 2491
  assertions, and 0 failures.
- The required pre-root `pgrep -af '^php tools/run-tests\.php( |$)'` check
  returned no active exact root harness. `php tools/run-tests.php` then passed
  223 files, 25545 assertions, and 0 failures.

## 2026-05-24 Reduced Folder Scan Route Registry

This isolated micro-slice adds a native WordPress activation boundary around the
already mapped scan API behavior. The upstream behavior cluster remains the
`/rest/db/scan` path mapped from `lib/api/api.go` `postDBScan`,
`lib/rc/rc.go` `Rescan`/`RescanSubs`, and `lib/model/model.go`
`ScanFolders`/`ScanFolderSubdirs`; the previous focused upstream model runner
passed with `ok github.com/syncthing/syncthing/lib/model 0.043s`. No new Go
runner was needed for the WordPress route registry itself because it is a
native PHP registration/dispatch boundary over the existing coordinator and
queue.

Native PHP now adds `FolderScanRouteRegistry`. It registers the reduced
WordPress REST-style scan routes, normalizes both direct lane paths and
`/wp-json/local-first/v1/...` paths, dispatches `POST /syncthing/db/scan` to
`FolderScanApiCoordinator`, optionally dispatches
`POST /syncthing/db/scan/queue` to `FolderScanApiRequestQueue`, exposes route
metadata for WordPress registration, and returns REST-shaped missing-route or
method-not-allowed errors. `wordpress-folder-scan-route-registry.php` shows a
queued WordPress media scan accepted through the registered route and completed
by the native scan queue.

Dependency closure: no new native PHP support component is needed beyond the
bounded `FolderScanRouteRegistry` added in this slice. It reuses the existing
scan coordinator, request queue, scheduler, and checkpoint components; the
activation gate is registering it from a WordPress REST callback, and the
evidence plan is the focused route tests plus the local WordPress example smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderScanRouteRegistry.php` passed.
- `php -l lanes/syncthing/tests/FolderScanRouteRegistryTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-folder-scan-route-registry.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanRouteRegistryTest.php`
  passed 1 file, 19 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanRouteRegistryTest.php lanes/syncthing/tests/FolderScanApiCoordinatorTest.php lanes/syncthing/tests/FolderScanApiRequestQueueTest.php`
  passed 3 files, 124 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-folder-scan-route-registry.php` ran
  successfully and reported two registered routes, one queued request, and one
  completed queue response.
- `php tools/run-tests.php lanes/syncthing/tests` passed 50 files, 2510
  assertions, and 0 failures.
- `git diff --check -- lanes/syncthing` passed.
- Root harness status: not run - isolated micro-slice.

## Next Task

## 2026-05-24 Watcher Restart Error Recovery

This isolated micro-slice maps the next filesystem watcher behavior cluster
from upstream `lib/model/folder.go`: `restartWatchChan`, `monitorWatch`,
`scanOnWatchErr`, and the folder Serve loop branch that falls back to a scan
after watcher errors. It reuses the existing watcher aggregation evidence from
`lib/watchaggregator` and the prior focused upstream runner:
`go test ./lib/watchaggregator -run '^(TestAggregate|TestInProgress|TestDelay|TestNoDelay)$' -count=1`
passed with `ok github.com/syncthing/syncthing/lib/watchaggregator 7.239s`.
No new Go runner was added for this PHP-only restart/status boundary; full
`go test ./...` remains unexecuted for the recorded blob-filter/cache and
budget reasons.

Native PHP now extends `FolderWatchScanScheduler` with
`recordWatcherError()` and `markWatcherRestarted()`. A watcher error records
REST-friendly restart status, applies bounded exponential backoff for delayed
watcher restarts, and, when `scanOnWatchError` is enabled, immediately runs a
full folder scan so a WordPress media library is not left stale after an
inotify/FSEvents overflow. The existing example
`wordpress-fs-watch-scan-scheduler.php` now shows delayed upload coalescing,
then a watcher overflow that triggers a full fallback scan and exposes the
pending restart payload.

Dependency closure: no new support component is needed. This slice reuses the
existing bounded PHP watcher scheduler, event aggregator, folder scan
scheduler, scan service, and checkpoint store. The activation gate is wiring
`recordWatcherError()` from a WordPress/local watcher adapter when a native
watch source reports overflow/closure; the evidence plan is the focused watcher
tests plus the local WordPress example smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderWatchScanScheduler.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 1 file, 41 assertions, and 0 failures.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchEventAggregatorTest.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 2 files, 58 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php` ran
  successfully and reported pending watcher status, before-due no-op scan
  status, delayed dispatched batch metadata, watch-error full scan fallback,
  watcher restart status, and checkpoint revision 2.
- `php tools/run-tests.php lanes/syncthing/tests` passed 50 files, 2533
  assertions, and 0 failures.
- `git diff --check -- lanes/syncthing` passed.
- Root harness status: not run - isolated micro-slice.

## Next Task

## 2026-05-24 Watcher Stop And Pause Cleanup

This isolated micro-slice maps the next filesystem watcher lifecycle boundary
from upstream `lib/model/folder.go`: `watchChan`, `startWatch`, `stopWatch`,
paused-folder transitions, and the Serve loop cleanup around watcher restart
state. It reuses the existing `lib/watchaggregator` evidence from the prior
watcher slice rather than adding a new Go runner; full `go test ./...` remains
unexecuted for the recorded blob-filter/cache and budget reasons.

Native PHP now adds `FolderWatchScanScheduler::stopWatchingFolder()`. A pause
or watcher stop clears delayed restart status and Syncthing-owned in-progress
markers without consuming queued external WordPress media watch events, so a
resume can still scan the pending upload. Shutdown/removal callers can pass
`discardPendingEvents: true` to remove the full watcher state.
`wordpress-fs-watch-scan-scheduler.php` now shows a watcher overflow fallback,
then pause cleanup where the restart payload is gone, the pending upload event
is still visible, and the paused scan remains a no-op.

Dependency closure: no new support component is needed. This slice reuses the
existing bounded PHP watcher scheduler, event aggregator, folder scan
scheduler, scan service, and checkpoint store. The activation gate is wiring
`stopWatchingFolder()` from a WordPress/local watcher adapter when a folder is
paused, stopped, removed, or unshared; the evidence plan is the focused watcher
tests plus the local WordPress example smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderWatchEventAggregator.php` passed.
- `php -l lanes/syncthing/src/FolderWatchScanScheduler.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 1 file, 53 assertions, and 0 failures.
- Root harness status: not run - isolated micro-slice.

## Next Task

Map the next Syncthing watcher lifecycle edge: folder removal/unshare cleanup
should discard pending watcher events and restart state while keeping
paused-folder resume semantics covered by the current stop cleanup slice.

## 2026-05-24 Watcher Removal And Unshare Cleanup

This isolated micro-slice maps the next filesystem watcher lifecycle boundary
from upstream `lib/model/folder.go`: watcher stop/restart state plus folder
removal/unshare cleanup when a folder leaves the model service map. It reuses
the existing bounded `lib/watchaggregator` evidence and prior watcher lifecycle
reads instead of adding a new Go runner; full `go test ./...` remains
unexecuted for the recorded blob-filter/cache and budget reasons.

Native PHP now adds `FolderWatchScanScheduler::removeWatchingFolder()` as the
explicit removal/unshare path. Unlike pause cleanup, removal discards queued
WordPress media watch events, pending watcher restart status, last dispatched
batch metadata, and Syncthing-owned in-progress markers after
`FolderScanScheduler::removeFolder()` removes the folder. `stopWatchingFolder()`
continues to preserve pending events for paused-folder resume.
`wordpress-fs-watch-scan-scheduler.php` now shows the pause-preserving state,
then removes the folder and reports that watcher state was removed and the
post-removal status is null.

Dependency closure: no new support component is needed. This slice reuses the
existing bounded PHP watcher scheduler, event aggregator, folder scan
scheduler, scan service, and checkpoint store. The activation gate is wiring
`removeWatchingFolder()` from a WordPress/local watcher adapter when a folder is
removed or unshared; the evidence plan is the focused watcher tests plus the
local WordPress example smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderWatchScanScheduler.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 1 file, 61 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php` ran
  successfully and reported `removedWatchState: true` plus `removedStatus:
  null`.
- Root harness status: not run - isolated micro-slice.

## Next Task

Map the next Syncthing watcher lifecycle edge: restart due handling should
expose when a watcher restart is ready and let a WordPress/local watcher
adapter mark the restart attempt complete without triggering a scan.

## 2026-05-25 Watcher Restart Due Completion

This isolated micro-slice maps the next filesystem watcher restart boundary
from upstream `lib/model/folder.go`: `watchChan`, `startWatch`, `stopWatch`,
`monitorWatch`, `restartWatchChan`, and `scanOnWatchErr`. It reuses the
existing bounded `lib/watchaggregator` evidence and prior watcher lifecycle
reads instead of adding a new Go runner; full `go test ./...` remains
unexecuted for the recorded blob-filter/cache and budget reasons.

Native PHP now adds `FolderWatchScanScheduler::dueWatcherRestarts()` and
`completeDueWatcherRestart()`. A WordPress/local watcher adapter can list only
restart attempts whose backoff has elapsed for active, unpaused folders and can
clear a due restart attempt without triggering a folder scan. Attempts that are
not yet due remain pending, so an adapter cannot accidentally drop restart
state before the Syncthing-style backoff window has elapsed.
`wordpress-fs-watch-scan-scheduler.php` now shows a not-due restart status,
then a due restart payload, then a completed restart with no extra scan.

Dependency closure: no new support component is needed. This slice reuses the
existing bounded PHP watcher scheduler, event aggregator, folder scan
scheduler, scan service, and checkpoint store. The activation gate is wiring
`dueWatcherRestarts()` and `completeDueWatcherRestart()` from a WordPress/local
watcher adapter after it recreates an inotify/FSEvents/watchman subscription;
the evidence plan is the focused watcher tests plus the local WordPress
example smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderWatchScanScheduler.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 1 file, 73 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php` ran
  successfully and reported `restartNotDue: []`, a due restart payload, and a
  completed restart payload without incrementing the checkpoint revision for
  that completion.
- Root harness status: not run - isolated micro-slice.

## Next Task

Map the next Syncthing watcher lifecycle edge: paused folders with pending due
watcher restarts should preserve restart state until resume, then expose
completion to the WordPress/local watcher adapter.

## 2026-05-25 Paused Watcher Restart Preservation

This isolated micro-slice maps the paused-folder side of the same upstream
filesystem watcher restart boundary from `lib/model/folder.go`: watcher restart
state survives while a folder is paused, due restart attempts are not exposed
to active watcher adapters until the folder resumes, and pending media events
are not scanned while the folder is paused.

Native PHP now adds `FolderWatchScanScheduler::pauseWatchingFolder()`. A
WordPress/local watcher adapter can clear Syncthing-owned in-progress markers
and stale dispatched batch metadata when a folder is paused while preserving
pending watch events and pending watcher restart state. After the folder
resumes, `dueWatcherRestarts()` exposes the already-due restart and
`completeDueWatcherRestart()` clears it without triggering a scan; the pending
media event can then be scanned normally.
`wordpress-fs-watch-scan-scheduler.php` now reports an empty due-restart list
while paused and the preserved due restart after resume.

Dependency closure: no new support component is needed. This slice reuses the
existing bounded PHP watcher scheduler, event aggregator, folder scan
scheduler, scan service, and checkpoint store. The activation gate is wiring
`pauseWatchingFolder()` from a WordPress/local watcher adapter when a folder is
paused; the evidence plan is the focused watcher tests plus the local
WordPress example smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderWatchScanScheduler.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 1 file, 89 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php` ran
  successfully and reported `pausedRestartDue: []`, a preserved
  `resumedRestartDue` payload, and `resumedRestartCompleted` without scanning
  while paused.
- Root harness status: not run - isolated micro-slice.

## Next Task

Map the next Syncthing watcher lifecycle edge: repeated watcher errors while a
folder is paused should keep the latest restart reason and backoff attempt
without accepting new filesystem events until resume.

## 2026-05-25 Repeated Paused Watcher Errors

This isolated micro-slice maps the repeated-error side of the upstream
filesystem watcher restart boundary from `lib/model/folder.go`: watcher errors
can arrive while a folder is paused, the latest restart reason and backoff
attempt remain visible in status, but paused folders still do not accept new
filesystem events, dispatch fallback scans, expose due restarts, or complete
restart attempts until the folder resumes.

Native PHP now lets `FolderWatchScanScheduler::recordWatcherError()` update
restart state for registered paused folders while preserving the stricter
event and scan gates. Unknown or removed folders remain ignored. The
WordPress watcher smoke now reports `pausedIgnoredEvent: null`, the latest
paused restart reason/backoff in `pausedStatusAfterCleanup`, empty
`pausedRestartDue`, and resumed due/completed restart status once the backoff
expires after resume.

Dependency closure: no new support component is needed. This slice reuses the
existing bounded PHP watcher scheduler, event aggregator, folder scan
scheduler, scan service, and checkpoint store. The activation gate is wiring
`recordWatcherError()` from a WordPress/local watcher adapter when a paused
folder's native watch source reports repeated closure/overflow errors; the
evidence plan is the focused watcher tests plus the local WordPress example
smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderWatchScanScheduler.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 1 file, 107 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php` ran
  successfully and reported paused event rejection, latest paused restart
  reason/backoff, empty paused due restarts, and resumed restart completion.
- Root harness status: not run - isolated micro-slice.

## Next Task

Map the next Syncthing watcher lifecycle edge: watcher restart completion
should leave already-queued filesystem events pending for normal delayed scan
dispatch rather than forcing or discarding them.

## 2026-05-25 Watcher Restart Completion Preserves Queued Events

This isolated micro-slice maps the queued-event side of the upstream
filesystem watcher restart boundary from `lib/model/folder.go`: a restarted
watch source can be marked healthy after its backoff has elapsed, but already
coalesced filesystem events remain governed by the normal `FSWatcherDelay`
window. Completing the restart clears only restart status; it does not force a
scan, drop the pending event batch, or mark the delayed batch as dispatched.

Native PHP already held the correct state boundary in
`FolderWatchScanScheduler::completeDueWatcherRestart()` by clearing only
`watchRestarts`; this batch adds focused regression coverage and WordPress
smoke output for the invariant. `wordpress-fs-watch-scan-scheduler.php` now
reports a completed queued restart, pending upload status with the original
`nextScanAt`, a before-due no-op scan, and a later due scan that consumes the
queued media event.

Dependency closure: no new support component is needed. This slice reuses the
existing bounded PHP watcher scheduler, event aggregator, folder scan
scheduler, scan service, and checkpoint store. The activation gate is wiring
`completeDueWatcherRestart()` from a WordPress/local watcher adapter after it
recreates a native watch subscription; the evidence plan is the focused watcher
tests plus the local WordPress example smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderWatchScanScheduler.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 1 file, 118 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php` ran
  successfully and reported queued restart completion, pending upload status
  after restart completion, a before-due no-op scan, and the later due scan.
- Root harness status: not run - isolated micro-slice.

## Next Task

Map the next Syncthing watcher lifecycle edge: legacy watcher restart
acknowledgement via `markWatcherRestarted()` should preserve queued events just
like due restart completion.

## 2026-05-25 Legacy Watcher Restart Acknowledgement Preserves Queued Events

This isolated micro-slice maps the legacy acknowledgement side of the upstream
filesystem watcher restart boundary from `lib/model/folder.go`: an adapter can
mark a restarted watch source healthy without routing through the due-restart
helper, but already coalesced filesystem events still remain governed by the
normal `FSWatcherDelay` window.

Native PHP already held the correct state boundary in
`FolderWatchScanScheduler::markWatcherRestarted()` by clearing only
`watchRestarts`; this batch adds focused regression coverage and WordPress
smoke output for that legacy path. `wordpress-fs-watch-scan-scheduler.php` now
reports `legacyRestartAcknowledged: true`, pending upload status after the
acknowledgement, a before-due no-op scan, and a later due scan that consumes the
queued media event.

Dependency closure: no new support component is needed. This slice reuses the
existing bounded PHP watcher scheduler, event aggregator, folder scan
scheduler, scan service, and checkpoint store. The activation gate is wiring
`markWatcherRestarted()` from a WordPress/local watcher adapter after it
recreates a native watch subscription through a legacy callback path; the
evidence plan is the focused watcher tests plus the local WordPress example
smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderWatchScanScheduler.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 1 file, 130 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php` ran
  successfully and reported legacy restart acknowledgement, pending upload
  status after acknowledgement, a before-due no-op scan, and the later due
  scan.
- Root harness status: not run - isolated micro-slice.

## Next Task

Map the next Syncthing watcher lifecycle edge: watcher restart acknowledgement
should be ignored after folder removal and must not recreate watcher state for
unshared folders.

## 2026-05-25 Removed-Folder Legacy Watcher Restart Acknowledgement

This isolated micro-slice maps the removed-folder side of the upstream
filesystem watcher restart boundary from `lib/model/folder.go`: a legacy
watcher restart acknowledgement can race with folder unshare/removal, but once
the folder is no longer registered it must not be accepted as a healthy restart
or recreate watcher state for that folder.

Native PHP now has `FolderWatchScanScheduler::markWatcherRestarted()` check the
folder registry before accepting an acknowledgement. Removed folders return
`false` and stale restart status is cleared without creating an aggregator or
dispatching delayed scans. The WordPress watcher smoke now reports
`removedRestartAcknowledged: false`, no restart status after the removed-folder
acknowledgement, and explicit watcher cleanup returning the folder status to
`null`.

Dependency closure: no new support component is needed. This slice reuses the
existing bounded PHP watcher scheduler, event aggregator, folder scan
scheduler, scan service, and checkpoint store. The activation gate is wiring
`markWatcherRestarted()` from a WordPress/local watcher adapter that may receive
a late native watcher callback after a folder is unshared; the evidence plan is
the focused watcher tests plus the local WordPress example smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderWatchScanScheduler.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 1 file, 139 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php` ran
  successfully and reported removed-folder legacy acknowledgement rejection
  without recreating restart state.
- Root harness status: not run - isolated micro-slice.

## Next Task

Map the next Syncthing watcher lifecycle edge: removed-folder watcher cleanup
should also discard stale queued filesystem events when explicit watcher
teardown races with delayed scan dispatch.

## 2026-05-25 Watcher Cleanup Status Payloads

This isolated micro-slice maps the adapter-facing status boundary for the
filesystem watcher cleanup lifecycle. WordPress watcher adapters need a bounded
payload that distinguishes a paused media folder, where queued upload events
and restart state are preserved for resume, from an unshared/removed folder,
where stale queued paths and restart state are discarded.

Native PHP now adds `FolderWatchScanScheduler::cleanupWatchingFolder()`, which
returns the folder existence/paused state, whether pending events were
preserved or discarded, restart/in-progress cleanup flags, pending path counts
before and after cleanup, and the remaining watcher status. Existing
`stopWatchingFolder()`, `pauseWatchingFolder()`, and `removeWatchingFolder()`
remain boolean-compatible wrappers. The WordPress watcher smoke now reports
`pausedCleanup` with `preservedPendingEvents: true` and one pending upload path,
and `removedCleanup` with `discardedPendingEvents: true`, zero pending paths
after cleanup, and `statusAfter: null`.

Dependency closure: no new support component is needed. This slice reuses the
existing bounded PHP watcher scheduler, event aggregator, folder scan
scheduler, scan service, and checkpoint store. The activation gate is wiring
the cleanup payload into a WordPress/local watcher adapter or REST status
surface that must explain why queued media events were either retained for a
paused folder or dropped after folder removal; the evidence plan is the focused
watcher tests plus the local WordPress example smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderWatchScanScheduler.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 1 file, 189 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php` ran
  successfully and reported `pausedCleanup` preserving one queued media path
  plus `removedCleanup` discarding one queued media path.
- Root harness status: not run - isolated micro-slice.

## Next Task

Map the next Syncthing watcher lifecycle edge: expose recent cleanup payloads
through a bounded watcher status collection if WordPress REST clients need to
inspect cleanup outcomes after the live watcher state has been cleared.

## 2026-05-25 Watcher Recent Cleanup Statuses

This isolated micro-slice maps the REST-facing follow-up to watcher cleanup:
after a WordPress media folder is paused, removed, or discovered removed during
delayed watch dispatch, clients still need one bounded status payload explaining
what happened even when `watchStatus()` is already `null`.

Native PHP now adds `FolderWatchScanScheduler::recentCleanupStatuses()`, keeping
the latest cleanup payload per folder. Explicit cleanup records `cleanupAt`,
preserved/discarded pending event counts and paths, restart/in-progress cleanup
flags, and the remaining status when one exists. Delayed dispatch cleanup for a
removed folder now goes through the same path, so stale queued upload events are
not silently dropped without an inspectable reason. Recording a new live watch
event for a folder clears its previous recent cleanup entry.

The WordPress watcher smoke now reports `recentCleanupAfterPause`,
`recentCleanupAfterRemoval`, and `recentCleanupAfterRemovedDispatch`, including
a removed media folder whose live status is `null` while the recent cleanup
payload still shows the discarded queued path.

Dependency closure: no new support component is needed. This slice reuses the
existing bounded PHP watcher scheduler, event aggregator, folder scan scheduler,
scan service, and checkpoint store. The activation gate is wiring
`recentCleanupStatuses()` into a WordPress/local REST status surface that polls
watcher cleanup outcomes after pause/unshare events; the evidence plan is the
focused watcher tests plus the local WordPress example smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderWatchScanScheduler.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 1 file, 210 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php` ran
  successfully and reported recent cleanup payloads after pause, explicit
  removal cleanup, and removed-folder delayed dispatch cleanup.
- Root harness status: not run - isolated micro-slice.

## Next Task

Map the next Syncthing watcher lifecycle edge: bound retention/expiry for recent
watcher cleanup payloads if multiple removed WordPress media folders accumulate
between REST polls.

## 2026-05-25 Watcher Recent Cleanup Retention

This isolated micro-slice bounds the REST-facing recent cleanup payloads added
in the previous watcher slice. `FolderWatchScanScheduler` now accepts
`recentCleanupTtlSeconds` and `recentCleanupMaxEntries`, prunes stale cleanup
payloads when callers poll with an explicit clock, and keeps only the newest
cleanup rows when multiple removed WordPress media folders accumulate between
REST polls. A zero-second TTL is supported as an immediate-clear policy.

The WordPress watcher smoke uses a short retention window and reports
`recentCleanupRetained` immediately after removed-folder delayed dispatch
cleanup, then `recentCleanupExpired` after the polling window passes.

Dependency closure: no new support component is needed. This slice reuses the
existing bounded PHP watcher scheduler, event aggregator, folder scan
scheduler, scan service, and checkpoint store. The activation gate remains
wiring bounded cleanup payloads into a WordPress/local REST status surface; the
evidence plan is the focused watcher tests plus the local WordPress example
smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderWatchScanScheduler.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 1 file, 216 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php` ran
  successfully and reported retained then expired recent cleanup payloads.
- Root harness status: not run - isolated micro-slice.

## Next Task

Map the next Syncthing watcher lifecycle edge: expose an explicit
recent-cleanup acknowledgement/clear path for REST clients after they consume
bounded cleanup payloads.

## 2026-05-25 Watcher Recent Cleanup Acknowledgement

This isolated micro-slice closes the REST consumption loop for bounded watcher
cleanup payloads. `FolderWatchScanScheduler` now exposes
`acknowledgeRecentCleanup()` for one folder and `acknowledgeRecentCleanups()`
for all retained folders, both pruning expired payloads before clearing
acknowledged rows.

The WordPress watcher smoke reports a removed media folder cleanup payload,
acknowledges it, and then shows an empty follow-up cleanup status collection so
a polling REST client does not keep re-displaying the same consumed cleanup
event.

Dependency closure: no new support component is needed. This slice reuses the
existing bounded PHP watcher scheduler, event aggregator, folder scan
scheduler, scan service, and checkpoint store. The activation gate remains
wiring watcher cleanup status and acknowledgement into a WordPress/local REST
status surface; the evidence plan is the focused watcher tests plus the local
WordPress example smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderWatchScanScheduler.php` passed.
- `php -l lanes/syncthing/tests/FolderWatchScanSchedulerTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderWatchScanSchedulerTest.php`
  passed 1 file, 225 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-fs-watch-scan-scheduler.php` ran
  successfully and reported `recentCleanupAcknowledged: true` followed by
  `recentCleanupAfterAcknowledgement: []`.
- Root harness status: not run - isolated micro-slice.

## Next Task

Map the next Syncthing watcher lifecycle edge: expose recent cleanup
acknowledgement through the bounded WordPress route/status registry so REST
clients can poll and consume watcher cleanup outcomes in one payload.

## 2026-05-25 Watcher Cleanup Route Registry

This isolated micro-slice wires the bounded watcher cleanup status and
acknowledgement behavior into the WordPress/local route registry.
`FolderScanRouteRegistry::forScanApi()` now accepts an optional
`FolderWatchScanScheduler` and registers `GET /syncthing/db/watch/cleanups`
plus `POST /syncthing/db/watch/cleanups/ack`. The status route returns retained
cleanup payloads after optional retention pruning; the acknowledgement route
clears either one folder's consumed payload or all currently retained payloads.

The WordPress route-registry smoke now registers scan, queue, cleanup status,
and cleanup acknowledgement routes in one REST-style registry. It removes a
queued media folder before delayed watch dispatch, polls the cleanup route, and
acknowledges the removed-folder payload through the registry path.

Dependency closure: no new support component is needed. This slice reuses the
existing bounded PHP route registry, watcher scheduler, event aggregator,
folder scan scheduler, scan service, and checkpoint store. The activation gate
for this support is the already accepted watcher cleanup retention and
acknowledgement behavior; the evidence plan is the focused route-registry tests
plus the local WordPress route-registry smoke.

Verification for this batch:

- `php -l lanes/syncthing/src/FolderScanRouteRegistry.php` passed.
- `php -l lanes/syncthing/tests/FolderScanRouteRegistryTest.php` passed.
- `php -l lanes/syncthing/examples/wordpress-folder-scan-route-registry.php`
  passed.
- `php tools/run-tests.php lanes/syncthing/tests/FolderScanRouteRegistryTest.php`
  passed 1 file, 35 assertions, and 0 failures.
- `php lanes/syncthing/examples/wordpress-folder-scan-route-registry.php` ran
  successfully and reported cleanup status plus acknowledgement through the
  registered WordPress routes.
- Root harness status: not run - isolated micro-slice.

## Next Task

Map the next Syncthing route/status edge: add a bounded watcher route for due
restart status/completion so WordPress REST clients can report filesystem
watcher recovery without reaching into the scheduler directly.

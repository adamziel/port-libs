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

## Next Task

Target pullScannerRoutine scan aggregation and deferred post-pull scan
scheduling for files and directories queued during finalization/deletion.

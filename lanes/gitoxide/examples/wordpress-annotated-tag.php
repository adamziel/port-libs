<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitTag;

$fixture = require __DIR__ . '/../fixtures/wordpress-annotated-tag.php';
$tag = GitTag::parse($fixture['tagBody']);
$tagger = $tag->taggerSignature();
$storage = $tag->storageBytes();
$object = $tag->object();
$sanitizedDraftReleaseName = GitTag::sanitizeName($fixture['draftReleaseName']);
$draftReleaseTag = new GitTag($tag->rawTarget, $tag->targetKind, $sanitizedDraftReleaseName, $tag->tagger, $tag->message, $tag->pgpSignature);
$draftReleaseStorage = $draftReleaseTag->storageBytes();
$ownedReleaseTag = $tag->toOwned();
$ownedReleaseStorage = $ownedReleaseTag->storageBytes();

return [
    'name' => $tag->name,
    'draftReleaseName' => $fixture['draftReleaseName'],
    'draftReleaseNameValid' => GitTag::isValidName($fixture['draftReleaseName']),
    'sanitizedDraftReleaseName' => $sanitizedDraftReleaseName,
    'sanitizedDraftReleaseNameValid' => GitTag::isValidName($sanitizedDraftReleaseName),
    'sanitizedDraftReleaseStorageHasName' => str_contains($draftReleaseStorage, "tag {$sanitizedDraftReleaseName}\n"),
    'sanitizedDraftReleaseTarget' => $draftReleaseTag->target,
    'sanitizedDraftReleaseRawTarget' => $draftReleaseTag->rawTarget,
    'sanitizedDraftReleaseStorageHasNormalizedTarget' => str_contains($draftReleaseStorage, "object {$draftReleaseTag->target}\n"),
    'sanitizedDraftReleaseStorageHasRawParsedTarget' => str_contains($draftReleaseStorage, "object {$tag->rawTarget}\n"),
    'ownedReleaseTarget' => $ownedReleaseTag->target,
    'ownedReleaseRawTarget' => $ownedReleaseTag->rawTarget,
    'ownedReleaseStorageHasNormalizedTarget' => str_contains($ownedReleaseStorage, "object {$ownedReleaseTag->target}\n"),
    'ownedReleaseStorageHasRawParsedTarget' => str_contains($ownedReleaseStorage, "object {$tag->rawTarget}\n"),
    'target' => $tag->target,
    'rawTarget' => $tag->rawTarget,
    'targetKind' => $tag->targetKind,
    'tagger' => $tagger === null ? null : [
        'name' => $tagger->name,
        'email' => $tagger->email,
        'seconds' => $tagger->seconds(),
        'offsetSeconds' => $tagger->offsetSeconds(),
    ],
    'message' => $tag->message,
    'pgpSignature' => $tag->pgpSignature,
    'tokens' => $tag->tokens(),
    'tokenResults' => GitTag::iterateTokens($fixture['tagBody']),
    'size' => $tag->size(),
    'storageSha1' => sha1($storage),
    'objectSha1' => $object->oid(),
    'roundTripMatches' => GitTag::parse($storage)->storageBytes() === $storage,
];

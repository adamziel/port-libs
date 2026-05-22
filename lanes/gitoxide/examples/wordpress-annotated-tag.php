<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitTag;

$fixture = require __DIR__ . '/../fixtures/wordpress-annotated-tag.php';
$tag = GitTag::parse($fixture['tagBody']);
$tagger = $tag->taggerSignature();
$storage = $tag->storageBytes();
$object = $tag->object();

return [
    'name' => $tag->name,
    'target' => $tag->target,
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
    'size' => $tag->size(),
    'storageSha1' => sha1($storage),
    'objectSha1' => $object->oid(),
    'roundTripMatches' => GitTag::parse($storage)->storageBytes() === $storage,
];

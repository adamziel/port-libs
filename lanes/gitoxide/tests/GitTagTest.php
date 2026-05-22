<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\GitTag;

return [
    'parses sha256 annotated tags with signature split at line boundary' => static function (TestRunner $t): void {
        $target = 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789';
        $body = "object ABCDEF0123456789abcdef0123456789abcdef0123456789abcdef0123456789\n"
            . "type commit\n"
            . "tag v2.0.0-sha256\n"
            . "tagger Release Bot <release@example.com> 1710007200 +0530\n"
            . "\n"
            . "Release v2.0.0\n\n"
            . "- ship sha256 object support\n"
            . "- include annotated tag signatures\n"
            . "-----BEGIN PGP SIGNATURE-----\n"
            . "sha256-tag-signature\n"
            . "-----END PGP SIGNATURE-----\n";

        $tag = GitTag::parse($body, 'sha256');
        $tagger = $tag->taggerSignature();

        $t->same($target, $tag->target);
        $t->same('commit', $tag->targetKind);
        $t->same('v2.0.0-sha256', $tag->name);
        $t->same('Release Bot', $tagger?->name);
        $t->same(19800, $tagger?->offsetSeconds());
        $t->same("Release v2.0.0\n\n- ship sha256 object support\n- include annotated tag signatures", $tag->message);
        $t->same("-----BEGIN PGP SIGNATURE-----\nsha256-tag-signature\n-----END PGP SIGNATURE-----\n", $tag->pgpSignature);

        $object = GitObject::fromStorageBytes('tag ' . strlen($body) . "\0" . $body);
        $t->same('v2.0.0-sha256', GitTag::parse($object->body, 'sha256')->name);
    },
    'tag signature marker follows gitoxide line boundary semantics' => static function (TestRunner $t): void {
        $prefix = "object ffa700b4aca13b80cb6b98a078e7c96804f8e0ec\n"
            . "type commit\n";

        $notAtLineStart = GitTag::parse($prefix
            . "tag pgp-marker-in-message\n\n"
            . "message text\n"
            . "not-a-signature -----BEGIN PGP SIGNATURE-----\n"
            . "body\n"
            . "-----END PGP SIGNATURE-----");
        $t->same("message text\nnot-a-signature -----BEGIN PGP SIGNATURE-----\nbody\n-----END PGP SIGNATURE-----", $notAtLineStart->message);
        $t->same(null, $notAtLineStart->pgpSignature);

        $withTrailingText = GitTag::parse($prefix
            . "tag pgp-signature-with-trailing-text\n\n"
            . "message text\n"
            . "-----BEGIN PGP SIGNATURE-----\n"
            . "body\n"
            . "-----END PGP SIGNATURE-----\n"
            . "trailing text");
        $t->same('message text', $withTrailingText->message);
        $t->same("-----BEGIN PGP SIGNATURE-----\nbody\n-----END PGP SIGNATURE-----\ntrailing text", $withTrailingText->pgpSignature);

        $withoutEndMarker = GitTag::parse($prefix
            . "tag pgp-signature-without-end-marker\n\n"
            . "message text\n"
            . "-----BEGIN PGP SIGNATURE-----\n"
            . "body");
        $t->same('message text', $withoutEndMarker->message);
        $t->same("-----BEGIN PGP SIGNATURE-----\nbody", $withoutEndMarker->pgpSignature);

        $atBodyStart = GitTag::parse($prefix
            . "tag pgp-signature-at-body-start\n\n"
            . "-----BEGIN PGP SIGNATURE-----\n"
            . "body");
        $t->same('', $atBodyStart->message);
        $t->same("-----BEGIN PGP SIGNATURE-----\nbody", $atBodyStart->pgpSignature);
    },
    'parses optional taggers empty messages and tree tags' => static function (TestRunner $t): void {
        $treeTag = GitTag::parse("object c39ae07f393806ccf406ef966e9a15afc43cc36a\n"
            . "type tree\n"
            . "tag v2.6.11-tree\n"
            . "\n"
            . "This is the 2.6.11 tree object.\n"
            . "-----BEGIN PGP SIGNATURE-----\n"
            . "tree-signature\n");
        $t->same('tree', $treeTag->targetKind);
        $t->same(null, $treeTag->tagger);
        $t->same('This is the 2.6.11 tree object.', $treeTag->message);
        $t->same("-----BEGIN PGP SIGNATURE-----\ntree-signature\n", $treeTag->pgpSignature);

        $empty = GitTag::parse("object 01dd4e2a978a9f5bd773dae6da7aa4a5ac1cdbbc\n"
            . "type commit\n"
            . "tag empty\n"
            . "tagger Sebastian Thiel <sebastian.thiel@icloud.com> 1592381636 +0800\n"
            . "\n");
        $t->same("\n", $empty->message);

        $withoutTimestamp = GitTag::parse("object 4fcd840c4935e4c7a5ea3552710a0f26b9178c24\n"
            . "type commit\n"
            . "tag ChangeLog\n"
            . "tagger shemminger <shemminger>\n");
        $t->same('', $withoutTimestamp->message);
        $t->same('', $withoutTimestamp->taggerSignature()?->time);
    },
    'tag parser rejects malformed annotated tags' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => GitTag::parse("object 00000066666666666684666666666666666299297\n"
            . "type commit\n"
            . "tag bad\n"));
        $t->throws(InvalidArgumentException::class, static fn () => GitTag::parse("object ffa700b4aca13b80cb6b98a078e7c96804f8e0ec\n"
            . "type invalid\n"
            . "tag bad\n"));
        $t->throws(InvalidArgumentException::class, static fn () => GitTag::parse("object ffa700b4aca13b80cb6b98a078e7c96804f8e0ec\n"
            . "type commit\n"
            . "tag \n"));
        $t->throws(InvalidArgumentException::class, static fn () => GitTag::parse("object ffa700b4aca13b80cb6b98a078e7c96804f8e0ec\n"
            . "type commit\n"
            . "tag partial\n"
            . "message without separator"));
    },
];

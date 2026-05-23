<?php

declare(strict_types=1);

use PortLibs\Gitoxide\BlobMerge;
use PortLibs\Gitoxide\BlobMergeResult;

return [
    'text merge resolves identical side changes without markers' => static function (TestRunner $t): void {
        $result = BlobMerge::mergeText("1\n2\n3\n", "1\n3\nother\n", "1\n3\nother\n");

        $t->true($result->isClean());
        $t->same(BlobMergeResult::RESOLUTION_COMPLETE, $result->resolution);
        $t->same("1\n3\nother\n", $result->content);
    },
    'text merge selects changed side when the other side is unchanged' => static function (TestRunner $t): void {
        $result = BlobMerge::mergeText("title: Old\n", "title: New\n", "title: Old\n");

        $t->true($result->isClean());
        $t->same("title: New\n", $result->content);
    },
    'text merge combines independent WordPress file edits' => static function (TestRunner $t): void {
        $base = "title: Demo\nslug: demo\nstatus: draft\n";
        $ours = "title: Demo Import\nslug: demo\nstatus: draft\n";
        $theirs = "title: Demo\nslug: demo\nstatus: publish\n";

        $result = BlobMerge::mergeText($base, $ours, $theirs);

        $t->true($result->isClean());
        $t->same("title: Demo Import\nslug: demo\nstatus: publish\n", $result->content);
    },
    'text merge emits merge style conflict markers for overlapping changes' => static function (TestRunner $t): void {
        $result = BlobMerge::mergeText(
            "theme: base\n",
            "theme: ours\n",
            "theme: theirs\n",
            BlobMerge::STYLE_MERGE,
            'base',
            'current',
            'incoming',
        );

        $t->same(BlobMergeResult::RESOLUTION_CONFLICT, $result->resolution);
        $t->same(1, $result->conflictCount);
        $t->same("<<<<<<< current\ntheme: ours\n=======\ntheme: theirs\n>>>>>>> incoming\n", $result->content);
    },
    'text merge conflicts when an append touches a modified final line' => static function (TestRunner $t): void {
        $result = BlobMerge::mergeText(
            "1\n2\n3\n4\n5\n",
            "1\n2\n3\n4\n5\n6\n",
            "1\n2\n3\n4\n5 six\n",
            BlobMerge::STYLE_MERGE,
            'base',
            'ours',
            'theirs',
        );

        $t->same(BlobMergeResult::RESOLUTION_CONFLICT, $result->resolution);
        $t->contains('<<<<<<< ours', $result->content);
        $t->contains("5\n6\n", $result->content);
        $t->contains("5 six\n", $result->content);
    },
    'text merge emits diff3 base section when requested' => static function (TestRunner $t): void {
        $result = BlobMerge::mergeText(
            "theme: base\n",
            "theme: ours\n",
            "theme: theirs\n",
            BlobMerge::STYLE_DIFF3,
            'ancestor',
            'ours',
            'theirs',
        );

        $t->same("<<<<<<< ours\ntheme: ours\n||||||| ancestor\ntheme: base\n=======\ntheme: theirs\n>>>>>>> theirs\n", $result->content);
    },
    'text merge supports union driver resolution' => static function (TestRunner $t): void {
        $result = BlobMerge::mergeText(
            "original\n1\n2\n3\n4\n5\n",
            "A\n1\n2\n3\n4\n5\n6\n",
            "B\n1\n2\n3\n4\n5\n7\n",
            BlobMerge::STYLE_UNION,
        );

        $t->true($result->isClean());
        $t->same(BlobMergeResult::RESOLUTION_AUTO_RESOLVED, $result->resolution);
        $t->same("A\nB\n1\n2\n3\n4\n5\n6\n7\n", $result->content);
    },
    'text merge auto resolves conflicts with ours theirs and newline-separated union' => static function (TestRunner $t): void {
        $ours = BlobMerge::mergeText("1\n2\n3", "1\n3\nours", "1\n3\ntheirs", BlobMerge::STYLE_OURS);
        $theirs = BlobMerge::mergeText("1\n2\n3", "1\n3\nours", "1\n3\ntheirs", BlobMerge::STYLE_THEIRS);
        $union = BlobMerge::mergeText("b", "ours", "theirs", BlobMerge::STYLE_UNION);

        $t->same(BlobMergeResult::RESOLUTION_AUTO_RESOLVED, $ours->resolution);
        $t->same(0, $ours->conflictCount);
        $t->same("1\n3\nours", $ours->content);
        $t->same(BlobMergeResult::RESOLUTION_AUTO_RESOLVED, $theirs->resolution);
        $t->same("1\n3\ntheirs", $theirs->content);
        $t->same(BlobMergeResult::RESOLUTION_AUTO_RESOLVED, $union->resolution);
        $t->same("ours\ntheirs", $union->content);
    },
    'binary merge defaults to ours as an unresolved conflict' => static function (TestRunner $t): void {
        $result = BlobMerge::mergeBinary("base\0", "ours\0", "theirs\0");

        $t->same(BlobMergeResult::RESOLUTION_CONFLICT, $result->resolution);
        $t->same(1, $result->conflictCount);
        $t->same("ours\0", $result->content);
    },
    'binary merge can be auto resolved with an explicit side pick' => static function (TestRunner $t): void {
        $result = BlobMerge::mergeBinary("base\0", "ours\0", "theirs\0", BlobMerge::PICK_THEIRS);

        $t->true($result->isClean());
        $t->same(BlobMergeResult::RESOLUTION_AUTO_RESOLVED, $result->resolution);
        $t->same("theirs\0", $result->content);
    },
    'wordpress blob merge fixture resolves metadata but conflicts on theme json' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-blob-merge.php';

        $metadata = BlobMerge::mergeText($fixture['metadata']['base'], $fixture['metadata']['ours'], $fixture['metadata']['theirs']);
        $deploymentChoice = BlobMerge::mergeText(
            $fixture['themeDecision']['base'],
            $fixture['themeDecision']['ours'],
            $fixture['themeDecision']['theirs'],
            BlobMerge::STYLE_OURS,
        );
        $unionNotes = BlobMerge::mergeText(
            $fixture['blockNotes']['base'],
            $fixture['blockNotes']['ours'],
            $fixture['blockNotes']['theirs'],
            BlobMerge::STYLE_UNION,
        );
        $theme = BlobMerge::mergeText(
            $fixture['theme']['base'],
            $fixture['theme']['ours'],
            $fixture['theme']['theirs'],
            BlobMerge::STYLE_DIFF3,
            'base/theme.json',
            'ours/theme.json',
            'theirs/theme.json',
        );

        $t->true($metadata->isClean());
        $t->same($fixture['metadata']['expected'], $metadata->content);
        $t->true($deploymentChoice->isClean());
        $t->same(BlobMergeResult::RESOLUTION_AUTO_RESOLVED, $deploymentChoice->resolution);
        $t->same($fixture['themeDecision']['ours'], $deploymentChoice->content);
        $t->same(BlobMergeResult::RESOLUTION_AUTO_RESOLVED, $unionNotes->resolution);
        $t->same($fixture['blockNotes']['expectedUnion'], $unionNotes->content);
        $t->same(BlobMergeResult::RESOLUTION_CONFLICT, $theme->resolution);
        $t->contains('||||||| base/theme.json', $theme->content);
    },
];

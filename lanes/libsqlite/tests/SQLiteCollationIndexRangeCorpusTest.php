<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;

$nocaseEquals = new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::EQUALS, 'siteurl');
$nocaseNotEquals = new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::NOT_EQUALS, 'siteurl');
$nocaseLower = new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, '_transient_');
$nocaseUpper = new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::LESS_THAN, '_transient`');
$nocaseBetween = new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::BETWEEN, [
    'lower' => '_transient_',
    'upper' => '_transient_timeout_feed',
]);
$nocaseIn = new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IN_LIST, ['siteurl', 'home', 'blogname']);
$rtrimEquals = new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::EQUALS, 'cache_token');
$rtrimUpper = new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::LESS_THAN_OR_EQUAL, 'cache_token');

return [
    'nocase point lookup implies uppercase partial equality' => static function (TestRunner $t) use ($nocaseEquals): void {
        $t->same(true, $nocaseEquals->isImpliedByPointLookup('option_name', 'SITEURL', 'NOCASE'));
    },
    'binary point lookup does not imply uppercase partial equality' => static function (TestRunner $t) use ($nocaseEquals): void {
        $t->same(false, $nocaseEquals->isImpliedByPointLookup('option_name', 'SITEURL'));
    },
    'nocase point lookup rejects different partial equality' => static function (TestRunner $t) use ($nocaseEquals): void {
        $t->same(false, $nocaseEquals->isImpliedByPointLookup('option_name', 'HOME', 'NOCASE'));
    },
    'nocase point lookup implies not equals with folded value' => static function (TestRunner $t) use ($nocaseNotEquals): void {
        $t->same(true, $nocaseNotEquals->isImpliedByPointLookup('option_name', 'home', 'NOCASE'));
    },
    'nocase point lookup rejects not equals matching folded value' => static function (TestRunner $t) use ($nocaseNotEquals): void {
        $t->same(false, $nocaseNotEquals->isImpliedByPointLookup('option_name', 'SITEURL', 'NOCASE'));
    },
    'nocase lower range implies folded lower partial bound' => static function (TestRunner $t) use ($nocaseLower): void {
        $t->same(true, $nocaseLower->isImpliedByRangeLookup('option_name', '_TRANSIENT_', null, false, 'NOCASE'));
    },
    'binary lower range does not imply folded lower partial bound' => static function (TestRunner $t) use ($nocaseLower): void {
        $t->same(false, $nocaseLower->isImpliedByRangeLookup('option_name', '_TRANSIENT_', null, false));
    },
    'nocase stricter lower range implies partial lower bound' => static function (TestRunner $t) use ($nocaseLower): void {
        $t->same(true, $nocaseLower->isImpliedByRangeLookup('option_name', '_TRANSIENT_TIMEOUT_', null, false, 'NOCASE'));
    },
    'nocase weaker lower range rejects partial lower bound' => static function (TestRunner $t) use ($nocaseLower): void {
        $t->same(false, $nocaseLower->isImpliedByRangeLookup('option_name', '_SITE_', null, false, 'NOCASE'));
    },
    'nocase upper range implies folded exclusive partial upper bound' => static function (TestRunner $t) use ($nocaseUpper): void {
        $t->same(true, $nocaseUpper->isImpliedByRangeLookup('option_name', null, '_TRANSIENT`', false, 'NOCASE'));
    },
    'nocase inclusive same upper does not imply exclusive partial upper bound' => static function (TestRunner $t) use ($nocaseUpper): void {
        $t->same(false, $nocaseUpper->isImpliedByRangeLookup('option_name', null, '_TRANSIENT`', true, 'NOCASE'));
    },
    'nocase stricter upper range implies exclusive partial upper bound' => static function (TestRunner $t) use ($nocaseUpper): void {
        $t->same(true, $nocaseUpper->isImpliedByRangeLookup('option_name', null, '_TRANSIENT_TIMEOUT_', true, 'NOCASE'));
    },
    'nocase between range implies folded inclusive between partial' => static function (TestRunner $t) use ($nocaseBetween): void {
        $t->same(true, $nocaseBetween->isImpliedByRangeLookup('option_name', '_TRANSIENT_', '_TRANSIENT_TIMEOUT_FEED', true, 'NOCASE'));
    },
    'nocase between range rejects weaker upper partial' => static function (TestRunner $t) use ($nocaseBetween): void {
        $t->same(false, $nocaseBetween->isImpliedByRangeLookup('option_name', '_TRANSIENT_', '_TRANSIENT_TIMEOUT_LATER', true, 'NOCASE'));
    },
    'nocase between range rejects weaker lower partial' => static function (TestRunner $t) use ($nocaseBetween): void {
        $t->same(false, $nocaseBetween->isImpliedByRangeLookup('option_name', '_SITE_', '_TRANSIENT_TIMEOUT_FEED', true, 'NOCASE'));
    },
    'nocase in-list lookup implies folded partial subset' => static function (TestRunner $t) use ($nocaseIn): void {
        $t->same(true, $nocaseIn->isImpliedByInListLookup('option_name', ['SITEURL', 'HOME'], 'NOCASE'));
    },
    'binary in-list lookup does not imply folded partial subset' => static function (TestRunner $t) use ($nocaseIn): void {
        $t->same(false, $nocaseIn->isImpliedByInListLookup('option_name', ['SITEURL', 'HOME']));
    },
    'nocase in-list lookup ignores null while proving non-null folded values' => static function (TestRunner $t) use ($nocaseIn): void {
        $t->same(true, $nocaseIn->isImpliedByInListLookup('option_name', [null, 'BLOGNAME'], 'NOCASE'));
    },
    'nocase in-list lookup rejects value outside partial set' => static function (TestRunner $t) use ($nocaseIn): void {
        $t->same(false, $nocaseIn->isImpliedByInListLookup('option_name', ['SITEURL', 'ADMIN_EMAIL'], 'NOCASE'));
    },
    'rtrim point lookup implies padded partial equality' => static function (TestRunner $t) use ($rtrimEquals): void {
        $t->same(true, $rtrimEquals->isImpliedByPointLookup('option_name', "cache_token  \t", 'RTRIM'));
    },
    'binary point lookup does not imply padded rtrim partial equality' => static function (TestRunner $t) use ($rtrimEquals): void {
        $t->same(false, $rtrimEquals->isImpliedByPointLookup('option_name', 'cache_token  '));
    },
    'rtrim upper range implies padded inclusive partial upper bound' => static function (TestRunner $t) use ($rtrimUpper): void {
        $t->same(true, $rtrimUpper->isImpliedByRangeLookup('option_name', null, 'cache_token  ', true, 'RTRIM'));
    },
    'rtrim upper range rejects values beyond trimmed partial upper bound' => static function (TestRunner $t) use ($rtrimUpper): void {
        $t->same(false, $rtrimUpper->isImpliedByRangeLookup('option_name', null, 'cache_tokenized', true, 'RTRIM'));
    },
    'nocase and predicate requires every folded child predicate' => static function (TestRunner $t) use ($nocaseLower, $nocaseUpper): void {
        $predicate = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [$nocaseLower, $nocaseUpper]);
        $t->same(true, $predicate->isImpliedByRangeLookup('option_name', '_TRANSIENT_', '_TRANSIENT`', false, 'NOCASE'));
    },
    'nocase or predicate accepts one folded child predicate' => static function (TestRunner $t) use ($nocaseEquals, $nocaseLower): void {
        $predicate = new SQLiteIndexPredicate('', SQLiteIndexPredicate::OR, [$nocaseEquals, $nocaseLower]);
        $t->same(true, $predicate->isImpliedByPointLookup('option_name', 'SITEURL', 'NOCASE'));
    },
];

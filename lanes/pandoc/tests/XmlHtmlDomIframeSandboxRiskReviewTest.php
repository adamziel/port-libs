<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes iframe sandbox relaxation risks for reviewer handoff' => static function (TestRunner $t): void {
        $sandbox = 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation allow-storage-access-by-user-activation allow-popups bad-token';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<iframe id="escape-frame" src="/embed.html" sandbox="' . $sandbox . '">Escape fallback</iframe>'
                . '<iframe id="strict-frame" sandbox=""></iframe>',
            'iframe sandbox risk review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/iframe-sandbox-risk-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $frame = $summary[0];
        $strict = $summary[1];

        $t->same('iframe', $frame['embeddedResource']);
        $t->same('iframe-sandbox-token-risk-review', $frame['iframeSandboxReviewPolicy']);
        $t->same($sandbox, $frame['sandboxRaw']);
        $t->same([
            'allow-scripts',
            'allow-same-origin',
            'allow-popups',
            'allow-popups-to-escape-sandbox',
            'allow-top-navigation-by-user-activation',
            'allow-storage-access-by-user-activation',
            'allow-popups',
            'bad-token',
        ], $frame['sandboxTokens']);
        $t->same([
            'allow-scripts',
            'allow-same-origin',
            'allow-popups',
            'allow-popups-to-escape-sandbox',
            'allow-top-navigation-by-user-activation',
            'allow-storage-access-by-user-activation',
        ], $frame['sandboxValidTokens']);
        $t->same(['bad-token'], $frame['invalidSandboxTokens']);
        $t->same(['allow-popups'], $frame['duplicateSandboxTokens']);
        $t->same(8, $frame['sandboxTokenCount']);
        $t->same(6, $frame['sandboxValidTokenCount']);
        $t->same(false, $frame['sandboxStrict']);
        $t->same($frame['sandboxValidTokens'], $frame['sandboxRelaxationTokens']);
        $t->same(['allow-scripts'], $frame['sandboxExecutionTokens']);
        $t->same([
            'allow-same-origin',
            'allow-storage-access-by-user-activation',
        ], $frame['sandboxOriginStorageTokens']);
        $t->same([
            'allow-popups',
            'allow-popups-to-escape-sandbox',
        ], $frame['sandboxPopupTokens']);
        $t->same([
            'allow-popups-to-escape-sandbox',
            'allow-top-navigation-by-user-activation',
        ], $frame['sandboxNavigationTokens']);
        $t->same(['allow-top-navigation-by-user-activation'], $frame['sandboxTopNavigationTokens']);
        $t->same([
            'allow-top-navigation-by-user-activation',
            'allow-storage-access-by-user-activation',
        ], $frame['sandboxUserActivationTokens']);
        $t->same(['allow-popups-to-escape-sandbox'], $frame['sandboxEscapeTokens']);
        $t->same(true, $frame['sandboxAllowsPopupEscape']);
        $t->same(true, $frame['sandboxAllowsTopNavigation']);
        $t->same(true, $frame['sandboxAllowsStorageAccessByUserActivation']);
        $t->same([
            'invalid-iframe-sandbox-token',
            'duplicate-iframe-sandbox-token',
            'iframe-sandbox-allows-scripts-same-origin',
            'iframe-sandbox-allows-popup-escape',
            'iframe-sandbox-allows-top-navigation',
        ], $frame['sandboxRiskIssueCodes']);
        $t->same([
            ['code' => 'invalid-iframe-sandbox-token', 'token' => 'bad-token'],
            ['code' => 'duplicate-iframe-sandbox-token', 'token' => 'allow-popups'],
            ['code' => 'iframe-sandbox-allows-scripts-same-origin', 'tokens' => ['allow-scripts', 'allow-same-origin']],
            ['code' => 'iframe-sandbox-allows-popup-escape', 'token' => 'allow-popups-to-escape-sandbox'],
            ['code' => 'iframe-sandbox-allows-top-navigation', 'tokens' => ['allow-top-navigation-by-user-activation']],
        ], $frame['sandboxRiskIssues']);
        $t->same([
            'invalid-iframe-sandbox-token',
            'duplicate-iframe-sandbox-token',
            'iframe-sandbox-allows-scripts-same-origin',
        ], $frame['iframePolicyIssueCodes']);
        $t->same('Escape fallback', $frame['fallbackText']);

        $t->same('iframe-sandbox-token-risk-review', $strict['iframeSandboxReviewPolicy']);
        $t->same('', $strict['sandboxRaw']);
        $t->same([], $strict['sandboxTokens']);
        $t->same([], $strict['sandboxRelaxationTokens']);
        $t->same(true, $strict['sandboxStrict']);
        $t->same([], $strict['sandboxRiskIssues']);
        $t->same([], $strict['sandboxRiskIssueCodes']);
        $t->same([], $strict['iframePolicyIssueCodes']);

        $t->contains('sandbox="' . $sandbox . '"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/iframe-sandbox-risk-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];

<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\JiraReader;
use PortLibs\Pandoc\PandocConverter;

$nodesOfType = static function (AstNode $node, string $type) use (&$nodesOfType): array {
    $nodes = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($nodes, ...$nodesOfType($child, $type));
    }

    return $nodes;
};

return [
    'keeps unsafe Jira targets literal across link syntaxes' => static function (TestRunner $t) use ($nodesOfType): void {
        $unsafeSources = [
            'ordinary pipe target' => '[Trusted|javascript:alert(1)]',
            'encoded pipe target' => '[Trusted|java%73cript:alert(1)]',
            'mixed percent and entity pipe target' => '[Trusted|java%26#x73;cript://alert(1)]',
            'smart link target' => '[Trusted|javascript://%0Aalert(1)|smart-link]',
            'bare attachment target' => '[^javascript:alert(1)]',
            'labelled attachment target' => '[Trusted^javascript:alert(1)]',
            'entity encoded attachment target' => '[^java&#x73;cript:alert(1)]',
            'ordinary bracket target' => '[javascript://%0Aalert(1)]',
            'bare autolink target' => 'javascript://%0Aalert(1)',
            'data target' => '[Trusted|data://text/plain,blocked]',
            'vbscript target' => '[Trusted|vbscript://alert(1)]',
        ];

        foreach ($unsafeSources as $name => $source) {
            $document = (new JiraReader())->read($source);
            $wordpress = PandocConverter::write($document, 'wordpress');

            $t->same([], $nodesOfType($document, 'link'), $name);
            $t->true(!str_contains(strtolower($wordpress), '<a '), $name . ' must not render an anchor');
            $t->true(!str_contains(strtolower($wordpress), 'href="javascript:'), $name . ' must not render a javascript href');
        }
    },

    'keeps unsafe Jira image resource targets literal' => static function (TestRunner $t) use ($nodesOfType): void {
        $unsafeSources = [
            'javascript image target' => '!javascript:alert(1)!',
            'percent-encoded javascript image target' => '!java%73cript:alert(1)!',
            'entity-encoded javascript image target' => '!java&#x73;cript:alert(1)!',
            'vbscript image target' => '!vbscript:alert(1)!',
            'html data image target' => '!data:text/html,blocked!',
            'svg data image target' => '!data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=!',
            'file image target' => '!file:///etc/passwd!',
            'entity-encoded file image target' => '!file&#x3A;///etc/passwd!',
            'percent-encoded file image target' => '!file%3A///etc/passwd!',
            'mailto image target' => '!mailto:ops@example.test!',
        ];

        foreach ($unsafeSources as $name => $source) {
            $document = (new JiraReader())->read($source);
            $wordpress = PandocConverter::write($document, 'wordpress');

            $t->same([], $nodesOfType($document, 'image'), $name);
            $t->true(!str_contains(strtolower($wordpress), '<img '), $name . ' must not render an image resource');
        }
    },

    'retains safe Jira image resource forms after target validation' => static function (TestRunner $t) use ($nodesOfType): void {
        $safeSources = [
            'relative image' => ['!image.png!', 'image.png'],
            'absolute-path image' => ['!/media/cover.png!', '/media/cover.png'],
            'external image' => ['!https://cdn.example.test/cover.png!', 'https://cdn.example.test/cover.png'],
            'raster data image' => ['!data:image/png;base64,iVBORw0KGgo=!', 'data:image/png;base64,iVBORw0KGgo='],
        ];

        foreach ($safeSources as $name => [$source, $url]) {
            $document = (new JiraReader())->read($source);
            $images = $nodesOfType($document, 'image');

            $t->same(1, count($images), $name);
            $t->same($url, $images[0]->attr('url'), $name);
            $t->same($url, $images[0]->attr('src'), $name);
        }
    },

    'retains safe Jira link forms after target validation' => static function (TestRunner $t) use ($nodesOfType): void {
        $safeSources = [
            'external pipe link' => ['[Trusted|https://example.test/path]', 'https://example.test/path', []],
            'fragment pipe link' => ['[Trusted|#fragment]', '#fragment', []],
            'mailto pipe link' => ['[Trusted|mailto:me@example.test]', 'mailto:me@example.test', []],
            'user account link' => ['[~alice]', '~alice', ['user-account']],
            'attachment link' => ['[^report.pdf]', 'report.pdf', ['attachment']],
            'labelled attachment link' => ['[Trusted^report.pdf]', 'report.pdf', ['attachment']],
            'smart card link' => ['[Trusted|https://example.test/card|smart-card]', 'https://example.test/card', ['smart-card']],
            'bare external autolink' => ['https://example.test/path', 'https://example.test/path', []],
        ];

        foreach ($safeSources as $name => [$source, $url, $classes]) {
            $document = (new JiraReader())->read($source);
            $links = $nodesOfType($document, 'link');

            $t->same(1, count($links), $name);
            $t->same($url, $links[0]->attr('url'), $name);
            $t->same($classes, $links[0]->attr('classes'), $name);
        }
    },

    'preserves Jira Unicode macro and greedy attachment semantics while prefiltering links' => static function (TestRunner $t) use ($nodesOfType): void {
        foreach (["{anchor:\xFF}", "{color:\xFF}x{color}"] as $source) {
            $document = (new JiraReader())->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');

            $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), bin2hex($source));
            $t->same([], $nodesOfType($document, 'span'), bin2hex($source));
        }

        $terminalCaret = (new JiraReader())->read('[a^b^]');
        $terminalCaretLinks = $nodesOfType($terminalCaret, 'link');
        $fuzz = 'x0a[_x.1kxp:{daahxz_jjj. e64aq6#2n|4.^hk9^]:928y}w6t.l113+~q:do3pj8k}hg#4{#f~2yh/[2*i!5d#r';
        $fuzzLinks = $nodesOfType((new JiraReader())->read($fuzz), 'link');

        $t->same(1, count($terminalCaretLinks));
        $t->same('b^', $terminalCaretLinks[0]->attr('url'));
        $t->same(['attachment'], $terminalCaretLinks[0]->attr('classes'));
        $t->same(1, count($fuzzLinks));
        $t->same('hk9^', $fuzzLinks[0]->attr('url'));
        $t->same(['attachment'], $fuzzLinks[0]->attr('classes'));

        $prefixInvalidAnchor = (new JiraReader())->read("\xFF{anchor:x}");
        $suffixInvalidAnchor = (new JiraReader())->read("{anchor:x}\xFF");
        $prefixInvalidColor = (new JiraReader())->read("\xFF{color:red}x{color}");
        $suffixInvalidColor = (new JiraReader())->read("{color:red}x{color}\xFF");
        $invalidAttachmentContent = (new JiraReader())->read("[label|~a^/\xFF]");
        $validAttachmentAfterInvalidPrefix = (new JiraReader())->read("\xFF[label^file.pdf]");

        $t->same(1, count($nodesOfType($prefixInvalidAnchor, 'span')));
        $t->same([], $nodesOfType($suffixInvalidAnchor, 'span'));
        $t->same(1, count($nodesOfType($prefixInvalidColor, 'span')));
        $t->same([], $nodesOfType($suffixInvalidColor, 'span'));
        $invalidAttachmentLinks = $nodesOfType($invalidAttachmentContent, 'link');
        $validAttachmentLinks = $nodesOfType($validAttachmentAfterInvalidPrefix, 'link');
        $t->same(1, count($invalidAttachmentLinks));
        $t->same(['user-account'], $invalidAttachmentLinks[0]->attr('classes'));
        $t->same(1, count($validAttachmentLinks));
        $t->same('file.pdf', $validAttachmentLinks[0]->attr('url'));
    },

    'preserves a large literal Jira paragraph without per-offset suffix copies' => static function (TestRunner $t): void {
        $source = rtrim(str_repeat('ordinary Jira prose ', 6000));
        $document = (new JiraReader())->read($source);
        $paragraph = $document->children[0] ?? new AstNode('missing');

        $t->same('paragraph', $paragraph->type);
        $t->same($source, $paragraph->attr('text'));
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));

        $unmatchedBrackets = str_repeat('[', 8192);
        $bracketDocument = (new JiraReader())->read($unmatchedBrackets);
        $bracketParagraph = $bracketDocument->children[0] ?? new AstNode('missing');

        $t->same('paragraph', $bracketParagraph->type);
        $t->same(strlen($unmatchedBrackets), strlen((string) $bracketParagraph->attr('text')));
        $t->same(hash('sha256', $unmatchedBrackets), hash('sha256', (string) $bracketParagraph->attr('text')));
    },

    'keeps malformed Jira inline delimiter runs linear and literal' => static function (TestRunner $t): void {
        $cases = [
            'unclosed color prefixes' => str_repeat('{color:', 16_384),
            'unclosed color headers' => str_repeat('{color:orange}', 16_384),
            'unclosed code delimiters' => str_repeat('{{code', 16_384),
            'nested brackets with one closer' => str_repeat('[', 65_536) . ']',
            'independent bracket candidates without a target delimiter' => str_repeat('[x]', 16_384),
            'nested brackets before an unsafe pipe target' => str_repeat('[', 65_536) . '|javascript:alert(1)]',
            'nested brackets before an unsafe attachment target' => str_repeat('[', 65_536) . '^javascript:alert(1)]',
            'escaped style delimiters' => str_repeat('*x\\', 16_384),
        ];

        foreach ($cases as $name => $source) {
            $document = (new JiraReader())->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');

            $t->same('paragraph', $paragraph->type, $name);
            $t->same(strlen($source), strlen((string) $paragraph->attr('text')), $name);
            $t->same(hash('sha256', $source), hash('sha256', (string) $paragraph->attr('text')), $name);
        }
    },

    'retains Jira first-close and fallback semantics while caching inline searches' => static function (TestRunner $t) use ($nodesOfType): void {
        $code = (new JiraReader())->read('{{outer {{inner}} tail}}');
        $codeNode = $code->children[0]->children[0] ?? new AstNode('missing');
        $nestedLink = (new JiraReader())->read('[[label|https://example.test]');
        $nestedLinks = $nodesOfType($nestedLink, 'link');
        $attachment = (new JiraReader())->read('[a|b^file.pdf]');
        $attachmentLinks = $nodesOfType($attachment, 'link');
        $imageFallback = (new JiraReader())->read('!javascript:alert(1)!https://example.test/a.png!');
        $images = $nodesOfType($imageFallback, 'image');

        $t->same('code', $codeNode->type);
        $t->same('outer {{inner', $codeNode->attr('text'));
        $t->same(1, count($nestedLinks));
        $t->same('[label', $nestedLinks[0]->children[0]->attr('text'));
        $t->same('https://example.test', $nestedLinks[0]->attr('url'));
        $t->same(1, count($attachmentLinks));
        $t->same(['attachment'], $attachmentLinks[0]->attr('classes'));
        $t->same('file.pdf', $attachmentLinks[0]->attr('url'));
        $t->same(1, count($images));
        $t->same('https://example.test/a.png', $images[0]->attr('url'));
    },
];

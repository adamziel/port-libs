<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocHtmlTagSoupReader
{
    /** @var list<TagSoupTag> */
    private array $tokens = [];
    private int $index = 0;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function read(string $html): AstNode
    {
        $parser = new TagSoupParser();
        $this->tokens = TagSoupParser::canonicalizeTags($parser->parse($html));
        $this->index = 0;
        $blocks = $this->parseBlocksUntil([]);

        return new AstNode('document', [
            'sourceFormat' => 'html',
            'meta' => [
                'sourceFormat' => 'html',
                'reader' => self::class,
                'readerScope' => 'tagsoup-pandoc-html-reader-port-in-progress',
                'htmlTokenizer' => TagSoupParser::class,
                'sourceBytes' => strlen($html),
                'sourceSha256' => hash('sha256', $html),
            ],
        ], $blocks);
    }

    /**
     * @return list<TagSoupTag>
     */
    public function tokenize(string $html): array
    {
        return TagSoupParser::canonicalizeTags((new TagSoupParser())->parse($html));
    }

    /**
     * @param list<string> $stopTags
     * @return list<AstNode>
     */
    private function parseBlocksUntil(array $stopTags): array
    {
        $blocks = [];
        while (($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && in_array($token->name, $stopTags, true)) {
                break;
            }

            if ($this->skipIgnorable($token)) {
                continue;
            }

            if ($token->type === TagSoupTag::OPEN) {
                if (str_starts_with($token->name, '!') || str_starts_with($token->name, '?')) {
                    $this->index++;
                    continue;
                }
                if ($token->name === 'html' || $token->name === 'body') {
                    $this->index++;
                    array_push($blocks, ...$this->parseBlocksUntil([$token->name]));
                    $this->consumeClose($token->name);
                    continue;
                }
                if ($token->name === 'head') {
                    $this->index++;
                    $this->skipUntilClose('head');
                    continue;
                }
                $block = $this->parseOpenBlock($token, $stopTags);
                if ($block instanceof AstNode) {
                    $blocks[] = $block;
                    continue;
                }
            }

            $beforeInlineIndex = $this->index;
            $inlines = $this->parseInlinesUntil($stopTags, stopBeforeBlock: true);
            if ($inlines !== []) {
                $blocks[] = new AstNode('paragraph', ['text' => $this->plainTextFromInlines($inlines)], $inlines);
                continue;
            }

            if ($this->index === $beforeInlineIndex) {
                $this->index++;
            }
        }

        return $blocks;
    }

    /**
     * @param list<string> $outerStopTags
     */
    private function parseOpenBlock(TagSoupTag $token, array $outerStopTags): ?AstNode
    {
        $name = $token->name;
        if (in_array($name, ['main', 'section', 'article', 'header', 'footer', 'aside'], true)) {
            $this->index++;
            $children = $this->parseBlocksUntil([$name]);
            $this->consumeClose($name);

            return new AstNode('div', $this->pandocAttrs($token), $children);
        }

        if ($name === 'p' || $name === 'address') {
            $this->index++;
            $inlines = $this->parseInlinesUntil([$name]);
            $this->consumeClose($name);

            return new AstNode('paragraph', ['text' => $this->plainTextFromInlines($inlines)], $inlines);
        }

        if (preg_match('/^h([1-6])$/', $name, $m) === 1) {
            $this->index++;
            $inlines = $this->parseInlinesUntil([$name]);
            $this->consumeClose($name);

            return new AstNode('heading', [
                'level' => (int) $m[1],
                'text' => $this->plainTextFromInlines($inlines),
            ], $inlines);
        }

        if ($name === 'blockquote') {
            $this->index++;
            $blocks = $this->parseBlocksUntil(['blockquote']);
            $this->consumeClose('blockquote');

            return new AstNode('blockquote', $this->pandocAttrs($token), $blocks);
        }

        if ($name === 'ul' || $name === 'ol') {
            return $this->parseListBlock($token);
        }

        if ($name === 'pre') {
            $this->index++;
            $text = $this->collectTextUntilClose('pre');
            $this->consumeClose('pre');

            return new AstNode('code_block', ['text' => rtrim($text, "\n")]);
        }

        if ($name === 'hr') {
            $this->index++;
            return new AstNode('horizontal_rule');
        }

        if (in_array($name, ['table', 'figure', 'dl'], true)) {
            $raw = $this->renderBalancedTokenSource($name);

            return new AstNode('raw_html', ['format' => 'html', 'html' => $raw, 'text' => $raw]);
        }

        if ($this->isBlockElement($name)) {
            $this->index++;
            $children = $this->parseBlocksUntil([$name, ...$outerStopTags]);
            $this->consumeClose($name);

            return new AstNode('div', $this->pandocAttrs($token), $children);
        }

        return null;
    }

    private function parseListBlock(TagSoupTag $list): AstNode
    {
        $ordered = $list->name === 'ol';
        $attrs = $ordered
            ? ['start' => (int) ($this->attribute($list, 'start') ?: 1), 'style' => 'default', 'delimiter' => 'default']
            : [];
        $items = [];
        $this->index++;
        while (($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === $list->name) {
                break;
            }
            if ($this->skipIgnorable($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === 'li') {
                $this->index++;
                $blocks = $this->parseBlocksUntil(['li']);
                $this->consumeClose('li');
                if ($blocks === []) {
                    $blocks[] = new AstNode('plain');
                }
                $items[] = new AstNode('list_item', ['text' => $this->plainTextFromBlocks($blocks)], $blocks);
                continue;
            }
            $this->index++;
        }
        $this->consumeClose($list->name);

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
    }

    /**
     * @param list<string> $stopTags
     * @return list<AstNode>
     */
    private function parseInlinesUntil(array $stopTags, bool $stopBeforeBlock = false): array
    {
        $inlines = [];
        while (($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && in_array($token->name, $stopTags, true)) {
                break;
            }
            if ($token->type === TagSoupTag::OPEN && $stopBeforeBlock && $this->isBlockElement($token->name)) {
                break;
            }
            if ($this->skipIgnorable($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::TEXT) {
                $inlines[] = new AstNode('text', ['text' => $token->text]);
                $this->index++;
                continue;
            }
            if ($token->type === TagSoupTag::OPEN) {
                array_push($inlines, ...$this->parseOpenInline($token));
                continue;
            }
            $this->index++;
        }

        return $this->normalizeInlineText($inlines);
    }

    /**
     * @return list<AstNode>
     */
    private function parseOpenInline(TagSoupTag $token): array
    {
        $name = $token->name;
        if ($name === 'br') {
            $this->index++;
            $this->consumeClose('br');
            return [new AstNode('linebreak')];
        }

        if ($name === 'em' || $name === 'i') {
            $this->index++;
            $children = $this->parseInlinesUntil([$name]);
            $this->consumeClose($name);

            return [new AstNode('emph', [], $children)];
        }

        if ($name === 'strong' || $name === 'b') {
            $this->index++;
            $children = $this->parseInlinesUntil([$name]);
            $this->consumeClose($name);

            return [new AstNode('strong', [], $children)];
        }

        if ($name === 'code' || $name === 'kbd' || $name === 'samp') {
            $this->index++;
            $text = $this->collectTextUntilClose($name);
            $this->consumeClose($name);

            return [new AstNode('code', ['text' => $text])];
        }

        if ($name === 'a') {
            $this->index++;
            $children = $this->parseInlinesUntil(['a']);
            $this->consumeClose('a');
            $url = $this->attribute($token, 'href');
            if ($url === '') {
                return $children;
            }

            return [new AstNode('link', ['url' => $url, 'title' => $this->attribute($token, 'title')], $children)];
        }

        if ($name === 'img') {
            $this->index++;
            $alt = $this->attribute($token, 'alt');
            $children = $alt === '' ? [] : [new AstNode('text', ['text' => $alt])];

            return [new AstNode('image', [
                'url' => $this->attribute($token, 'src'),
                'title' => $this->attribute($token, 'title'),
                'alt' => $alt,
            ], $children)];
        }

        if (in_array($name, ['span', 'small', 'cite', 'sub', 'sup', 'u', 's', 'del', 'ins', 'mark', 'q'], true)) {
            $this->index++;
            $children = $this->parseInlinesUntil([$name]);
            $this->consumeClose($name);

            return $children;
        }

        $this->index++;
        $children = $this->parseInlinesUntil([$name]);
        $this->consumeClose($name);

        return $children;
    }

    private function current(): ?TagSoupTag
    {
        return $this->tokens[$this->index] ?? null;
    }

    private function skipIgnorable(TagSoupTag $token): bool
    {
        if (in_array($token->type, [TagSoupTag::POSITION, TagSoupTag::WARNING, TagSoupTag::COMMENT], true)) {
            $this->index++;
            return true;
        }

        return false;
    }

    private function consumeClose(string $name): void
    {
        $token = $this->current();
        if ($token instanceof TagSoupTag && $token->type === TagSoupTag::CLOSE && $token->name === $name) {
            $this->index++;
        }
    }

    private function skipUntilClose(string $name): void
    {
        while (($token = $this->current()) instanceof TagSoupTag) {
            $this->index++;
            if ($token->type === TagSoupTag::CLOSE && $token->name === $name) {
                return;
            }
        }
    }

    private function collectTextUntilClose(string $name): string
    {
        $text = '';
        while (($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === $name) {
                break;
            }
            if ($token->type === TagSoupTag::TEXT) {
                $text .= $token->text;
            }
            $this->index++;
        }

        return $text;
    }

    private function renderBalancedTokenSource(string $name): string
    {
        $start = $this->index;
        $depth = 0;
        while (($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::OPEN && $token->name === $name) {
                $depth++;
            } elseif ($token->type === TagSoupTag::CLOSE && $token->name === $name) {
                $depth--;
                $this->index++;
                if ($depth <= 0) {
                    break;
                }
                continue;
            }
            $this->index++;
        }

        return (new TagSoupRenderer())->render(array_slice($this->tokens, $start, $this->index - $start));
    }

    private function attribute(TagSoupTag $tag, string $name): string
    {
        foreach ($tag->attributes as $attribute) {
            if ($attribute['name'] === $name) {
                return $attribute['value'];
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function pandocAttrs(TagSoupTag $tag): array
    {
        $id = $this->attribute($tag, 'id');
        $classes = preg_split('/\s+/', trim($this->attribute($tag, 'class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keyValues = [];
        foreach ($tag->attributes as $attribute) {
            if (in_array($attribute['name'], ['id', 'class'], true)) {
                continue;
            }
            $keyValues[$attribute['name']] = $attribute['value'];
        }

        return ['id' => $id, 'classes' => $classes, 'keyValues' => $keyValues];
    }

    private function isBlockElement(string $name): bool
    {
        return in_array($name, [
            'address', 'article', 'aside', 'blockquote', 'body', 'dd', 'details', 'dialog', 'div', 'dl', 'dt',
            'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head',
            'header', 'hr', 'html', 'li', 'main', 'menu', 'nav', 'ol', 'p', 'pre', 'section', 'table', 'ul',
        ], true);
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function normalizeInlineText(array $inlines): array
    {
        $normalized = [];
        foreach ($inlines as $inline) {
            if ($inline->type === 'text') {
                $text = preg_replace('/\s+/', ' ', (string) $inline->attr('text', '')) ?? '';
                $lastIndex = array_key_last($normalized);
                $last = $lastIndex === null ? null : $normalized[$lastIndex];
                if ($last instanceof AstNode && $last->type === 'linebreak') {
                    $text = ltrim($text);
                }
                if ($text === '') {
                    continue;
                }
                if ($last instanceof AstNode && $last->type === 'text') {
                    $normalized[$lastIndex] = new AstNode('text', ['text' => (string) $last->attr('text', '') . $text]);
                    continue;
                }
                $normalized[] = new AstNode('text', ['text' => $text]);
                continue;
            }

            $normalized[] = $inline;
        }

        if ($normalized !== [] && $normalized[0]->type === 'text') {
            $normalized[0] = new AstNode('text', ['text' => ltrim((string) $normalized[0]->attr('text', ''))]);
            if ($normalized[0]->attr('text', '') === '') {
                array_shift($normalized);
            }
        }
        $lastIndex = array_key_last($normalized);
        if ($lastIndex !== null && $normalized[$lastIndex]->type === 'text') {
            $normalized[$lastIndex] = new AstNode('text', ['text' => rtrim((string) $normalized[$lastIndex]->attr('text', ''))]);
            if ($normalized[$lastIndex]->attr('text', '') === '') {
                array_pop($normalized);
            }
        }

        return array_values($normalized);
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function plainTextFromInlines(array $inlines): string
    {
        $text = '';
        foreach ($inlines as $inline) {
            if ($inline->type === 'text' || $inline->type === 'code') {
                $text .= (string) $inline->attr('text', '');
            } elseif ($inline->type === 'linebreak') {
                $text .= "\n";
            } else {
                $text .= $this->plainTextFromInlines($inline->children);
            }
        }

        return trim(preg_replace('/[ \t\f\v]+/', ' ', $text) ?? $text);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainTextFromBlocks(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $text = (string) $block->attr('text', '');
            if ($text === '') {
                $text = $this->plainTextFromBlocks($block->children);
            }
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return trim(implode(' ', $parts));
    }
}

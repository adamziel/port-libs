<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/** Core heading, paragraph, list, table, and code top-level nodes. */
final class WordPressTopLevelCoreNodeRenderer
{
    /** @param array<string, mixed> $options */
    public function __construct(
        private readonly array $options,
        private readonly WordPressBlockWriter $writer,
    ) {
    }

    public function render(AstNode $node): ?string
    {
        if ($node->type === 'heading') {
            $level = (int) $node->attr('level', 2);
            $headingAttrs = ['level' => $level];
            $alignment = $this->call('blockTextAlignment', $node);
            if ($alignment !== '') {
                $headingAttrs['textAlign'] = $alignment;
            }
            $headingAttrs = array_replace($headingAttrs, $this->call('blockColorCommentAttrs', $node));

            return $this->call('blockComment', 'heading', $headingAttrs)
                . "\n" . '<h' . $level . $this->call('renderHeadingAttrs', $node) . '>' . $this->call('renderInlines', $node) . '</h' . $level . '>'
                . "\n" . '<!-- /wp:heading -->';
        }

        return match ($node->type) {
            'paragraph' => $this->renderParagraph($node),
            'plain' => '<!-- wp:paragraph -->' . "\n" . '<p>' . $this->call('renderInlines', $node) . '</p>' . "\n" . '<!-- /wp:paragraph -->',
            'bullet_list' => $this->call('renderList', $node, false),
            'ordered_list' => $this->call('renderList', $node, true),
            'definition_list' => $this->call('renderDefinitionList', $node),
            'table' => $this->call('renderTable', $node),
            'code_block' => $this->call('renderCodeBlock', $node),
            default => null,
        };
    }

    public function renderParagraph(AstNode $node): string
    {
        $chart = $this->renderablePptxChart($node);
        if ($chart !== null) {
            return '<!-- wp:html -->' . "\n" . $this->call('renderPptxChart', $node, $chart) . "\n" . '<!-- /wp:html -->';
        }
        if (count($node->children) === 1 && $node->children[0]->type === 'image') {
            return $this->extendedRenderer()->renderParagraphImageBlock($node->children[0]);
        }

        $alignment = $this->call('blockTextAlignment', $node);
        $commentAttrs = array_replace(
            $alignment === '' ? [] : ['align' => $alignment],
            $this->call('blockColorCommentAttrs', $node)
        );
        $htmlAttrs = $alignment === ''
            ? $this->call('renderBlockHtmlAttrs', $node)
            : $this->call('renderBlockHtmlAttrsWithClasses', $node, ['has-text-align-' . $alignment]);
        $htmlAttrs .= $this->call('blockColorHtmlAttr', $node);

        return $this->call('blockComment', 'paragraph', $commentAttrs)
            . "\n" . '<p' . $htmlAttrs . '>' . $this->call('renderInlines', $node) . '</p>'
            . "\n" . '<!-- /wp:paragraph -->';
    }

    /** @return array<string, mixed>|null */
    private function renderablePptxChart(AstNode $node): ?array
    {
        $chart = $node->attr('pptxChart');
        $series = is_array($chart) ? ($chart['series'] ?? null) : null;

        return is_array($chart) && ($chart['issues'] ?? []) === [] && is_array($series) && $series !== []
            ? $chart
            : null;
    }

    private function extendedRenderer(): WordPressExtendedNodeRenderer
    {
        return $this->call('extendedRenderer');
    }

    private function call(string $name, mixed ...$arguments): mixed
    {
        return $this->writer->{$name}(...$arguments);
    }
}

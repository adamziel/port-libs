<?php

declare(strict_types=1);

use PortLibs\LightningCSS\NestingTransformer;

return [
    'nesting transformer maps upstream direct parent selector lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{color:#00f}.foo>.bar{color:red}',
            $transformer->lower('.foo { color: blue; & > .bar { color: red; } }')
        );
        $t->same(
            '.foo{color:#00f}.foo.bar{color:red}',
            $transformer->lower('.foo { color: blue; &.bar { color: red; } }')
        );
        $t->same(
            '.foo{color:#00f}.foo.foo{padding:2ch}',
            $transformer->lower('.foo { color: blue; && { padding: 2ch; } }')
        );
    },
    'nesting transformer maps upstream selector-list parent references' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.foo, .bar {
  color: blue;
  & + .baz, &.qux {
    color: red;
  }
}
CSS;

        $t->same(
            '.foo,.bar{color:#00f}:is(.foo,.bar)+.baz,:is(.foo,.bar).qux{color:red}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer maps upstream implicit descendant and recursive nesting' => static function (TestRunner $t): void {
        $css = <<<'CSS'
figure {
  margin: 0;

  & > figcaption {
    background: hsl(0 0% 0% / 50%);

    & > p {
      font-size: .9rem;
    }
  }
}
CSS;

        $t->same(
            'figure{margin:0}figure>figcaption{background:#00000080}figure>figcaption>p{font-size:.9rem}',
            (new NestingTransformer())->lower($css)
        );
    },
    'nesting transformer maps upstream attached type selector lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            'article.foo>figure{color:red}',
            $transformer->lower('.foo { &article > figure { color: red; } }')
        );
        $t->same(
            'span:is(div>.foo){background:green}',
            $transformer->lower('div > .foo { &span { background: green; } }')
        );
        $t->same(
            'h1:is(.foo .bar){background:green}',
            $transformer->lower('.foo .bar { &h1 { background: green; } }')
        );
        $t->same(
            'h1.foo.bar{background:green}',
            $transformer->lower('.foo.bar { &h1 { background: green; } }')
        );
        $t->same(
            'h1:is(.foo .bar) .baz{background:green}',
            $transformer->lower('.foo .bar { &h1 .baz { background: green; } }')
        );
        $t->same(
            'h1:is(.foo .bar){background:green}',
            $transformer->lower('.foo .bar { @nest h1& { background: green; } }')
        );
        $t->same(
            'html:scope{color:red}.foo:scope{color:#00f}',
            $transformer->lower('&html { color: red; } .foo& { color: blue; }')
        );
    },
    'nesting transformer maps upstream nested media and @nest lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{display:grid}@media (orientation:landscape){.foo{grid-auto-flow:column}}',
            $transformer->lower('.foo { display: grid; @media (orientation: landscape) { grid-auto-flow: column; } }')
        );
        $t->same(
            '.foo{color:red}.parent .foo{color:#00f}',
            $transformer->lower('.foo { color: red; @nest .parent & { color: blue; } }')
        );
    },
    'nesting transformer maps upstream parent placement and leading combinators' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{color:red}.bar .foo{color:#00f}',
            $transformer->lower('.foo { color: red; .bar & { color: blue; } }')
        );
        $t->same(
            '.foo{color:red}.foo+.bar+.foo{color:#00f}',
            $transformer->lower('.foo { color: red; + .bar + & { color: blue; } }')
        );
        $t->same(
            '.baz :is(.foo .bar){background:green}',
            $transformer->lower('.foo .bar { .baz & { background: green; } }')
        );
        $t->same(
            '.baz :is(.foo .bar){background:green}',
            $transformer->lower('.foo .bar { @nest .baz & { background: green; } }')
        );
    },
    'nesting transformer maps upstream nested supports and container lowering' => static function (TestRunner $t): void {
        $transformer = new NestingTransformer();

        $t->same(
            '.foo{display:grid}@supports (foo:bar){.foo{grid-auto-flow:column}.foo>.bar{color:#00f}}',
            $transformer->lower('.foo { display: grid; @supports (foo: bar) { grid-auto-flow: column; & > .bar { color: blue; } } }')
        );
        $t->same(
            '@container (width>=100px){.foo{grid-auto-flow:column}article.foo>figure{color:red}}',
            $transformer->lower('.foo { @container (min-width: 100px) { grid-auto-flow: column; &article > figure { color: red; } } }')
        );
    },
    'wordpress nested block stylesheet lowers without node' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-query {
  color: blue;

  .wp-block-post-title {
    color: red;

    .is-featured & {
      opacity: .9;
    }
  }

  &:hover .wp-block-post-title {
    text-decoration-color: yellow;
  }

  @media (min-width: 600px) {
    .wp-block-post-title {
      color: blue;
    }
  }
}
CSS;

        $t->same(
            '.wp-block-query{color:#00f}.wp-block-query .wp-block-post-title{color:red}.is-featured :is(.wp-block-query .wp-block-post-title){opacity:.9}.wp-block-query:hover .wp-block-post-title{text-decoration-color:#ff0}@media (width>=600px){.wp-block-query .wp-block-post-title{color:#00f}}',
            (new NestingTransformer())->lower($css)
        );
    },
];

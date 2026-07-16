<?php

declare(strict_types=1);

return [
    'document' => 'switch_trans.pdf',
    'sourceCommit' => 'da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34',
    'markerPath' => 'data/examples/marker/switch_transformers.md',
    'referencePath' => 'data/examples/nougat/switch_transformers.md',
    'referenceKind' => 'committed-nougat-output-surrogate',
    'scoreThreshold' => 0.75,
    'chunkLength' => 500,
    'markerExcerpt' => <<<'MARKER'
# Switch Transformers: Scaling To Trillion Parameter Models With Simple And Efficient Sparsity

William Fedus∗
liamfedus@google.com Barret Zoph∗
barretzoph@google.com Noam Shazeer noam@google.com Google, Mountain View, CA 94043, USA
Editor: Alexander Clark

## Abstract

In deep learning, models typically reuse the same parameters for all inputs. Mixture of Experts (MoE) models defy this and instead select *different* parameters for each incoming example. The result is a sparsely-activated model—with an outrageous number of parameters—but a constant computational cost. However, despite several notable successes of MoE, widespread adoption has been hindered by complexity, communication costs, and training instability. We address these with the introduction of the Switch Transformer. We simplify the MoE routing algorithm and design intuitive improved models with reduced communication and computational costs. Our proposed training techniques mitigate the instabilities, and we show large sparse models may be trained, for the first time, with lower precision (bfloat16) formats. We design models based off T5-Base and T5-Large (Raffel et al., 2019) to obtain up to 7x increases in pre-training speed with the same computational resources.
MARKER,
    'referenceExcerpt' => <<<'REFERENCE'
# Switch Transformers: Scaling to Trillion Parameter Models with Simple and Efficient Sparsity

William Fedus

Jianfedus@google.com

Barret Zoph

barretzoph@google.com

Noam Shazeer

noam@google.com

Google, Mountain View, CA 94043, USA

###### Abstract

In deep learning, models typically reuse the same parameters for all inputs. Mixture of Experts (MoE) models defy this and instead select _different_ parameters for each incoming example. The result is a sparsely-activated model--with an outrageous number of parameters--but a constant computational cost. However, despite several notable successes of MoE, widespread adoption has been hindered by complexity, communication costs, and training instability. We address these with the introduction of the Switch Transformer. We simplify the MoE routing algorithm and design intuitive improved models with reduced communication and computational costs. Our proposed training techniques mitigate the instabilities, and we show large sparse models may be trained, for the first time, with lower precision (bfloat16) formats. We design models based off T5-Base and T5-Large (Raffel et al., 2019) to obtain up to 7x increases in pre-training speed with the same computational resources.
REFERENCE,
];

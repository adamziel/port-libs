import type { BlockConfiguration, BlockEditProps } from '@wordpress/blocks';
import type { WPElement } from '@wordpress/element';

type CardAttributes = { title: string };

const edit = (props: BlockEditProps<CardAttributes>): WPElement => {
  return wp.element.createElement('div', {}, props.attributes.title);
};

wp.blocks.registerBlockType('port-libs/card', { edit } satisfies BlockConfiguration<CardAttributes>);

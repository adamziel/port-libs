import { registerPlugin } from '@wordpress/plugins';

registerPlugin('acme-card-sidebar', {
    render: () => {
        return wp.element.createElement('p', {}, 'Legacy panel');
    },
);

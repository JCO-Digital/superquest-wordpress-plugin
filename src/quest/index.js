/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import Edit from './edit';
import icon from './icon';
import './store';

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
	// Rendered by render.php; nothing is saved to post content.
	save: () => null,
} );

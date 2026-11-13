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

// Loaded with the block's editor script, which the editor always loads, so
// the Button block's quest picker needs no build entry of its own.
import './button-popup';

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
	// Rendered by render.php; nothing is saved to post content.
	save: () => null,
} );

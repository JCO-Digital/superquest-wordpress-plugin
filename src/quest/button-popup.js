/**
 * A "SuperQuest popup" panel in the core Button block's sidebar.
 *
 * Picking a quest points the button at `#superquest-ID`, which the SuperQuest
 * loader opens as a popup. The button stays a core Button: it keeps the
 * theme's button styles and still works if the plugin is turned off.
 */

/**
 * WordPress dependencies
 */
import { InspectorControls } from '@wordpress/block-editor';
import {
	ComboboxControl,
	Notice,
	PanelBody,
	Spinner,
} from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { STORE_NAME } from './store';

const LINK_PREFIX = 'superquest';

/**
 * The link URL opening a quest's popup.
 *
 * @param {string} questId Quest ID.
 * @return {string} URL.
 */
export const linkUrl = ( questId ) =>
	`#${ LINK_PREFIX }-${ encodeURIComponent( questId ) }`;

/**
 * The quest a link URL opens, matching `Popup::link_quest_id()` in PHP: the
 * quest ID, an empty string for the bare `#superquest` (every popup quest),
 * or null for any other URL.
 *
 * @param {unknown} url Link URL.
 * @return {string|null} Quest ID.
 */
export const linkQuestId = ( url ) => {
	if ( typeof url !== 'string' ) {
		return null;
	}

	const hash = url.indexOf( '#' );
	if ( hash === -1 ) {
		return null;
	}

	const fragment = url.slice( hash + 1 );
	if ( fragment === LINK_PREFIX ) {
		return '';
	}

	const prefix = `${ LINK_PREFIX }-`;
	if ( ! fragment.startsWith( prefix ) ) {
		return null;
	}

	try {
		return decodeURIComponent( fragment.slice( prefix.length ) ) || null;
	} catch {
		return null;
	}
};

/**
 * The panel itself. Only mounted for the selected button, so the quest list
 * is fetched once a button is selected, not when the editor opens.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Button attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element} Element.
 */
function QuestPanel( { attributes, setAttributes } ) {
	const questId = linkQuestId( attributes.url );

	const { quests, error, isLoading } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			quests: store.getQuestsData()?.quests ?? [],
			error: store.getError(),
			isLoading:
				! store.hasFinishedResolution( 'getQuestsData' ) &&
				! store.getError(),
		};
	}, [] );

	const selected = quests.find( ( quest ) => quest.id === questId );

	// Inline quests would not open as a popup, so they are not offered.
	// Quests of unknown mode are, as on the plugin's Popup screen.
	const options = quests
		.filter(
			( quest ) => quest.launch_mode !== 'inline' || quest.id === questId
		)
		.map( ( quest ) => ( {
			value: quest.id,
			label: quest.title || quest.id,
		} ) );

	if ( questId && ! selected ) {
		options.push( { value: questId, label: questId } );
	}

	const onChange = ( id ) => {
		if ( ! id ) {
			// Only clear a link this panel owns.
			if ( questId !== null ) {
				setAttributes( { url: undefined } );
			}
			return;
		}

		// A popup opens over the page, so a new tab makes no sense.
		setAttributes( { url: linkUrl( id ), linkTarget: undefined } );
	};

	return (
		<PanelBody
			title={ __( 'SuperQuest popup', 'superquest' ) }
			initialOpen={ questId !== null }
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ isLoading ? (
				<Spinner />
			) : (
				<ComboboxControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Open quest', 'superquest' ) }
					value={ questId || null }
					options={ options }
					onChange={ onChange }
					placeholder={ __( 'Search quests…', 'superquest' ) }
					help={ __(
						'The button opens the quest as a popup. It only works on pages where the quest is inserted as a popup.',
						'superquest'
					) }
				/>
			) }

			{ questId === '' && (
				<p>
					{ __(
						'This button opens every popup quest on the page.',
						'superquest'
					) }
				</p>
			) }

			{ selected?.launch_mode === 'inline' && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This quest is set to inline mode in SuperQuest, so it will not open as a popup. Switch it to Popup in SuperQuest, or pick another quest.',
						'superquest'
					) }
				</Notice>
			) }

			{ questId && ! selected && ! isLoading && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This quest is not in the fetched quest list. Pick another one or refresh the list in a SuperQuest block.',
						'superquest'
					) }
				</Notice>
			) }
		</PanelBody>
	);
}

const withQuestPanel = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const { name, isSelected, attributes, setAttributes } = props;

		// Form buttons (`<button>`) have no link to set.
		if (
			name !== 'core/button' ||
			! isSelected ||
			attributes.tagName === 'button'
		) {
			return <BlockEdit { ...props } />;
		}

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<QuestPanel
						attributes={ attributes }
						setAttributes={ setAttributes }
					/>
				</InspectorControls>
			</>
		);
	},
	'withSuperQuestButtonPanel'
);

addFilter( 'editor.BlockEdit', 'superquest/button-popup', withQuestPanel );

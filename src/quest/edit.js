/**
 * WordPress dependencies
 */
import {
	BlockControls,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	ComboboxControl,
	ExternalLink,
	Notice,
	PanelBody,
	Placeholder,
	Spinner,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { humanTimeDiff } from '@wordpress/date';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import icon from './icon';
import { STORE_NAME } from './store';
import './editor.scss';

/**
 * Quest ID used by the inserter preview, so it renders without a request.
 */
const EXAMPLE_ID = '__example__';

const DASHBOARD_URL = 'https://dashboard.jquest.fi/#/dashboard';

/**
 * URL of a quest in the SuperQuest dashboard.
 *
 * @param {string} organizationId Organisation ID.
 * @param {string} questId        Quest ID.
 * @return {string} URL.
 */
const dashboardUrl = ( organizationId, questId ) =>
	`${ DASHBOARD_URL }/${ encodeURIComponent(
		organizationId
	) }/quests/${ encodeURIComponent( questId ) }`;

/**
 * Stands in for the quest the visitor will see, which is rendered by the
 * SuperQuest app and cannot be previewed in the editor.
 *
 * It is the editor's own placeholder, so a configured block sits in the
 * content the way every other unpreviewable block does rather than drawing
 * the eye with the SuperQuest colours.
 *
 * @param {Object} props          Component props.
 * @param {string} props.title    Quest title.
 * @param {Object} props.children Optional notice below the title.
 * @return {Element} Element.
 */
function QuestPreview( { title, children } ) {
	return (
		<Placeholder
			icon={ icon }
			label={ title }
			instructions={ __(
				'SuperQuest loads this quest when a visitor opens the page.',
				'superquest'
			) }
		>
			{ children }
		</Placeholder>
	);
}

/**
 * Editor view of the SuperQuest block.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element} Element.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { questId } = attributes;
	const isExample = questId === EXAMPLE_ID;
	const blockProps = useBlockProps( { className: 'superquest-quest' } );

	const [ isChoosing, setIsChoosing ] = useState( false );
	const { refreshQuests } = useDispatch( STORE_NAME );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const { data, error, isLoading, isRefreshing } = useSelect(
		( select ) => {
			if ( isExample ) {
				return {
					data: null,
					error: '',
					isLoading: false,
					isRefreshing: false,
				};
			}

			const store = select( STORE_NAME );
			return {
				data: store.getQuestsData(),
				error: store.getError(),
				isLoading:
					! store.hasFinishedResolution( 'getQuestsData' ) &&
					! store.getError(),
				isRefreshing: store.isRefreshing(),
			};
		},
		[ isExample ]
	);

	if ( isExample ) {
		return (
			<div { ...blockProps }>
				<QuestPreview
					title={ __( 'Customer satisfaction quiz', 'superquest' ) }
				/>
			</div>
		);
	}

	const organizationId = data?.organization_id ?? '';
	const quests = data?.quests ?? [];
	const options = quests.map( ( quest ) => ( {
		value: quest.id,
		label: quest.title || quest.id,
	} ) );
	const selected = quests.find( ( quest ) => quest.id === questId );
	const hasQuest = questId !== '';
	const isStale = hasQuest && data && ! selected;

	const onRefresh = async () => {
		try {
			const result = await refreshQuests();
			createSuccessNotice(
				sprintf(
					/* translators: %d: number of quests. */
					__( 'Quest list refreshed: %d quests.', 'superquest' ),
					result?.quests?.length ?? 0
				),
				{ type: 'snackbar' }
			);
		} catch ( refreshError ) {
			createErrorNotice( refreshError.message, { type: 'snackbar' } );
		}
	};

	const onChangeQuest = ( value ) => {
		setAttributes( { questId: value ?? '' } );
		setIsChoosing( false );
	};

	const refreshButton = (
		<Button
			variant="secondary"
			onClick={ onRefresh }
			isBusy={ isRefreshing }
			disabled={ isRefreshing }
		>
			{ isRefreshing
				? __( 'Refreshing quests…', 'superquest' )
				: __( 'Refresh quests', 'superquest' ) }
		</Button>
	);

	const questPicker = (
		<ComboboxControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ __( 'Quest', 'superquest' ) }
			value={ questId }
			options={ options }
			onChange={ onChangeQuest }
			placeholder={ __( 'Search quests…', 'superquest' ) }
		/>
	);

	/**
	 * Wraps the configuration states in the editor's own placeholder, so every
	 * control keeps its default colours.
	 *
	 * @param {Element} children Placeholder content.
	 * @return {Element} Element.
	 */
	const questPlaceholder = ( children ) => (
		<Placeholder
			icon={ icon }
			label={ __( 'SuperQuest', 'superquest' ) }
			instructions={ __(
				'Pick the quest this block should show.',
				'superquest'
			) }
		>
			{ children }
		</Placeholder>
	);

	let body;

	if ( isLoading ) {
		body = questPlaceholder( <Spinner /> );
	} else if ( error && ! data ) {
		body = questPlaceholder(
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	} else if ( ! organizationId ) {
		body = questPlaceholder(
			<>
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'No SuperQuest organisation is set for this site.',
						'superquest'
					) }
				</Notice>
				{ data?.can_manage && data?.settings_url ? (
					<Button variant="primary" href={ data.settings_url }>
						{ __( 'Open SuperQuest settings', 'superquest' ) }
					</Button>
				) : (
					<p className="superquest-hint">
						{ __(
							'Ask an administrator to set the Organisation ID in the SuperQuest settings.',
							'superquest'
						) }
					</p>
				) }
			</>
		);
	} else if ( hasQuest && ! isChoosing ) {
		body = (
			<QuestPreview
				title={ selected ? selected.title || questId : questId }
			>
				{ isStale && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'This quest is no longer in the fetched quest list. Pick another one or refresh the list.',
							'superquest'
						) }
					</Notice>
				) }
			</QuestPreview>
		);
	} else if ( quests.length === 0 ) {
		body = questPlaceholder(
			<>
				<p className="superquest-hint">
					{ data?.message
						? data.message
						: __(
								'No quests were found for the organisation.',
								'superquest'
							) }
				</p>
				{ refreshButton }
			</>
		);
	} else {
		body = questPlaceholder(
			<>
				<div className="superquest-picker">{ questPicker }</div>
				<div className="superquest-actions">
					{ refreshButton }
					{ hasQuest && (
						<Button
							variant="tertiary"
							onClick={ () => setIsChoosing( false ) }
						>
							{ __( 'Cancel', 'superquest' ) }
						</Button>
					) }
				</div>
			</>
		);
	}

	return (
		<>
			{ hasQuest && ! isChoosing && organizationId && (
				<BlockControls>
					<ToolbarGroup>
						<ToolbarButton onClick={ () => setIsChoosing( true ) }>
							{ __( 'Change quest', 'superquest' ) }
						</ToolbarButton>
					</ToolbarGroup>
				</BlockControls>
			) }

			<InspectorControls>
				<PanelBody title={ __( 'Quest', 'superquest' ) }>
					{ organizationId && quests.length > 0 && (
						<div className="superquest-inspector__field">
							{ questPicker }
						</div>
					) }
					<div className="superquest-inspector__field">
						{ refreshButton }
					</div>
					{ data?.updated > 0 && (
						<p className="superquest-inspector__meta">
							{ sprintf(
								/* translators: %s: human-readable time difference, e.g. "5 minutes ago". */
								__( 'Quest list fetched %s.', 'superquest' ),
								humanTimeDiff( data.updated * 1000 )
							) }
						</p>
					) }
					{ organizationId && hasQuest && (
						<ExternalLink
							href={ dashboardUrl( organizationId, questId ) }
						>
							{ __(
								'Edit in SuperQuest dashboard',
								'superquest'
							) }
						</ExternalLink>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>{ body }</div>
		</>
	);
}

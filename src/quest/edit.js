/**
 * WordPress dependencies
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	ComboboxControl,
	ExternalLink,
	Notice,
	PanelBody,
	Placeholder,
	Spinner,
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
 * The inserter preview.
 *
 * @param {Object} props            Component props.
 * @param {Object} props.blockProps Block wrapper props.
 * @return {Element} Element.
 */
function ExamplePlaceholder( { blockProps } ) {
	return (
		<div { ...blockProps }>
			<Placeholder
				icon={ icon }
				label={ __( 'SuperQuest', 'superquest' ) }
				className="superquest-placeholder"
			>
				<p className="superquest-placeholder__title">
					{ __( 'Customer satisfaction quiz', 'superquest' ) }
				</p>
			</Placeholder>
		</div>
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
		return <ExamplePlaceholder blockProps={ blockProps } />;
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

	let content;

	if ( isLoading ) {
		content = <Spinner />;
	} else if ( error && ! data ) {
		content = (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	} else if ( ! organizationId ) {
		content = (
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
					<p>
						{ __(
							'Ask an administrator to set the Organisation ID in the SuperQuest settings.',
							'superquest'
						) }
					</p>
				) }
			</>
		);
	} else if ( quests.length === 0 && ! hasQuest ) {
		content = (
			<>
				<p>
					{ __(
						'No quests were found for the organisation.',
						'superquest'
					) }
					{ data?.message ? ` ${ data.message }` : '' }
				</p>
				{ refreshButton }
			</>
		);
	} else if ( ! hasQuest || isChoosing ) {
		content = (
			<div className="superquest-placeholder__picker">
				{ questPicker }
				<div className="superquest-placeholder__actions">
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
			</div>
		);
	} else {
		content = (
			<>
				<p className="superquest-placeholder__title">
					{ selected ? selected.title || selected.id : questId }
				</p>
				{ isStale && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'This quest is no longer in the fetched quest list. Pick another one or refresh the list.',
							'superquest'
						) }
					</Notice>
				) }
				<div className="superquest-placeholder__actions">
					<Button
						variant="secondary"
						onClick={ () => setIsChoosing( true ) }
					>
						{ __( 'Change quest', 'superquest' ) }
					</Button>
					<ExternalLink
						href={ dashboardUrl( organizationId, questId ) }
					>
						{ __( 'Edit in SuperQuest dashboard', 'superquest' ) }
					</ExternalLink>
				</div>
			</>
		);
	}

	return (
		<>
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

			<div { ...blockProps }>
				<Placeholder
					icon={ icon }
					label={ __( 'SuperQuest', 'superquest' ) }
					className="superquest-placeholder"
				>
					{ content }
				</Placeholder>
			</div>
		</>
	);
}

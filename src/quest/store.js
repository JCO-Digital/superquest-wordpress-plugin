/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { createReduxStore, register } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

export const STORE_NAME = 'superquest/quests';

const QUESTS_PATH = '/superquest/v1/quests';
const REFRESH_PATH = '/superquest/v1/quests/refresh';

const DEFAULT_STATE = {
	data: null,
	error: '',
	refreshing: false,
};

/**
 * A readable message for a failed request.
 *
 * @param {unknown} error Thrown value.
 * @return {string} Message.
 */
const errorMessage = ( error ) =>
	error && typeof error.message === 'string' && error.message
		? error.message
		: __( 'The request failed.', 'superquest' );

const actions = {
	receiveQuests( data ) {
		return { type: 'RECEIVE_QUESTS', data };
	},
	setError( error ) {
		return { type: 'SET_ERROR', error };
	},
	setRefreshing( refreshing ) {
		return { type: 'SET_REFRESHING', refreshing };
	},
	/**
	 * Refetches the quest list from the SuperQuest API.
	 *
	 * @return {Function} Thunk resolving with the new data.
	 */
	refreshQuests() {
		return async ( { dispatch } ) => {
			dispatch.setRefreshing( true );
			try {
				const data = await apiFetch( {
					path: REFRESH_PATH,
					method: 'POST',
				} );
				dispatch.receiveQuests( data );
				return data;
			} catch ( error ) {
				throw new Error( errorMessage( error ) );
			} finally {
				dispatch.setRefreshing( false );
			}
		};
	},
};

/**
 * Store shared by every SuperQuest block in the editor: one request per
 * session, and a refresh from any block updates all of them.
 */
const store = createReduxStore( STORE_NAME, {
	reducer( state = DEFAULT_STATE, action ) {
		switch ( action.type ) {
			case 'RECEIVE_QUESTS':
				return { ...state, data: action.data, error: '' };
			case 'SET_ERROR':
				return { ...state, error: action.error };
			case 'SET_REFRESHING':
				return { ...state, refreshing: action.refreshing };
			default:
				return state;
		}
	},
	actions,
	selectors: {
		getQuestsData( state ) {
			return state.data;
		},
		getError( state ) {
			return state.error;
		},
		isRefreshing( state ) {
			return state.refreshing;
		},
	},
	resolvers: {
		getQuestsData() {
			return async ( { dispatch } ) => {
				try {
					const data = await apiFetch( { path: QUESTS_PATH } );
					dispatch.receiveQuests( data );
				} catch ( error ) {
					dispatch.setError( errorMessage( error ) );
				}
			};
		},
	},
} );

register( store );

export default store;

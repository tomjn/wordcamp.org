/**
 * WordPress dependencies
 */
import { withSelect } from '@wordpress/data';
import { Component, Fragment } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { WC_BLOCKS_STORE } from '../../data';
import { ScheduleGrid } from './schedule-grid';
import InspectorControls from './inspector-controls';
import { ICON } from './index';

const blockData = window.WordCampBlocks.sessions || {};

/**
 * Top-level component for the editing UI for the block.
 */
class ScheduleEdit extends Component {
	/**
	 * Render the block's editing UI.
	 *
	 * @return {Element}
	 */
	render() {
		const { attributes, entities } = this.props;

		return (
			<Fragment>
				<ScheduleGrid
					icon={ ICON }
					attributes={ attributes }
					entities={ entities }
				/>

				<InspectorControls />
			</Fragment>
		);
	}
}

const scheduleSelect = ( select ) => {
	const { getEntities, getSiteSettings } = select( WC_BLOCKS_STORE );

	const sessionArgs = {
		_embed: true,
	};

	const entities = {
		sessions : getEntities( 'postType', 'wcb_session', sessionArgs ),
		settings : getSiteSettings(),
	};

	return { blockData, entities };
};

export const Edit = withSelect( scheduleSelect )( ScheduleEdit );


//import HierarchicalTermSelector from '@wordpress/editor'; doesn't work
//
//function customizeTaxonomySelector( OriginalComponent ) {
//    return function( props ) {
//
//        if ( 'my_taxonomy' !== props.slug ) {
//        	return <OriginalComponent { ...props } />;
//        }
//
//        return <HierarchicalTermSelector { ...props } />;
//    };
//}
//wp.hooks.addFilter( 'editor.PostTaxonomyType', 'my-custom-plugin', customizeTaxonomySelector );
// might not be necessary after https://github.com/WordPress/gutenberg/issues/13816 fixed, but doesn't run there?

// another approach would be to just ignore tha parent level directories, and only show the bottom level
// or better yet, flatten them all into the same level, and then ignore the ones that don't have any terms directly assigned to them (which should throw out the parents in most cases)
	// ugh, probably have to do this, since can't overwrite UI in Gutenberg --
	// see https://github.com/WordPress/gutenberg/issues/13816#issuecomment-532885577 and https://github.com/WordPress/gutenberg/issues/17476

// another would be to trick G into thinking it's hierarchical by overwriting the rest api value but that's not elegant. could restrict to context=edit but still

// once this is done, it should probably live in `inspector-controls.js`, but ran into some issues there that didn't run into here, so putting that off until it's working here.
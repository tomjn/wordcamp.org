<?php
/**
 * Plugin Name: WordCamp.org Post Types
 * Plugin Description: Sessions, Speakers, Sponsors and much more.
 */

require 'inc/back-compat.php';
require_once 'inc/favorite-schedule-shortcode.php';
require_once 'inc/privacy.php';
require_once 'inc/deprecated.php';

class WordCamp_Post_Types_Plugin {
	protected $wcpt_permalinks;

	/**
	 * Fired when plugin file is loaded.
	 */
	public function __construct() {
		$this->wcpt_permalinks = array();

		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'after_theme_setup', array( $this, 'add_image_sizes' ) );

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_print_styles', array( $this, 'admin_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'admin_print_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );

		add_action( 'save_post', array( $this, 'save_post_speaker' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_post_session' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_post_organizer' ), 10, 2);
		add_action( 'save_post', array( $this, 'save_post_sponsor' ), 10, 2);

		add_filter( 'manage_wcb_speaker_posts_columns', array( $this, 'manage_post_types_columns' ) );
		add_filter( 'manage_wcb_session_posts_columns', array( $this, 'manage_post_types_columns' ) );
		add_filter( 'manage_wcb_sponsor_posts_columns', array( $this, 'manage_post_types_columns' ) );
		add_filter( 'manage_wcb_organizer_posts_columns', array( $this, 'manage_post_types_columns' ) );
		add_filter( 'manage_edit-wcb_session_sortable_columns', array( $this, 'manage_sortable_columns' ) );
		add_filter( 'display_post_states', array( $this, 'display_post_states' ) );

		add_action( 'manage_posts_custom_column', array( $this, 'manage_post_types_columns_output' ), 10, 2 );

		add_action( 'widgets_init', array( $this, 'register_widgets' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		add_shortcode( 'schedule', array( $this, 'shortcode_schedule' ) );

		add_filter( 'body_class', array( $this, 'session_category_slugs_to_body_tag' ) );

		add_filter( 'the_content', array( $this, 'add_avatar_to_speaker_posts' ) );
		add_filter( 'the_content', array( $this, 'add_speaker_info_to_session_posts' ) );
		add_filter( 'the_content', array( $this, 'add_slides_info_to_session_posts' ) );
		add_filter( 'the_content', array( $this, 'add_video_info_to_session_posts' ) );
		add_filter( 'the_content', array( $this, 'add_session_categories_to_session_posts' ) );
		add_filter( 'the_content', array( $this, 'add_session_info_to_speaker_posts' ) );

		add_filter( 'dashboard_glance_items', array( $this, 'glance_items' ) );
		add_filter( 'option_default_comment_status', array( $this, 'default_comment_ping_status' ) );
		add_filter( 'option_default_ping_status', array( $this, 'default_comment_ping_status' ) );

		add_action( 'init', array( $this, 'rest_init' ), 9 );
	}

	/**
	 * Run and setup hooks for wc_post_types.
	 */
	public function init() {
		do_action( 'wcpt_back_compat_init' );

		if ( is_user_logged_in() ) {
			register_setting(
				'wcb_sponsor_options',
				'wcb_sponsor_level_order',
				array(
					'sanitize_callback' => array( $this, 'validate_sponsor_options' ),
					'show_in_rest'      => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'integer',
							),
						),
					),
				)
			);
		}
	}

	/**
	 * Runs during admin_init.
	 */
	public function admin_init() {
		add_action( 'pre_get_posts', array( $this, 'admin_pre_get_posts' ) );
	}

	/**
	 * Runs during init, because rest_api_init is too late.
	 */
	public function rest_init() {
		require_once 'inc/rest-api.php';
	}

	/**
	 * Runs during admin_menu
	 */
	public function admin_menu() {
		$page = add_submenu_page( 'edit.php?post_type=wcb_sponsor', __( 'Order Sponsor Levels', 'wordcamporg' ), __( 'Order Sponsor Levels', 'wordcamporg' ), 'edit_posts', 'sponsor_levels', array( $this, 'render_order_sponsor_levels' ) );

		add_action( "admin_print_scripts-$page", array( $this, 'enqueue_order_sponsor_levels_scripts' ) );
	}

	/**
	 * Add custom image sizes
	 */
	public function add_image_sizes() {
		add_image_size( 'wcb-sponsor-logo-horizontal-2x', 600, 220, false );
	}

	/**
	 * Enqueues scripts and styles for the render_order_sponsors_level admin page.
	 */
	public function enqueue_order_sponsor_levels_scripts() {
		wp_enqueue_script( 'wcb-sponsor-order', plugins_url( '/js/order-sponsor-levels.js', __FILE__ ), array( 'jquery-ui-sortable' ), '20110212', true );
		wp_enqueue_style( 'wcb-sponsor-order', plugins_url( '/css/order-sponsor-levels.css', __FILE__ ), array(), '20110212' );
	}

	/**
	 * Renders the Order Sponsor Levels admin page.
	 */
	public function render_order_sponsor_levels() {
		$levels = $this->get_sponsor_levels();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Order Sponsor Levels', 'wordcamporg' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'wcb_sponsor_options' ); ?>
				<div class="description sponsor-order-instructions">
					<?php esc_html_e( 'Change the order of sponsor levels are displayed in the sponsors page template.', 'wordcamporg' ); ?>
				</div>
				<ul class="sponsor-order">
				<?php foreach ( $levels as $term ) : ?>
					<li class="level">
						<input type="hidden" class="level-id" name="wcb_sponsor_level_order[]" value="<?php echo esc_attr( $term->term_id ); ?>" />
						<?php echo esc_html( $term->name ); ?>
					</li>
				<?php endforeach; ?>
				</ul>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Options', 'wordcamporg' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Runs when settings are updated for the sponsor level order page.
	 */
	public function validate_sponsor_options( $input ) {
		if ( ! is_array( $input ) ) {
			$input = null;
		} else {
			foreach ( $input as $key => $value ) {
				$input[ $key ] = (int) $input[ $key ];
			}
			$input = array_values( $input );
		}

		return $input;
	}

	/**
	 * Returns the sponsor level terms in set order.
	 */
	public function get_sponsor_levels() {
		$option        = get_option( 'wcb_sponsor_level_order' );
		$term_objects  = get_terms( 'wcb_sponsor_level', array( 'get' => 'all' ) );
		$terms         = array();
		$ordered_terms = array();

		foreach ( $term_objects as $term ) {
			$terms[ $term->term_id ] = $term;
		}

		if ( empty( $option ) ) {
			$option = array();
		}

		foreach ( $option as $term_id ) {
			if ( isset( $terms[ $term_id ] ) ) {
				$ordered_terms[] = $terms[ $term_id ];
				unset( $terms[ $term_id ] );
			}
		}

		return array_merge( $ordered_terms, array_values( $terms ) );
	}

	/**
	 * Runs during pre_get_posts in admin.
	 *
	 * @param WP_Query $query
	 */
	public function admin_pre_get_posts( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$current_screen = get_current_screen();

		// Order by session time.
		if ( 'edit-wcb_session' == $current_screen->id && $query->get( 'orderby' ) == '_wcpt_session_time' ) {
			$query->set( 'meta_key', '_wcpt_session_time' );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Enqueues Scripts required for displaying settings.
	 */
	public function admin_enqueue_scripts() {
		global $post_type;

		// Register.
		wp_register_script(
			'wcb-spon', // Avoid "sponsor" since that's a trigger word for ad blockers.
			plugins_url( 'js/wcb-spon.js', __FILE__ ),
			array( 'jquery', 'backbone', 'media-views' ),
			1,
			true
		);
		wp_localize_script(
			'wcb-spon',
			'wcbSponsors',
			array(
				'l10n'  => array(
					'modalTitle' => __( 'Sponsor Agreement', 'wordcamporg' ),
				),
				'modal' => array(
					'allowedTypes' => array( 'image', 'application/pdf' ),
				),
			)
		);

		// Enqueues scripts and styles for session admin page.
		if ( 'wcb_session' == $post_type ) {
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_style( 'jquery-ui' );
			wp_enqueue_style( 'wp-datepicker-skins' );
		}

		// Enqueues scripts and styles for sponsors admin page.
		if ( 'wcb_sponsor' == $post_type ) {
			wp_enqueue_script( 'wcb-spon' );
		}
	}

	/**
	 * Print our JavaScript
	 */
	public function admin_print_scripts() {
		global $post_type;

		// DatePicker for Session posts.
		if ( 'wcb_session' == $post_type ) :
			?>

			<script type="text/javascript">
				jQuery( document ).ready( function( $ ) {
					$( '#wcpt-session-date' ).datepicker( {
						dateFormat:  'yy-mm-dd',
						changeMonth: true,
						changeYear:  true
					} );
				} );
			</script>

			<?php
		endif;
	}

	/**
	 * Enqueue scripts.
	 */
	public function wp_enqueue_scripts() {
		wp_enqueue_style( 'wcb_shortcodes', plugins_url( 'css/shortcodes.css', __FILE__ ), array(), 3 );
	}

	/**
	 * Runs during admin_print_styles, does some CSS things.
	 *
	 * @todo add an icon for wcb_organizer
	 * @uses get_current_screen()
	 * @uses wp_enqueue_style()
	 */
	public function admin_css() {
		$screen = get_current_screen();

		switch ( $screen->id ) {
			case 'edit-wcb_organizer':
			case 'edit-wcb_speaker':
			case 'edit-wcb_sponsor':
			case 'edit-wcb_session':
			case 'wcb_sponsor':
			case 'dashboard':
				wp_enqueue_style( 'wcpt-admin', plugins_url( '/css/admin.css', __FILE__ ), array(), 1 );
				break;
			default:
		}
	}

	/**
	 * The [schedule] shortcode callback (experimental)
	 *
	 * @todo implement date arg
	 * @todo implement anchor for session_link
	 * @todo maybe simplify $attr['custom']
	 * @todo cleanup
	 */
	public function shortcode_schedule( $attr, $content ) {
		$this->enqueue_schedule_shortcode_dependencies();

		$attr                        = preprocess_schedule_attributes( $attr );
		$tracks                      = get_schedule_tracks( $attr['tracks'] );
		$tracks_explicitly_specified = 'all' !== $attr['tracks'];
		$sessions                    = get_schedule_sessions( $attr['date'], $tracks_explicitly_specified, $tracks );
		$columns                     = get_schedule_columns( $tracks, $sessions, $tracks_explicitly_specified );

		$html  = '<table class="wcpt-schedule" border="0">';
		$html .= '<thead>';
		$html .= '<tr>';

		// Table headings.
		$html .= '<th class="wcpt-col-time">' . esc_html__( 'Time', 'wordcamporg' ) . '</th>';
		foreach ( $columns as $term_id ) {
			$track = get_term( $term_id, 'wcb_track' );
			$html .= sprintf(
				'<th class="wcpt-col-track"> <span class="wcpt-track-name">%s</span> <span class="wcpt-track-description">%s</span> </th>',
				isset( $track->term_id ) ? esc_html( $track->name ) : '',
				isset( $track->term_id ) ? esc_html( $track->description ) : ''
			);
		}

		$html .= '</tr>';
		$html .= '</thead>';

		$html .= '<tbody>';

		$time_format = get_option( 'time_format', 'g:i a' );

		foreach ( $sessions as $time => $entry ) {

			$skip_next = 0;
			$colspan   = 0;

			$columns_html = '';
			foreach ( $columns as $key => $term_id ) {

				// Allow the below to skip some items if needed.
				if ( $skip_next > 0 ) {
					$skip_next--;
					continue;
				}

				// For empty items print empty cells.
				if ( empty( $entry[ $term_id ] ) ) {
					$columns_html .= '<td class="wcpt-session-empty"></td>';
					continue;
				}

				// For custom labels print label and continue.
				if ( is_string( $entry[ $term_id ] ) ) {
					$columns_html .= sprintf( '<td colspan="%d" class="wcpt-session-custom">%s</td>', count( $columns ), esc_html( $entry[ $term_id ] ) );
					break;
				}

				// Gather relevant data about the session.
				$colspan              = 1;
				$classes              = array();
				$session              = get_post( $entry[ $term_id ] );
				$session_title        = apply_filters( 'the_title', $session->post_title );
				$session_tracks       = get_the_terms( $session->ID, 'wcb_track' );
				$session_categories   = get_the_terms( $session->ID, 'wcb_session_category' );
				$session_track_titles = is_array( $session_tracks ) ? implode( ', ', wp_list_pluck( $session_tracks, 'name' ) ) : '';
				$session_type         = get_post_meta( $session->ID, '_wcpt_session_type', true );

				if ( ! in_array( $session_type, array( 'session', 'custom' ) ) ) {
					$session_type = 'session';
				}

				// Fetch speakers associated with this session.
				$speakers     = array();
				$speakers_ids = array_map( 'absint', (array) get_post_meta( $session->ID, '_wcpt_speaker_id' ) );

				if ( ! empty( $speakers_ids ) ) {
					$speakers = get_posts( array(
						'post_type'      => 'wcb_speaker',
						'posts_per_page' => -1,
						'post__in'       => $speakers_ids,
					) );
				}

				// Add CSS classes to help with custom styles.
				foreach ( $speakers as $speaker ) {
					$classes[] = 'wcb-speaker-' . $speaker->post_name;
				}

				if ( is_array( $session_tracks ) ) {
					foreach ( $session_tracks as $session_track ) {
						$classes[] = 'wcb-track-' . $session_track->slug;
					}
				}

				if ( is_array( $session_categories ) ) {
					foreach ( $session_categories as $session_category ) {
						$classes[] = 'wcb-session-category-' . $session_category->slug;
					}
				}

				$classes[] = 'wcpt-session-type-' . $session_type;
				$classes[] = 'wcb-session-' . $session->post_name;

				$content = '<div class="wcb-session-cell-content">';

				// Determine the session title.
				if ( 'permalink' == $attr['session_link'] && 'session' == $session_type ) {
					$session_title_html = sprintf( '<a class="wcpt-session-title" href="%s">%s</a>', esc_url( get_permalink( $session->ID ) ), $session_title );
				} elseif ( 'anchor' == $attr['session_link'] && 'session' == $session_type ) {
					$session_title_html = sprintf( '<a class="wcpt-session-title" href="%s">%s</a>', esc_url( $this->get_wcpt_anchor_permalink( $session->ID ) ), $session_title );
				} else {
					$session_title_html = sprintf( '<span class="wcpt-session-title">%s</span>', $session_title );
				}

				$content .= $session_title_html;

				$speakers_names = array();
				foreach ( $speakers as $speaker ) {
					$speaker_name = apply_filters( 'the_title', $speaker->post_title );

					if ( 'anchor' == $attr['speaker_link'] ) {
						// speakers/#wcorg-speaker-slug.
						$speaker_permalink = $this->get_wcpt_anchor_permalink( $speaker->ID );
					} elseif ( 'wporg' == $attr['speaker_link'] ) {
						// profiles.wordpress.org/user.
						$speaker_permalink = $this->get_speaker_wporg_permalink( $speaker->ID );
					} elseif ( 'permalink' == $attr['speaker_link'] ) {
						// year.city.wordcamp.org/speakers/slug.
						$speaker_permalink = get_permalink( $speaker->ID );
					}

					if ( ! empty( $speaker_permalink ) ) {
						$speaker_name = sprintf( '<a href="%s">%s</a>', esc_url( $speaker_permalink ), esc_html( $speaker_name ) );
					}

					$speakers_names[] = $speaker_name;
				}

				// Add speakers names to the output string.
				if ( count( $speakers_names ) ) {
					$content .= sprintf( ' <span class="wcpt-session-speakers">%s</span>', implode( ', ', $speakers_names ) );
				}

				// End of cell-content.
				$content .= '</div>';

				// Favourite session star-icon.
				if ( 'session' == $session_type ) {
					$content .= '<div class="wcb-session-favourite-icon">';
					$content .= '<a href="#" role="button" class="fav-session-button" aria-pressed="false"><span class="screen-reader-text">';
					$content .= sprintf( esc_html__( 'Favorite session: %s', 'wordcamporg' ), $session_title );
					$content .= '</span><span class="dashicons dashicons-star-filled"></span></a></div>';
				}

				$columns_clone = $columns;

				// If the next element in the table is the same as the current one, use colspan.
				if ( key( array_slice( $columns, -1, 1, true ) ) != $key ) {
					foreach ( $columns_clone as $clonekey => $clonevalue ) {
						if ( $clonekey == $key ) {
							continue;
						}

						if ( ! empty( $entry[ $clonevalue ] ) && $entry[ $clonevalue ] == $session->ID ) {
							$colspan++;
							$skip_next++;
						} else {
							break;
						}
					}
				}

				$columns_html .= sprintf( '<td colspan="%d" class="%s" data-track-title="%s" data-session-id="%s">%s</td>', $colspan, esc_attr( implode( ' ', $classes ) ), $session_track_titles, esc_attr( $session->ID ), $content );
			}

			$global_session      = count( $columns ) == $colspan ? ' global-session' : '';
			$global_session_slug = $global_session ? ' ' . sanitize_html_class( sanitize_title_with_dashes( $session->post_title ) ) : '';

			$html .= sprintf( '<tr class="%s">', sanitize_html_class( 'wcpt-time-' . date( $time_format, $time ) ) . $global_session . $global_session_slug );
			$html .= sprintf( '<td class="wcpt-time">%s</td>', str_replace( ' ', '&nbsp;', esc_html( date( $time_format, $time ) ) ) );
			$html .= $columns_html;
			$html .= '</tr>';
		}

		$html .= '</tbody>';
		$html .= '</table>';
		$html .= $this->fav_session_share_form();
		return $html;
	}

	/**
	 * Enqueue style and scripts needed for [schedule] shortcode.
	 */
	public function enqueue_schedule_shortcode_dependencies() {
		wp_enqueue_style( 'dashicons' );

		wp_enqueue_script(
			'favourite-sessions',
			plugin_dir_url( __FILE__ ) . 'js/favourite-sessions.js',
			array( 'jquery' ),
			filemtime( plugin_dir_path( __FILE__ ) . 'js/favourite-sessions.js' ),
			true
		);

		wp_localize_script(
			'favourite-sessions',
			'favSessionsPhpObject',
			array(
				'root' => esc_url_raw( rest_url() ),
				'i18n' => array(
					'reqTimeOut'           => esc_html__( 'Sorry, the email request timed out.', 'wordcamporg' ),
					'otherError'           => esc_html__( 'Sorry, the email request failed.',    'wordcamporg' ),
					'overwriteFavSessions' => esc_html__( 'You already have some sessions saved. Would you like to overwrite those with the shared sessions that you are viewing?', 'wordcamporg' ),
					'buttonDisabledAlert'  => esc_html__( 'Interaction with favorite sessions disabled in share sessions view. Please click on schedule menu link to pick sessions.', 'wordcamporg' ),
					'buttonDisabledNote'   => esc_html__( 'Button disabled.', 'wordcamporg' ),
				),
			)
		);
	}

	/**
	 * Return HTML code for email form used to send/share favourite sessions over email.
	 *
	 * Both form and button/link to show/hide the form can be styled using classes email-form
	 * and show-email-form, respectively.
	 *
	 * @return string HTML code that represents the form to send emails and a link to show and hide it.
	 */
	public function fav_session_share_form() {
		static $share_form_count = 0;

		// Skip share form if it was already added to document.
		if ( 0 !== $share_form_count ) {
			return '';
		}

		ob_start();
		?>

		<div class="email-form fav-session-email-form-hide">
			<!-- Tab links -->
			<div class="fav-session-share-tab">
				<?php if ( ! email_fav_sessions_disabled() ) : ?>
					<div class="fav-session-tablinks" id="fav-session-btn-email">
						<?php esc_html_e( 'Email', 'wordcamporg' ); ?>
					</div>
				<?php endif; ?>

				<div class="fav-session-tablinks" id="fav-session-btn-link">
					<?php esc_html_e( 'Link', 'wordcamporg' ); ?>
				</div>

				<div class="fav-session-tablinks" id="fav-session-btn-print">
					<?php esc_html_e( 'Print', 'wordcamporg' ); ?>
				</div>
			</div>

			<!-- Tab content -->
			<?php if ( ! email_fav_sessions_disabled() ) : ?>
				<div id="fav-session-tab-email" class="fav-session-share-tabcontent">
					<div id="fav-session-email-form">
						<?php esc_html_e( 'Send me my favorite sessions:', 'wordcamporg' ); ?>

						<form id="fav-sessions-form">
							<input type="text" name="email_address" id="fav-sessions-email-address" placeholder="me@protonmail.com" />
							<input type="submit" value="<?php esc_attr_e( 'Send', 'wordcamporg' ); ?>" />
						</form>
					</div>

					<div class="fav-session-email-wait-spinner"></div>
					<div class="fav-session-email-result"></div>
				</div>
			<?php endif; ?>

			<div id="fav-session-tab-link" class="fav-session-share-tabcontent">
				<?php esc_html_e( 'Shareable link:', 'wordcamporg' ); ?><br />
				<a id="fav-sessions-link" href=""></a>
			</div>

			<div id="fav-session-tab-print" class="fav-session-share-tabcontent">
				<button id="fav-session-print">
					<?php esc_html_e( 'Print favorite sessions', 'wordcamporg' ); ?>
				</button>
			</div>
		</div>

		<a class="show-email-form" href="javascript:">
			<span class="dashicons dashicons-star-filled"></span>
			<span class="dashicons dashicons-email-alt"></span>
		</a>

		<?php
		$share_form = ob_get_clean();

		$share_form_count++;

		return $share_form;
	}

	/**
	 * Returns a speaker's WordPress.org profile url (if username set)
	 *
	 * @param int $speaker_id int The speaker's post id.
	 *
	 * @return NULL|string
	 */
	public function get_speaker_wporg_permalink( $speaker_id ) {
		$post = get_post( $speaker_id );
		if ( 'wcb_speaker' != $post->post_type  || 'publish' != $post->post_status ) {
			return null;
		}

		$wporg_user_id = get_post_meta( $speaker_id, '_wcpt_user_id', true );
		if ( ! $wporg_user_id ) {
			return null;
		}

		$user = get_user_by( 'id', $wporg_user_id );
		if ( ! $user ) {
			return null;
		}

		$permalink = sprintf( 'http://profiles.wordpress.org/%s', strtolower( $user->user_nicename ) );
		return esc_url_raw( $permalink );
	}

	/**
	 * Returns an anchor permalink for a Speaker or Session.
	 *
	 * Any page with the Speakers or Sessions block will contain IDs that can be used as anchors. If the current
	 * page contains the corresponding block, we'll assume the user wants to link there. Otherwise, we'll attempt
	 * to find another page that contains the block.
	 *
	 * Note: if `content_blocks` skip feature flag is set, this site still uses the shortcodes, and we search for
	 * shortcode content instead.
	 *
	 * @param int $target_id The speaker/session's post ID.
	 *
	 * @return string
	 */
	public function get_wcpt_anchor_permalink( $target_id ) {
		global $post;
		$anchor_target = get_post( $target_id );

		if ( 'publish' !== $anchor_target->post_status ) {
			return '';
		}

		switch ( $anchor_target->post_type ) {
			case 'wcb_speaker':
				$current_post_has_target = wcorg_skip_feature( 'content_blocks' ) ?
					has_shortcode( $post->post_content, 'speakers' ) :
					has_block( 'wordcamp/speakers', $post->post_content );

				$permalink = $current_post_has_target ? get_permalink( $post->id ) : $this->get_wcpt_permalink( 'speakers' );
				$anchor_id = $anchor_target->post_name;
				break;

			case 'wcb_session':
				$current_post_has_target = wcorg_skip_feature( 'content_blocks' ) ?
					has_shortcode( $post->post_content, 'sessions' ) :
					has_block( 'wordcamp/sessions', $post->post_content );

				$permalink = $current_post_has_target ? get_permalink( $post->id ) : $this->get_wcpt_permalink( 'sessions' );
				$anchor_id = $anchor_target->ID;
				break;

			default:
				$permalink = false;
				$anchor_id = false;
				break;
		}

		if ( ! $permalink ) {
			return '';
		}

		return sprintf(
			'%s#wcorg-%s-%s',
			$permalink,
			str_replace( 'wcb_', '', $anchor_target->post_type ),
			sanitize_html_class( $anchor_id )
		);
	}

	/**
	 * Returns the page permalink for speakers, sessions, or organizers.
	 *
	 * Fetches for a page with the Speakers, Sessions, or Organizers block and returns the permalink of the oldest
	 * page on the site (which should be the generated site content).
	 *
	 * Note: if `content_blocks` skip feature flag is set, this site still uses the shortcodes, and we search for
	 * shortcode content instead.
	 *
	 * @param string $type
	 *
	 * @return false | string
	 */
	public function get_wcpt_permalink( $type ) {
		if ( ! in_array( $type, array( 'speakers', 'sessions', 'organizers' ), true ) ) {
			return false;
		}

		/*
		 * The [schedule] shortcode can call this for each session and speaker, so cache the result to avoid
		 * dozens of SQL queries.
		 */
		if ( isset( $this->wcpt_permalinks[ $type ] ) ) {
			return $this->wcpt_permalinks[ $type ];
		}

		$this->wcpt_permalinks[ $type ] = false;

		$wcpt_post = get_posts( array(
			'post_type'      => array( 'page' ),
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'asc',
			's'              => wcorg_skip_feature( 'content_blocks' ) ? "[{$type}" : "<!-- wp:wordcamp/{$type}",
			'posts_per_page' => 1,
		) );

		if ( ! empty( $wcpt_post ) ) {
			$this->wcpt_permalinks[ $type ] = get_permalink( $wcpt_post[0] );
		}

		return $this->wcpt_permalinks[ $type ];
	}

	/**
	 * Determine if the current loop is just a single page, or a loop of posts within a page
	 *
	 * For example, this helps to target a single wcb_speaker post vs a page containing the [speakers] shortcode,
	 * which loops through wcb_speaker posts. Using functions like is_single() don't work, because they reference
	 * the main query instead of the $speakers query.
	 *
	 * @param string $post_type
	 *
	 * @return bool
	 */
	protected function is_single_cpt_post( $post_type ) {
		global $wp_query;

		return isset( $wp_query->query[ $post_type ] ) && $post_type == $wp_query->query['post_type'];
	}

	/**
	 * Add the speaker's avatar to their post
	 *
	 * We don't enable it for sites that were created before it was committed, because it may need custom CSS
	 * to look good with their custom design, but we allow older sites to opt-in.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function add_avatar_to_speaker_posts( $content ) {
		global $post;
		$enabled_site_ids = apply_filters( 'wcpt_speaker_post_avatar_enabled_site_ids', array( 364 ) );    // 2014.sf

		if ( ! $this->is_single_cpt_post( 'wcb_speaker') ) {
			return $content;
		}

		$site_id = get_current_blog_id();
		if ( $site_id <= apply_filters( 'wcpt_speaker_post_avatar_min_site_id', 463 ) && ! in_array( $site_id, $enabled_site_ids ) ) {
			return $content;
		}

		$avatar = get_avatar( get_post_meta( $post->ID, '_wcb_speaker_email', true ) );
		return '<div class="speaker-avatar">' . $avatar . '</div>' . $content;
	}

	/**
	 * Add speaker information to Session posts
	 *
	 * We don't enable it for sites that were created before it was committed, because some will have already
	 * crafted the bio to include this content, so duplicating it would look wrong, but we still allow older
	 * sites to opt-in.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function add_speaker_info_to_session_posts( $content ) {
		global $post;
		$enabled_site_ids = apply_filters( 'wcpt_session_post_speaker_info_enabled_site_ids', array( 364 ) );    // 2014.sf

		if ( ! $this->is_single_cpt_post( 'wcb_session') ) {
			return $content;
		}

		$site_id = get_current_blog_id();
		if ( $site_id <= apply_filters( 'wcpt_session_post_speaker_info_min_site_id', 463 ) && ! in_array( $site_id, $enabled_site_ids ) ) {
			return $content;
		}

		$speaker_ids = (array) get_post_meta( $post->ID, '_wcpt_speaker_id' );

		if ( empty( $speaker_ids ) ) {
			return $content;
		}

		$speaker_args = array(
			'post_type'      => 'wcb_speaker',
			'posts_per_page' => -1,
			'post__in'       => $speaker_ids,
			'orderby'        => 'title',
			'order'          => 'asc',
		);

		$speakers = new WP_Query( $speaker_args );

		if ( ! $speakers->have_posts() ) {
			return $content;
		}

		$speakers_html = sprintf(
			'<h2 class="session-speakers">%s</h2>',
			_n(
				'Speaker',
				'Speakers',
				$speakers->post_count,
				'wordcamporg'
			)
		);

		$speakers_html .= '<ul id="session-speaker-names">';
		while ( $speakers->have_posts() ) {
			$speakers->the_post();
			$speakers_html .= sprintf( '<li><a href="%s">%s</a></li>', get_the_permalink(), get_the_title() );
		}
		$speakers_html .= '</ul>';

		wp_reset_postdata();

		return $content . $speakers_html;
	}

	/**
	 * Add Slides link to Session posts
	 *
	 * We don't enable it for sites that were created before it was committed, because some will have already
	 * crafted the session to include this content, so duplicating it would look wrong, but we still allow older
	 * sites to opt-in.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function add_slides_info_to_session_posts( $content ) {
		global $post;

		$enabled_site_ids = apply_filters(
			'wcpt_session_post_slides_info_enabled_site_ids',
			array(
				206,  // testing.wordcamp.org.
				648,  // 2016.asheville.
				651,  // 2016.kansascity.
				623,  // 2016.tampa.
			)
		);

		if ( ! $this->is_single_cpt_post( 'wcb_session' ) ) {
			return $content;
		}

		$site_id = get_current_blog_id();

		if ( $site_id <= apply_filters( 'wcpt_session_post_slides_info_min_site_id', 699 ) && ! in_array( $site_id, $enabled_site_ids ) ) {
			return $content;
		}

		$session_slides = get_post_meta( $post->ID, '_wcpt_session_slides', true );

		if ( empty( $session_slides ) ) {
			return $content;
		}

		$session_slides_html  = '<div class="session-video">';
		$session_slides_html .= sprintf( __( '<a href="%s" target="_blank">View Session Slides</a>', 'wordcamporg' ), esc_url( $session_slides ) );
		$session_slides_html .= '</div>';

		return $content . $session_slides_html;
	}

	/**
	 * Add Video link to Session posts
	 *
	 * We don't enable it for sites that were created before it was committed, because some will have already
	 * crafted the session to include this content, so duplicating it would look wrong, but we still allow older
	 * sites to opt-in.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function add_video_info_to_session_posts( $content ) {
		global $post;

		$enabled_site_ids = apply_filters(
			'wcpt_session_post_video_info_enabled_site_ids',
			array(
				206,  // testing.wordcamp.org .
				648,  // 2016.asheville .
				623,  // 2016.tampa .
			)
		);

		if ( ! $this->is_single_cpt_post( 'wcb_session' ) ) {
			return $content;
		}

		$site_id = get_current_blog_id();

		if ( $site_id <= apply_filters( 'wcpt_session_post_video_info_min_site_id', 699 ) && ! in_array( $site_id, $enabled_site_ids ) ) {
			return $content;
		}

		$session_video = get_post_meta( $post->ID, '_wcpt_session_video', true );

		if ( empty( $session_video ) ) {
			return $content;
		}

		$session_video_html  = '<div class="session-video">';
		$session_video_html .= sprintf( __( '<a href="%s" target="_blank">View Session Video</a>', 'wordcamporg' ), esc_url( $session_video ) );
		$session_video_html .= '</div>';

		return $content . $session_video_html;
	}

	/**
	 * Append a session's categories to its post content.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function add_session_categories_to_session_posts( $content ) {
		global $post;

		if ( ! $this->is_single_cpt_post( 'wcb_session' ) ) {
			return $content;
		}

		$session_categories_html = '';

		$session_categories_list = get_the_term_list( $post->ID, 'wcb_session_category', '', _x( ', ', 'Used between list items, there is a space after the comma.', 'wordcamporg' ) );
		if ( $session_categories_list ) {
			$session_categories_html = sprintf(
				'<span class="session-categories-links"><span class="screen-reader-text">%1$s</span> %2$s</span>',
				esc_html_x( 'Categories', 'Used before session category names.', 'wordcamporg' ),
				wp_kses_post( $session_categories_list )
			);
		}

		return $content . $session_categories_html;
	}

	/**
	 * Add the sessions's category slugs to the body tag.
	 *
	 * @param array $body_classes
	 *
	 * @return array
	 */
	public function session_category_slugs_to_body_tag( $body_classes ) {
		if ( 'wcb_session' === get_post_type() ) {
			$session_categories = get_the_terms( get_post(), 'wcb_session_category' );

			if ( is_array( $session_categories ) ) {
				foreach ( $session_categories as $session_category ) {
					$body_classes[] = 'wcb_session_category-' . $session_category->slug;
				}
			}
		}

		return $body_classes;
	}

	/**
	 * Add session information to Speaker posts
	 *
	 * We don't enable it for sites that were created before it was committed, because some will have already
	 * crafted the bio to include this content, so duplicating it would look wrong, but we still allow older
	 * sites to opt-in.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function add_session_info_to_speaker_posts( $content ) {
		global $post;
		$enabled_site_ids = apply_filters( 'wcpt_speaker_post_session_info_enabled_site_ids', array( 364 ) );    // 2014.sf

		if ( ! $this->is_single_cpt_post( 'wcb_speaker') ) {
			return $content;
		}

		$site_id = get_current_blog_id();
		if ( $site_id <= apply_filters( 'wcpt_speaker_post_session_info_min_site_id', 463 ) && ! in_array( $site_id, $enabled_site_ids ) ) {
			return $content;
		}

		$session_args = array(
			'post_type'      => 'wcb_session',
			'posts_per_page' => -1,
			'meta_key'       => '_wcpt_speaker_id',
			'meta_value'     => $post->ID,
			'orderby'        => 'title',
			'order'          => 'asc',
		);

		$sessions = new WP_Query( $session_args );

		if ( ! $sessions->have_posts() ) {
			return $content;
		}

		$sessions_html = sprintf(
			'<h2 class="speaker-sessions">%s</h2>',
			_n(
				'Session',
				'Sessions',
				$sessions->post_count,
				'wordcamporg'
			)
		);

		$sessions_html .= '<ul id="speaker-session-names">';
		while ( $sessions->have_posts() ) {
			$sessions->the_post();
			$sessions_html .= sprintf( '<li><a href="%s">%s</a></li>', get_the_permalink(), get_the_title() );
		}
		$sessions_html .= '</ul>';

		wp_reset_postdata();

		return $content . $sessions_html;
	}

	/**
	 * Fired during add_meta_boxes, adds extra meta boxes to our custom post types.
	 */
	public function add_meta_boxes() {
		add_meta_box( 'speaker-info',      __( 'Speaker Info',      'wordcamporg'  ), array( $this, 'metabox_speaker_info'      ), 'wcb_speaker',   'side'   );
		add_meta_box( 'organizer-info',    __( 'Organizer Info',    'wordcamporg'  ), array( $this, 'metabox_organizer_info'    ), 'wcb_organizer', 'side'   );
		add_meta_box( 'speakers-list',     __( 'Speakers',          'wordcamporg'  ), array( $this, 'metabox_speakers_list'     ), 'wcb_session',   'side'   );
		add_meta_box( 'session-info',      __( 'Session Info',      'wordcamporg'  ), array( $this, 'metabox_session_info'      ), 'wcb_session',   'normal' );
		add_meta_box( 'sponsor-info',      __( 'Sponsor Info',      'wordcamporg'  ), array( $this, 'metabox_sponsor_info'      ), 'wcb_sponsor',   'normal' );
		add_meta_box( 'sponsor-agreement', __( 'Sponsor Agreement', 'wordcamporg'  ), array( $this, 'metabox_sponsor_agreement' ), 'wcb_sponsor',   'side'   );
		add_meta_box( 'invoice-sponsor',   __( 'Invoice Sponsor',   'wordcamporg'  ), array( $this, 'metabox_invoice_sponsor'   ), 'wcb_sponsor',   'side'   );
	}

	/**
	 * Used by the Speakers post type
	 */
	public function metabox_speaker_info() {
		global $post;
		$email = get_post_meta( $post->ID, '_wcb_speaker_email', true );

		$wporg_username = '';
		$user_id        = get_post_meta( $post->ID, '_wcpt_user_id', true );
		$wporg_user     = get_user_by( 'id', $user_id );

		if ( $wporg_user ) {
			$wporg_username = $wporg_user->user_login;
		}
		?>

		<?php wp_nonce_field( 'edit-speaker-info', 'wcpt-meta-speaker-info' ); ?>

		<p>
			<label for="wcpt-gravatar-email"><?php esc_html_e( 'Gravatar Email:', 'wordcamporg' ); ?></label>
			<input type="text" class="widefat" id="wcpt-gravatar-email" name="wcpt-gravatar-email" value="<?php echo esc_attr( $email ); ?>" />
		</p>

		<p>
			<label for="wcpt-wporg-username"><?php esc_html_e( 'WordPress.org Username:', 'wordcamporg' ); ?></label>
			<input type="text" class="widefat" id="wcpt-wporg-username" name="wcpt-wporg-username" value="<?php echo esc_attr( $wporg_username ); ?>" />
		</p>

		<?php
	}

	/**
	 * Rendered in the Organizer post type
	 */
	public function metabox_organizer_info() {
		global $post;

		$wporg_username = '';
		$user_id        = get_post_meta( $post->ID, '_wcpt_user_id', true );
		$wporg_user     = get_user_by( 'id', $user_id );

		if ( $wporg_user ) {
			$wporg_username = $wporg_user->user_login;
		}
		?>

		<?php wp_nonce_field( 'edit-organizer-info', 'wcpt-meta-organizer-info' ); ?>

		<p>
			<label for="wcpt-wporg-username"><?php esc_html_e( 'WordPress.org Username:', 'wordcamporg' ); ?></label>
			<input type="text" class="widefat" id="wcpt-wporg-username" name="wcpt-wporg-username" value="<?php echo esc_attr( $wporg_username ); ?>" />
		</p>

		<?php
	}

	/**
	 * Used by the Sessions post type, renders a text box for speakers input.
	 */
	public function metabox_speakers_list() {
		global $post;

		$speakers = get_post_meta( $post->ID, '_wcb_session_speakers', true );

		wp_enqueue_script( 'jquery-ui-autocomplete' );

		$speakers_names   = array();
		$speakers_objects = get_posts( array(
			'post_type'      => 'wcb_speaker',
			'post_status'    => 'any',
			'posts_per_page' => -1,
		) );

		// We'll use these in js.
		foreach ( $speakers_objects as $speaker_object ) {
			$speakers_names[] = $speaker_object->post_title;
		}

		$speakers_names_first = array_pop( $speakers_names );

		?>

		<?php wp_nonce_field( 'edit-speakers-list', 'wcpt-meta-speakers-list-nonce' ); ?>

		<!--<input type="text" class="text" id="wcpt-speakers-list" name="wcpt-speakers-list" value="<?php echo esc_attr( $speakers ); ?>" />-->
		<textarea class="large-text" placeholder="Start typing a name" id="wcpt-speakers-list" name="wcpt-speakers-list"><?php
			echo esc_textarea( $speakers );
		?></textarea>

		<p class="description">
			<?php esc_html_e( 'A speaker entry must exist first. Separate multiple speakers with commas.', 'wordcamporg' ); ?>
		</p>

		<script>
			jQuery( document ).ready( function ( $ ) {
				var availableSpeakers = [
					<?php

					foreach ( $speakers_names as $name ) {
						printf( "'%s', ", esc_js( $name ) );
					}

					printf( "'%s'", esc_js( $speakers_names_first ) ); // avoid the trailing comma.

					?>
				];

				function split( val ) {
					return val.split( /,\s*/ );
				}

				function extractLast( term ) {
					return split( term ).pop();
				}

				$( '#wcpt-speakers-list' ).bind( 'keydown', function ( event ) {
					if ( event.keyCode == $.ui.keyCode.TAB &&
						$( this ).data( 'autocomplete' ).menu.active ) {
						event.preventDefault();
					}
				} ).autocomplete( {
					minLength: 0,

					source: function ( request, response ) {
						response( $.ui.autocomplete.filter(
							availableSpeakers, extractLast( request.term ) ) );
					},

					focus: function () {
						return false;
					},

					select: function ( event, ui ) {
						var terms = split( this.value );
						terms.pop();
						terms.push( ui.item.value );
						terms.push( '' );
						this.value = terms.join( ', ' );
						$( this ).focus();
						return false;
					},

					open: function () {
						$( this ).addClass( 'open' );
					},

					close: function () {
						$( this ).removeClass( 'open' );
					}
				} );
			} );
		</script>

		<?php
	}

	/**
	 * Renders session info metabox.
	 */
	public function metabox_session_info() {
		$post             = get_post();
		$session_time     = absint( get_post_meta( $post->ID, '_wcpt_session_time', true ) );
		$session_date     = ( $session_time ) ? date( 'Y-m-d', $session_time ) : date( 'Y-m-d' );
		$session_hours    = ( $session_time ) ? date( 'g', $session_time )     : date( 'g' );
		$session_minutes  = ( $session_time ) ? date( 'i', $session_time )     : '00';
		$session_meridiem = ( $session_time ) ? date( 'a', $session_time )     : 'am';
		$session_type     = get_post_meta( $post->ID, '_wcpt_session_type', true );
		$session_slides   = get_post_meta( $post->ID, '_wcpt_session_slides', true );
		$session_video    = get_post_meta( $post->ID, '_wcpt_session_video',  true );
		?>

		<?php wp_nonce_field( 'edit-session-info', 'wcpt-meta-session-info' ); ?>

		<p>
			<label for="wcpt-session-date"><?php esc_html_e( 'Date:', 'wordcamporg' ); ?></label>
			<input type="text" id="wcpt-session-date" data-date="<?php echo esc_attr( $session_date ); ?>" name="wcpt-session-date" value="<?php echo esc_attr( $session_date ); ?>" /><br />
			<label><?php esc_html_e( 'Time:', 'wordcamporg' ); ?></label>

			<select name="wcpt-session-hour" aria-label="<?php esc_html_e( 'Session Start Hour', 'wordcamporg' ); ?>">
				<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $i, $session_hours ); ?>>
						<?php echo esc_html( $i ); ?>
					</option>
				<?php endfor; ?>
			</select> :

			<select name="wcpt-session-minutes" aria-label="<?php esc_html_e( 'Session Start Minutes', 'wordcamporg' ); ?>">
				<?php for ( $i = '00'; (int) $i <= 55; $i = sprintf( '%02d', (int) $i + 5 ) ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $i, $session_minutes ); ?>>
						<?php echo esc_html( $i ); ?>
					</option>
				<?php endfor; ?>
			</select>

			<select name="wcpt-session-meridiem" aria-label="<?php esc_html_e( 'Session Meridiem', 'wordcamporg' ); ?>">
				<option value="am" <?php selected( 'am', $session_meridiem ); ?>>am</option>
				<option value="pm" <?php selected( 'pm', $session_meridiem ); ?>>pm</option>
			</select>
		</p>

		<p>
			<label for="wcpt-session-type"><?php esc_html_e( 'Type:', 'wordcamporg' ); ?></label>
			<select id="wcpt-session-type" name="wcpt-session-type">
				<option value="session" <?php selected( $session_type, 'session' ); ?>><?php esc_html_e( 'Regular Session', 'wordcamporg' ); ?></option>
				<option value="custom" <?php selected( $session_type, 'custom' ); ?>><?php esc_html_e( 'Break, Lunch, etc.', 'wordcamporg' ); ?></option>
			</select>
		</p>

		<p>
			<label for="wcpt-session-slides"><?php esc_html_e( 'Slides URL:', 'wordcamporg' ); ?></label>
			<input type="text" class="widefat" id="wcpt-session-slides" name="wcpt-session-slides" value="<?php echo esc_url( $session_slides ); ?>" />
		</p>

		<p>
			<label for="wcpt-session-video"><?php esc_html_e( 'WordPress.TV URL:', 'wordcamporg' ); ?></label>
			<input type="text" class="widefat" id="wcpt-session-video" name="wcpt-session-video" value="<?php echo esc_url( $session_video ); ?>" />
		</p>

		<?php
	}

	/**
	 * Render the Sponsor Info metabox view
	 *
	 * @param WP_Post $sponsor
	 */
	public function metabox_sponsor_info( $sponsor ) {
		$company_name   = get_post_meta( $sponsor->ID, '_wcpt_sponsor_company_name',   true );
		$website        = get_post_meta( $sponsor->ID, '_wcpt_sponsor_website',        true );
		$first_name     = get_post_meta( $sponsor->ID, '_wcpt_sponsor_first_name',     true );
		$last_name      = get_post_meta( $sponsor->ID, '_wcpt_sponsor_last_name',      true );
		$email_address  = get_post_meta( $sponsor->ID, '_wcpt_sponsor_email_address',  true );
		$phone_number   = get_post_meta( $sponsor->ID, '_wcpt_sponsor_phone_number',   true );
		$vat_number     = get_post_meta( $sponsor->ID, '_wcpt_sponsor_vat_number',     true );
		$twitter_handle = get_post_meta( $sponsor->ID, '_wcpt_sponsor_twitter_handle', true );

		$street_address1 = get_post_meta( $sponsor->ID, '_wcpt_sponsor_street_address1', true );
		$street_address2 = get_post_meta( $sponsor->ID, '_wcpt_sponsor_street_address2', true );
		$city            = get_post_meta( $sponsor->ID, '_wcpt_sponsor_city',            true );
		$state           = get_post_meta( $sponsor->ID, '_wcpt_sponsor_state',           true );
		$zip_code        = get_post_meta( $sponsor->ID, '_wcpt_sponsor_zip_code',        true );
		$country         = get_post_meta( $sponsor->ID, '_wcpt_sponsor_country',         true );

		if ( $state === $this->get_sponsor_info_state_default_value() ) {
			$state = '';
		}

		if ( wcorg_skip_feature( 'cldr-countries' ) ) {
			$available_countries = array( 'Abkhazia', 'Afghanistan', 'Aland', 'Albania', 'Algeria', 'American Samoa', 'Andorra', 'Angola', 'Anguilla', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Aruba', 'Ascension', 'Ashmore and Cartier Islands', 'Australia', 'Australian Antarctic Territory', 'Austria', 'Azerbaijan', 'Bahamas, The', 'Bahrain', 'Baker Island', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bermuda', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Bouvet Island', 'Brazil', 'British Antarctic Territory', 'British Indian Ocean Territory', 'British Sovereign Base Areas', 'British Virgin Islands', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cambodia', 'Cameroon', 'Canada', 'Cape Verde', 'Cayman Islands', 'Central African Republic', 'Chad', 'Chile', "China, People's Republic of", 'China, Republic of (Taiwan)', 'Christmas Island', 'Clipperton Island', 'Cocos (Keeling) Islands', 'Colombia', 'Comoros', 'Congo, (Congo  Brazzaville)', 'Congo, (Congo  Kinshasa)', 'Cook Islands', 'Coral Sea Islands', 'Costa Rica', "Cote d'Ivoire (Ivory Coast)", 'Croatia', 'Cuba', 'Cyprus', 'Czech Republic', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Ethiopia', 'Falkland Islands (Islas Malvinas)', 'Faroe Islands', 'Fiji', 'Finland', 'France', 'French Guiana', 'French Polynesia', 'French Southern and Antarctic Lands', 'Gabon', 'Gambia, The', 'Georgia', 'Germany', 'Ghana', 'Gibraltar', 'Greece', 'Greenland', 'Grenada', 'Guadeloupe', 'Guam', 'Guatemala', 'Guernsey', 'Guinea', 'Guinea-Bissau', 'Guyana', 'Haiti', 'Heard Island and McDonald Islands', 'Honduras', 'Hong Kong', 'Howland Island', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Isle of Man', 'Israel', 'Italy', 'Jamaica', 'Japan', 'Jarvis Island', 'Jersey', 'Johnston Atoll', 'Jordan', 'Kazakhstan', 'Kenya', 'Kingman Reef', 'Kiribati', 'Korea, North', 'Korea, South', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Macau', 'Macedonia', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Martinique', 'Mauritania', 'Mauritius', 'Mayotte', 'Mexico', 'Micronesia', 'Midway Islands', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Montserrat', 'Morocco', 'Mozambique', 'Myanmar (Burma)', 'Nagorno-Karabakh', 'Namibia', 'Nauru', 'Navassa Island', 'Nepal', 'Netherlands', 'Netherlands Antilles', 'New Caledonia', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'Niue', 'Norfolk Island', 'Northern Cyprus', 'Northern Mariana Islands', 'Norway', 'Oman', 'Pakistan', 'Palau', 'Palmyra Atoll', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Peter I Island', 'Philippines', 'Pitcairn Islands', 'Poland', 'Portugal', 'Pridnestrovie (Transnistria)', 'Puerto Rico', 'Qatar', 'Queen Maud Land', 'Reunion', 'Romania', 'Ross Dependency', 'Russia', 'Rwanda', 'Saint Barthelemy', 'Saint Helena', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Martin', 'Saint Pierre and Miquelon', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia', 'Somaliland', 'South Africa', 'South Georgia & South Sandwich Islands', 'South Ossetia', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Svalbard', 'Swaziland', 'Sweden', 'Switzerland', 'Syria', 'Tajikistan', 'Tanzania', 'Thailand', 'Timor-Leste (East Timor)', 'Togo', 'Tokelau', 'Tonga', 'Trinidad and Tobago', 'Tristan da Cunha', 'Tunisia', 'Turkey', 'Turkmenistan', 'Turks and Caicos Islands', 'Tuvalu', 'U.S. Virgin Islands', 'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan', 'Vanuatu', 'Vatican City', 'Venezuela', 'Vietnam', 'Wake Island', 'Wallis and Futuna', 'Yemen', 'Zambia', 'Zimbabwe' );
		} else {
			$available_countries = wcorg_get_countries();
		}

		wp_nonce_field( 'edit-sponsor-info', 'wcpt-meta-sponsor-info' );

		require_once __DIR__ . '/views/sponsors/metabox-sponsor-info.php';
	}

	/**
	 * Returns the default value for the state input when it's empty
	 *
	 * @return string
	 */
	protected function get_sponsor_info_state_default_value() {
		return 'Not Applicable';
	}

	/**
	 * Render the Sponsor Agreement metabox view.
	 *
	 * @param WP_Post $sponsor
	 */
	public function metabox_sponsor_agreement( $sponsor ) {
		$agreement_id  = get_post_meta( $sponsor->ID, '_wcpt_sponsor_agreement', true );
		$agreement_url = wp_get_attachment_url( $agreement_id );

		$mes_id = get_post_meta( $sponsor->ID, '_mes_id', true );

		if ( $mes_id ) {
			switch_to_blog( BLOG_ID_CURRENT_SITE ); // central.wordcamp.org .

			$mes_agreement_id = get_post_meta( $mes_id, 'mes_sponsor_agreement', true );
			if ( $mes_agreement_id ) {
				$agreement_url = wp_get_attachment_url( $mes_agreement_id );
			} else {
				$agreement_url = '';
			}

			restore_current_blog();
		}

		require_once __DIR__ . '/views/sponsors/metabox-sponsor-agreement.php';
	}

	/**
	 * Render the Invoice Sponsor metabox view
	 *
	 * @param WP_Post $sponsor
	 */
	public function metabox_invoice_sponsor( $sponsor ) {
		$current_screen = get_current_screen();

		$existing_invoices = get_posts( array(
			'post_type'      => \WordCamp\Budgets\Sponsor_Invoices\POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => - 1,

			'meta_query'     => array(
				array(
					'key'   => '_wcbsi_sponsor_id',
					'value' => $sponsor->ID,
				),
			),
		) );

		$new_invoice_url = add_query_arg(
			array(
				'post_type'  => 'wcb_sponsor_invoice',
				'sponsor_id' => $sponsor->ID,
			),
			admin_url( 'post-new.php' )
		);

		require_once __DIR__ . '/views/sponsors/metabox-invoice-sponsor.php';
	}

	/**
	 * Fired when a post is saved, makes sure additional metadata is also updated.
	 */
	public function save_post_speaker( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || 'wcb_speaker' != $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['wcpt-meta-speaker-info'] ) && wp_verify_nonce( $_POST['wcpt-meta-speaker-info'], 'edit-speaker-info' ) ) {
			$email          = sanitize_text_field( $_POST['wcpt-gravatar-email'] );
			$wporg_username = sanitize_text_field( $_POST['wcpt-wporg-username'] );
			$wporg_user     = wcorg_get_user_by_canonical_names( $wporg_username );

			if ( empty( $email ) ) {
				delete_post_meta( $post_id, '_wcb_speaker_email' );
			} elseif ( $email && is_email( $email ) ) {
				update_post_meta( $post_id, '_wcb_speaker_email', $email );
			}

			if ( ! $wporg_user ) {
				delete_post_meta( $post_id, '_wcpt_user_id' );
			} else {
				update_post_meta( $post_id, '_wcpt_user_id', $wporg_user->ID );
			}
		}
	}

	/**
	 * When an Organizer post is saved, update some meta data.
	 */
	public function save_post_organizer( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || 'wcb_organizer' != $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['wcpt-meta-organizer-info'] ) && wp_verify_nonce( $_POST['wcpt-meta-organizer-info'], 'edit-organizer-info' ) ) {
			$wporg_username = sanitize_text_field( $_POST['wcpt-wporg-username'] );
			$wporg_user     = wcorg_get_user_by_canonical_names( $wporg_username );

			if ( ! $wporg_user ) {
				delete_post_meta( $post_id, '_wcpt_user_id' );
			} else {
				update_post_meta( $post_id, '_wcpt_user_id', $wporg_user->ID );
			}
		}
	}

	/**
	 * Fired when a post is saved, updates additional sessions metadada.
	 */
	public function save_post_session( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || 'wcb_session' != $post->post_type ) {
			return;
		}

		if ( isset( $_POST['wcpt-meta-speakers-list-nonce'] ) && wp_verify_nonce( $_POST['wcpt-meta-speakers-list-nonce'], 'edit-speakers-list' ) && current_user_can( 'edit_post', $post_id ) ) {

			// Update the text box as is for backwards compatibility.
			$speakers = sanitize_text_field( $_POST['wcpt-speakers-list'] );
			update_post_meta( $post_id, '_wcb_session_speakers', $speakers );
		}

		if ( isset( $_POST['wcpt-meta-session-info'] ) && wp_verify_nonce( $_POST['wcpt-meta-session-info'], 'edit-session-info' ) ) {
			// Update session time.
			$session_time = strtotime( sprintf(
				'%s %d:%02d %s',
				sanitize_text_field( $_POST['wcpt-session-date'] ),
				absint( $_POST['wcpt-session-hour'] ),
				absint( $_POST['wcpt-session-minutes'] ),
				'am' == $_POST['wcpt-session-meridiem'] ? 'am' : 'pm'
			) );
			update_post_meta( $post_id, '_wcpt_session_time', $session_time );

			// Update session type.
			$session_type = sanitize_text_field( $_POST['wcpt-session-type'] );
			if ( ! in_array( $session_type, array( 'session', 'custom' ) ) ) {
				$session_type = 'session';
			}

			update_post_meta( $post_id, '_wcpt_session_type', $session_type );

			// Update session slides link.
			update_post_meta( $post_id, '_wcpt_session_slides', esc_url_raw( $_POST['wcpt-session-slides'] ) );

			// Update session video link.
			if ( 'wordpress.tv' == str_replace( 'www.', '', strtolower( wp_parse_url( $_POST['wcpt-session-video'], PHP_URL_HOST ) ) ) ) {
				update_post_meta( $post_id, '_wcpt_session_video', esc_url_raw( $_POST['wcpt-session-video'] ) );
			}
		}

		// Allowed outside of $_POST. If anything updates a session, make sure.
		// we parse the list of speakers and add the references to speakers.
		$speakers_list = get_post_meta( $post_id, '_wcb_session_speakers', true );
		$speakers_list = explode( ',', $speakers_list );

		if ( ! is_array( $speakers_list ) ) {
			$speakers_list = array();
		}

		$speaker_ids = array();
		$speakers    = array_unique( array_map( 'trim', $speakers_list ) );

		foreach ( $speakers as $speaker_name ) {
			if ( empty( $speaker_name ) ) {
				continue;
			}

			/*
			 * Look for speakers by their names.
			 *
			 * @todo - This is very fragile, it fails if the speaker name has a tab character instead of a space
			 * separating the first from last name, or an extra space at the end, etc. Those situations often arise
			 * from copy/pasting the speaker data from spreadsheets. Moving to automated speaker submissions and
			 * tighter integration with WordPress.org usernames should avoid this, but if not we should do something
			 * here to make it more forgiving.
			 */
			$speaker = get_page_by_title( $speaker_name, OBJECT, 'wcb_speaker' );
			if ( $speaker ) {
				$speaker_ids[] = $speaker->ID;
			}
		}

		// Add speaker IDs to post meta.
		$speaker_ids = array_unique( $speaker_ids );
		delete_post_meta( $post_id, '_wcpt_speaker_id' );
		foreach ( $speaker_ids as $speaker_id ) {
			add_post_meta( $post_id, '_wcpt_speaker_id', $speaker_id );
		}

		// Set the speaker as the author of the session post, so the single.
		// view doesn't confuse users who see "posted by [organizer name]".
		foreach ( $speaker_ids as $speaker_post ) {
			$wporg_user_id = get_post_meta( $speaker_post, '_wcpt_user_id', true );
			$user          = get_user_by( 'id', $wporg_user_id );

			if ( $user ) {
				remove_action( 'save_post', array( $this, 'save_post_session' ), 10 );   // avoid infinite recursion.
				wp_update_post( array(
					'ID'          => $post_id,
					'post_author' => $user->ID,
				) );
				add_action( 'save_post', array( $this, 'save_post_session' ), 10, 2 );

				break;
			}
		}
	}

	/**
	 * Save meta data for Sponsor posts
	 */
	public function save_post_sponsor( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || 'wcb_sponsor' != $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( wp_verify_nonce( filter_input( INPUT_POST, 'wcpt-meta-sponsor-info' ), 'edit-sponsor-info' ) ) {
			$text_values = array(
				'company_name',
				'first_name',
				'last_name',
				'email_address',
				'phone_number',
				'vat_number',
				'twitter_handle',
				'street_address1',
				'street_address2',
				'city',
				'state',
				'zip_code',
				'country',
			);

			foreach ( $text_values as $id ) {
				$values[ $id ] = sanitize_text_field( filter_input( INPUT_POST, '_wcpt_sponsor_' . $id ) );
			}

			if ( empty( $values['state'] ) ) {
				$values['state'] = $this->get_sponsor_info_state_default_value();
			}

			$values['website'] = esc_url_raw( filter_input( INPUT_POST, '_wcpt_sponsor_website' ) );
			// TODO: maybe only allows links to home page, depending on outcome of http://make.wordpress.org/community/2013/12/31/irs-rules-for-corporate-sponsorship-of-wordcamp/ .
			$values['first_name'] = ucfirst( $values['first_name'] );
			$values['last_name']  = ucfirst( $values['last_name'] );
			$values['agreement']  = filter_input( INPUT_POST, '_wcpt_sponsor_agreement', FILTER_SANITIZE_NUMBER_INT );

			foreach ( $values as $id => $value ) {
				$meta_key = '_wcpt_sponsor_' . $id;

				if ( empty( $value ) ) {
					delete_post_meta( $post_id, $meta_key );
				} else {
					update_post_meta( $post_id, $meta_key, $value );
				}
			}
		}
	}

	/**
	 * Registers the custom post types, runs during init.
	 */
	public function register_post_types() {
		// Speaker post type labels.
		$labels = array(
			'name'               => __( 'Speakers',                   'wordcamporg' ),
			'singular_name'      => __( 'Speaker',                    'wordcamporg' ),
			'add_new'            => __( 'Add New',                    'wordcamporg' ),
			'add_new_item'       => __( 'Create New Speaker',         'wordcamporg' ),
			'edit'               => __( 'Edit',                       'wordcamporg' ),
			'edit_item'          => __( 'Edit Speaker',               'wordcamporg' ),
			'new_item'           => __( 'New Speaker',                'wordcamporg' ),
			'view'               => __( 'View Speaker',               'wordcamporg' ),
			'view_item'          => __( 'View Speaker',               'wordcamporg' ),
			'search_items'       => __( 'Search Speakers',            'wordcamporg' ),
			'not_found'          => __( 'No speakers found',          'wordcamporg' ),
			'not_found_in_trash' => __( 'No speakers found in Trash', 'wordcamporg' ),
			'parent_item_colon'  => __( 'Parent Speaker:',            'wordcamporg' ),
		);

		// Register speaker post type.
		register_post_type(
			'wcb_speaker',
			array(
				'labels'          => $labels,
				'rewrite'         => array(
					'slug'       => 'speaker',
					'with_front' => true,
				),
				'supports'        => array( 'title', 'editor', 'excerpt', 'author', 'revisions', 'comments', 'custom-fields' ),
				'menu_position'   => 20,
				'public'          => true,
				'show_ui'         => true,
				'can_export'      => true,
				'capability_type' => 'post',
				'hierarchical'    => false,
				'query_var'       => true,
				'menu_icon'       => 'dashicons-megaphone',
				'show_in_rest'    => true,
				'rest_base'       => 'speakers',
			)
		);

		// Session post type labels.
		$labels = array(
			'name'               => __( 'Sessions',                   'wordcamporg' ),
			'singular_name'      => __( 'Session',                    'wordcamporg' ),
			'add_new'            => __( 'Add New',                    'wordcamporg' ),
			'add_new_item'       => __( 'Create New Session',         'wordcamporg' ),
			'edit'               => __( 'Edit',                       'wordcamporg' ),
			'edit_item'          => __( 'Edit Session',               'wordcamporg' ),
			'new_item'           => __( 'New Session',                'wordcamporg' ),
			'view'               => __( 'View Session',               'wordcamporg' ),
			'view_item'          => __( 'View Session',               'wordcamporg' ),
			'search_items'       => __( 'Search Sessions',            'wordcamporg' ),
			'not_found'          => __( 'No sessions found',          'wordcamporg' ),
			'not_found_in_trash' => __( 'No sessions found in Trash', 'wordcamporg' ),
			'parent_item_colon'  => __( 'Parent Session:',            'wordcamporg' ),
		);

		// Register session post type.
		register_post_type(
			'wcb_session',
			array(
				'labels'          => $labels,
				'rewrite'         => array(
					'slug'       => 'session',
					'with_front' => false,
				),
				'supports'        => array( 'title', 'editor', 'excerpt', 'author', 'revisions', 'thumbnail', 'custom-fields' ),
				'menu_position'   => 21,
				'public'          => true,
				'show_ui'         => true,
				'can_export'      => true,
				'capability_type' => 'post',
				'hierarchical'    => false,
				'query_var'       => true,
				'menu_icon'       => 'dashicons-schedule',
				'show_in_rest'    => true,
				'rest_base'       => 'sessions',
			)
		);

		// Sponsor post type labels.
		$labels = array(
			'name'               => __( 'Sponsors',                   'wordcamporg' ),
			'singular_name'      => __( 'Sponsor',                    'wordcamporg' ),
			'add_new'            => __( 'Add New',                    'wordcamporg' ),
			'add_new_item'       => __( 'Create New Sponsor',         'wordcamporg' ),
			'edit'               => __( 'Edit',                       'wordcamporg' ),
			'edit_item'          => __( 'Edit Sponsor',               'wordcamporg' ),
			'new_item'           => __( 'New Sponsor',                'wordcamporg' ),
			'view'               => __( 'View Sponsor',               'wordcamporg' ),
			'view_item'          => __( 'View Sponsor',               'wordcamporg' ),
			'search_items'       => __( 'Search Sponsors',            'wordcamporg' ),
			'not_found'          => __( 'No sponsors found',          'wordcamporg' ),
			'not_found_in_trash' => __( 'No sponsors found in Trash', 'wordcamporg' ),
			'parent_item_colon'  => __( 'Parent Sponsor:',            'wordcamporg' ),
		);

		// Register sponsor post type.
		register_post_type(
			'wcb_sponsor',
			array(
				'labels'          => $labels,
				'rewrite'         => array(
					'slug'       => 'sponsor',
					'with_front' => false,
				),
				'supports'        => array( 'title', 'editor', 'excerpt', 'revisions', 'thumbnail', 'custom-fields' ),
				'menu_position'   => 21,
				'public'          => true,
				'show_ui'         => true,
				'can_export'      => true,
				'capability_type' => 'post',
				'hierarchical'    => false,
				'query_var'       => true,
				'menu_icon'       => 'dashicons-heart',
				'show_in_rest'    => true,
				'rest_base'       => 'sponsors',
			)
		);

		// Organizer post type labels.
		$labels = array(
			'name'               => __( 'Organizers',                   'wordcamporg' ),
			'singular_name'      => __( 'Organizer',                    'wordcamporg' ),
			'add_new'            => __( 'Add New',                      'wordcamporg' ),
			'add_new_item'       => __( 'Create New Organizer',         'wordcamporg' ),
			'edit'               => __( 'Edit',                         'wordcamporg' ),
			'edit_item'          => __( 'Edit Organizer',               'wordcamporg' ),
			'new_item'           => __( 'New Organizer',                'wordcamporg' ),
			'view'               => __( 'View Organizer',               'wordcamporg' ),
			'view_item'          => __( 'View Organizer',               'wordcamporg' ),
			'search_items'       => __( 'Search Organizers',            'wordcamporg' ),
			'not_found'          => __( 'No organizers found',          'wordcamporg' ),
			'not_found_in_trash' => __( 'No organizers found in Trash', 'wordcamporg' ),
			'parent_item_colon'  => __( 'Parent Organizer:',            'wordcamporg' ),
		);

		// Register organizer post type.
		register_post_type(
			'wcb_organizer',
			array(
				'labels'          => $labels,
				'rewrite'         => array(
					'slug'       => 'organizer',
					'with_front' => false,
				),
				'supports'        => array( 'title', 'editor', 'excerpt', 'revisions' ),
				'menu_position'   => 22,
				'public'          => false,
				// todo public or publicly_queryable = true, so consistent with others? at the very least set show_in_json = true.
				'show_ui'         => true,
				'can_export'      => true,
				'capability_type' => 'post',
				'hierarchical'    => false,
				'query_var'       => true,
				'show_in_rest'    => true,
				'rest_base'       => 'organizers',
				'menu_icon'       => 'dashicons-groups',
			)
		);
	}

	/**
	 * Registers custom taxonomies to post types.
	 */
	public function register_taxonomies() {
		// Labels for tracks.
		$labels = array(
			'name'          => __( 'Tracks',         'wordcamporg' ),
			'singular_name' => __( 'Track',          'wordcamporg' ),
			'search_items'  => __( 'Search Tracks',  'wordcamporg' ),
			'popular_items' => __( 'Popular Tracks', 'wordcamporg' ),
			'all_items'     => __( 'All Tracks',     'wordcamporg' ),
			'edit_item'     => __( 'Edit Track',     'wordcamporg' ),
			'update_item'   => __( 'Update Track',   'wordcamporg' ),
			'add_new_item'  => __( 'Add Track',      'wordcamporg' ),
			'new_item_name' => __( 'New Track',      'wordcamporg' ),
		);

		// Register the Tracks taxonomy.
		register_taxonomy(
			'wcb_track',
			'wcb_session',
			array(
				'labels'       => $labels,
				'rewrite'      => array( 'slug' => 'track' ),
				'query_var'    => 'track',
				'hierarchical' => true,
				'public'       => true,
				'show_ui'      => true,
				'show_in_rest' => true,
				'rest_base'    => 'session_track',
			)
		);

		// Labels for categories.
		$labels = array(
			'name'          => __( 'Categories',         'wordcamporg' ),
			'singular_name' => __( 'Category',           'wordcamporg' ),
			'search_items'  => __( 'Search Categories',  'wordcamporg' ),
			'popular_items' => __( 'Popular Categories', 'wordcamporg' ),
			'all_items'     => __( 'All Categories',     'wordcamporg' ),
			'edit_item'     => __( 'Edit Category',      'wordcamporg' ),
			'update_item'   => __( 'Update Category',    'wordcamporg' ),
			'add_new_item'  => __( 'Add Category',       'wordcamporg' ),
			'new_item_name' => __( 'New Category',       'wordcamporg' ),
		);

		// Register the Categories taxonomy.
		register_taxonomy(
			'wcb_session_category',
			'wcb_session',
			array(
				'labels'       => $labels,
				'rewrite'      => array( 'slug' => 'session-category' ),
				'query_var'    => 'session_category',
				'hierarchical' => true,
				'public'       => true,
				'show_ui'      => true,
				'show_in_rest' => true,
				'rest_base'    => 'session_category',
			)
		);

		// Labels for sponsor levels.
		$labels = array(
			'name'          => __( 'Sponsor Levels',         'wordcamporg' ),
			'singular_name' => __( 'Sponsor Level',          'wordcamporg' ),
			'search_items'  => __( 'Search Sponsor Levels',  'wordcamporg' ),
			'popular_items' => __( 'Popular Sponsor Levels', 'wordcamporg' ),
			'all_items'     => __( 'All Sponsor Levels',     'wordcamporg' ),
			'edit_item'     => __( 'Edit Sponsor Level',     'wordcamporg' ),
			'update_item'   => __( 'Update Sponsor Level',   'wordcamporg' ),
			'add_new_item'  => __( 'Add Sponsor Level',      'wordcamporg' ),
			'new_item_name' => __( 'New Sponsor Level',      'wordcamporg' ),
		);

		// Register sponsor level taxonomy.
		register_taxonomy(
			'wcb_sponsor_level',
			'wcb_sponsor',
			array(
				'labels'       => $labels,
				'rewrite'      => array( 'slug' => 'sponsor_level' ),
				'query_var'    => 'sponsor_level',
				'hierarchical' => true,
				'public'       => true,
				'show_ui'      => true,
				'show_in_rest' => true,
				'rest_base'    => 'sponsor_level',
			)
		);

		// Labels for organizer teams.
		$labels = array(
			'name'          => __( 'Teams',         'wordcamporg' ),
			'singular_name' => __( 'Team',          'wordcamporg' ),
			'search_items'  => __( 'Search Teams',  'wordcamporg' ),
			'popular_items' => __( 'Popular Teams', 'wordcamporg' ),
			'all_items'     => __( 'All Teams',     'wordcamporg' ),
			'edit_item'     => __( 'Edit Team',     'wordcamporg' ),
			'update_item'   => __( 'Update Team',   'wordcamporg' ),
			'add_new_item'  => __( 'Add Team',      'wordcamporg' ),
			'new_item_name' => __( 'New Team',      'wordcamporg' ),
		);

		// Register organizer teams taxonomy.
		register_taxonomy(
			'wcb_organizer_team',
			'wcb_organizer',
			array(
				'labels'       => $labels,
				'rewrite'      => array( 'slug' => 'team' ),
				'query_var'    => 'team',
				'hierarchical' => true,
				'public'       => true,
				'show_ui'      => true,
				'show_in_rest' => true,
				'rest_base'    => 'organizer_team',
			)
		);

		// Labels for speaker groups.
		$labels = array(
			'name'          => __( 'Groups',         'wordcamporg' ),
			'singular_name' => __( 'Group',          'wordcamporg' ),
			'search_items'  => __( 'Search Groups',  'wordcamporg' ),
			'popular_items' => __( 'Popular Groups', 'wordcamporg' ),
			'all_items'     => __( 'All Groups',     'wordcamporg' ),
			'edit_item'     => __( 'Edit Group',     'wordcamporg' ),
			'update_item'   => __( 'Update Group',   'wordcamporg' ),
			'add_new_item'  => __( 'Add Group',      'wordcamporg' ),
			'new_item_name' => __( 'New Group',      'wordcamporg' ),
		);

		// Register speaker groups taxonomy.
		register_taxonomy(
			'wcb_speaker_group',
			'wcb_speaker',
			array(
				'labels'       => $labels,
				'rewrite'      => array( 'slug' => 'speaker_group' ),
				'query_var'    => 'speaker_group',
				'hierarchical' => true,
				'public'       => true,
				'show_ui'      => true,
				'show_in_rest' => true,
				'rest_base'    => 'speaker_group',
			)
		);
	}

	/**
	 * Filters our custom post types columns. Instead of creating a filter for each
	 * post type, we applied the same callback function to the post types we want to
	 * override.
	 *
	 * @uses current_filter()
	 * @see __construct()
	 */
	public function manage_post_types_columns( $columns ) {
		$current_filter = current_filter();

		switch ( $current_filter ) {
			case 'manage_wcb_organizer_posts_columns':
				// Insert at offset 1, that's right after the checkbox.
				$columns = array_slice( $columns, 0, 1, true ) + array( 'wcb_organizer_avatar' => __( 'Avatar', 'wordcamporg' ) )   + array_slice( $columns, 1, null, true );
				break;

			case 'manage_wcb_speaker_posts_columns':
				$original_columns = $columns;

				$columns  = array_slice( $original_columns, 0, 1, true );
				$columns += array( 'wcb_speaker_avatar' => __( 'Avatar', 'wordcamporg' ) );
				$columns += array_slice( $original_columns, 1, 1, true );
				$columns += array(
					'wcb_speaker_email'          => __( 'Gravatar Email',         'wordcamporg' ),
					'wcb_speaker_wporg_username' => __( 'WordPress.org Username', 'wordcamporg' ),
				);
				$columns += array_slice( $original_columns, 2, null, true );

				break;

			case 'manage_wcb_session_posts_columns':
				$columns = array_slice( $columns, 0, 2, true ) + array( 'wcb_session_speakers' => __( 'Speakers', 'wordcamporg' ) ) + array_slice( $columns, 2, null, true );
				$columns = array_slice( $columns, 0, 1, true ) + array( 'wcb_session_time' => __( 'Time',     'wordcamporg' ) ) + array_slice( $columns, 1, null, true );
				break;
			default:
		}

		return $columns;
	}

	/**
	 * Custom columns output
	 *
	 * This generates the output to the extra columns added to the posts lists in the admin.
	 *
	 * @see manage_post_types_columns()
	 */
	public function manage_post_types_columns_output( $column, $post_id ) {
		switch ( $column ) {
			case 'wcb_organizer_avatar':
				edit_post_link( get_avatar( absint( get_post_meta( get_the_ID(), '_wcpt_user_id', true ) ), 32 ) );
				break;

			case 'wcb_speaker_avatar':
				edit_post_link( get_avatar( get_post_meta( get_the_ID(), '_wcb_speaker_email', true ), 32 ) );
				break;

			case 'wcb_speaker_email':
				echo esc_html( get_post_meta( get_the_ID(), '_wcb_speaker_email', true ) );
				break;

			case 'wcb_speaker_wporg_username':
				$user_id    = get_post_meta( get_the_ID(), '_wcpt_user_id', true );
				$wporg_user = get_user_by( 'id', $user_id );

				if ( $wporg_user ) {
					echo esc_html( $wporg_user->user_login );
				}

				break;

			case 'wcb_session_speakers':
				$speakers     = array();
				$speakers_ids = array_map( 'absint', (array) get_post_meta( $post_id, '_wcpt_speaker_id' ) );

				if ( ! empty( $speakers_ids ) ) {
					$speakers = get_posts( array(
						'post_type'      => 'wcb_speaker',
						'posts_per_page' => -1,
						'post__in'       => $speakers_ids,
					) );
				}

				$output = array();

				foreach ( $speakers as $speaker ) {
					$output[] = sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $speaker->ID ) ), esc_html( apply_filters( 'the_title', $speaker->post_title ) ) );
				}

				// Output is escaped when the string is built, so we can ignore the PHPCS error.
				echo implode( ', ', $output ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped

				break;

			case 'wcb_session_time':
				$session_time = absint( get_post_meta( get_the_ID(), '_wcpt_session_time', true ) );
				$session_time = ( $session_time ) ? date( get_option( 'time_format' ), $session_time ) : '&mdash;';
				echo esc_html( $session_time );
				break;

			default:
		}
	}

	/**
	 * Additional sortable columns for WP_Posts_List_Table
	 */
	public function manage_sortable_columns( $sortable ) {
		$current_filter = current_filter();

		if ( 'manage_edit-wcb_session_sortable_columns' == $current_filter ) {
			$sortable['wcb_session_time'] = '_wcpt_session_time';
		}

		return $sortable;
	}

	/**
	 * Display an additional post label if needed.
	 */
	public function display_post_states( $states ) {
		$post = get_post();

		if ( 'wcb_session' != $post->post_type ) {
			return $states;
		}

		$session_type = get_post_meta( $post->ID, '_wcpt_session_type', true );
		if ( ! in_array( $session_type, array( 'session', 'custom' ) ) ) {
			$session_type = 'session';
		}

		if ( 'session' == $session_type ) {
			$states['wcpt-session-type'] = __( 'Session', 'wordcamporg' );
		} elseif ( 'custom' == $session_type ) {
			$states['wcpt-session-type'] = __( 'Custom', 'wordcamporg' );
		}

		return $states;
	}

	/**
	 * Register some widgets.
	 */
	public function register_widgets() {
		require_once 'inc/widgets.php';

		register_widget( 'WCB_Widget_Sponsors'    );
		register_widget( 'WCPT_Widget_Speakers'   );
		register_widget( 'WCPT_Widget_Sessions'   );
		register_widget( 'WCPT_Widget_Organizers' );
	}

	/**
	 * Add post types to 'At a Glance' dashboard widget
	 */
	public function glance_items( $items = array() ) {
		$post_types = array( 'wcb_speaker', 'wcb_session', 'wcb_sponsor' );

		foreach ( $post_types as $post_type ) {

			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			$num_posts        = wp_count_posts( $post_type );
			$post_type_object = get_post_type_object( $post_type );

			if ( $num_posts && $num_posts->publish ) {

				switch ( $post_type ) {
					case 'wcb_speaker':
						$text = _n( '%s Speaker', '%s Speakers', $num_posts->publish );
						break;
					case 'wcb_session':
						$text = _n( '%s Session', '%s Sessions', $num_posts->publish );
						break;
					case 'wcb_sponsor':
						$text = _n( '%s Sponsor', '%s Sponsors', $num_posts->publish );
						break;
					default:
				}

				$text = sprintf( $text, number_format_i18n( $num_posts->publish ) );

				if ( current_user_can( $post_type_object->cap->edit_posts ) ) {
					$items[] = sprintf( '<a class="%1$s-count" href="edit.php?post_type=%1$s">%2$s</a>', $post_type, $text ) . "\n";
				} else {
					$items[] = sprintf( '<span class="%1$s-count">%2$s</span>', $post_type, $text ) . "\n";
				}
			}
		}

		return $items;
	}

	/**
	 * Comments and pings on speakers closed by default.
	 *
	 * @param string $status Default comment status.
	 * @return string Resulting status.
	 */
	public function default_comment_ping_status( $status ) {
		$screen = get_current_screen();
		if ( ! empty( $screen->post_type ) && 'wcb_speaker' == $screen->post_type ) {
			$status = 'closed';
		}

		return $status;
	}
}

// Load the plugin class.
$GLOBALS['wcpt_plugin'] = new WordCamp_Post_Types_Plugin();

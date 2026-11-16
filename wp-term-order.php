<?php
/**
 * Plugin Name:       Term Order
 * Plugin URI:        https://github.com/Azanumer/wp-term-order
 * Description:       Drag-and-drop ordering for taxonomy terms — categories, tags and custom taxonomies. The order you set in the admin is the order get_terms() and wp_list_categories() return.
 * Version:           1.0.0
 * Author:            Azan Umer
 * Author URI:        https://azanumer.cv
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       wp-term-order
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WTO_VERSION', '1.0.0' );
define( 'WTO_OPTION', 'wto_settings' );
define( 'WTO_META_KEY', 'wto_order' );

/**
 * Get the plugin settings, merged over defaults.
 *
 * @return array{taxonomies: string[]}
 */
function wto_get_settings() {
	$defaults = array(
		'taxonomies' => array( 'category' ),
	);
	$saved = get_option( WTO_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( $defaults, $saved );
}

/**
 * Taxonomies that have ordering enabled.
 *
 * @return string[]
 */
function wto_enabled_taxonomies() {
	$taxonomies = (array) wto_get_settings()['taxonomies'];
	$taxonomies = array_filter( array_map( 'sanitize_key', $taxonomies ) );

	/**
	 * Filter the taxonomies that get drag-and-drop ordering.
	 *
	 * @param string[] $taxonomies Taxonomy slugs.
	 */
	return apply_filters( 'wto_taxonomies', $taxonomies );
}

/**
 * Is ordering enabled for this taxonomy?
 *
 * @param string $taxonomy Taxonomy slug.
 * @return bool
 */
function wto_is_enabled( $taxonomy ) {
	return in_array( $taxonomy, wto_enabled_taxonomies(), true );
}

/**
 * Next free order value for a taxonomy (max existing + 10, leaving gaps
 * so a single drag between two terms never needs a full renumber).
 *
 * @param string $taxonomy Taxonomy slug.
 * @return int
 */
function wto_next_order( $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 0,
			'fields'     => 'ids',
			'meta_query' => array(
				array(
					'key'     => WTO_META_KEY,
					'compare' => 'EXISTS',
				),
			),
		)
	);
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return 10;
	}
	$max = 0;
	foreach ( $terms as $term_id ) {
		$max = max( $max, (int) get_term_meta( $term_id, WTO_META_KEY, true ) );
	}
	return $max + 10;
}

/**
 * Assign an order value to a term that does not have one yet.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy slug.
 */
function wto_maybe_assign_order( $term_id, $taxonomy ) {
	if ( ! wto_is_enabled( $taxonomy ) ) {
		return;
	}
	$existing = get_term_meta( $term_id, WTO_META_KEY, true );
	if ( '' === $existing ) {
		update_term_meta( $term_id, WTO_META_KEY, wto_next_order( $taxonomy ) );
	}
}
add_action( 'created_term', 'wto_maybe_assign_order', 10, 2 );

/* ------------------------------------------------------------------ */
/*  Admin: order column + drag-and-drop on the term list screen        */
/* ------------------------------------------------------------------ */

/**
 * Add an "Order" column to the term list table of enabled taxonomies.
 * Registered per taxonomy, so the taxonomy is read from the hook name
 * (manage_edit-{$taxonomy}_columns passes only $columns).
 *
 * @param array $columns Existing columns.
 * @return array
 */
function wto_add_order_column( $columns ) {
	$hook     = current_filter();
	$taxonomy = str_replace( array( 'manage_edit-', '_columns' ), '', $hook );
	if ( ! wto_is_enabled( $taxonomy ) ) {
		return $columns;
	}
	$columns['wto_order'] = __( 'Order', 'wp-term-order' );
	return $columns;
}

/**
 * The manage_edit-{$taxonomy}_columns filter is per-taxonomy; register it
 * for every public taxonomy so custom taxonomies get the column too.
 */
function wto_register_column_filters() {
	foreach ( get_taxonomies( array( 'public' => true ) ) as $taxonomy ) {
		add_filter( "manage_edit-{$taxonomy}_columns", 'wto_add_order_column' );
	}
}
add_action( 'admin_init', 'wto_register_column_filters' );

/**
 * Render the order value in the Order column.
 *
 * @param string $content    Cell content (unused).
 * @param string $column     Column name.
 * @param int    $term_id    Term ID.
 * @return string
 */
function wto_order_column_content( $content, $column, $term_id ) {
	if ( 'wto_order' !== $column ) {
		return $content;
	}
	$order = get_term_meta( $term_id, WTO_META_KEY, true );
	if ( '' === $order ) {
		$term = get_term( $term_id );
		if ( $term && ! is_wp_error( $term ) ) {
			wto_maybe_assign_order( $term_id, $term->taxonomy );
			$order = get_term_meta( $term_id, WTO_META_KEY, true );
		}
	}
	return '<span class="wto-order-value">' . esc_html( $order ) . '</span>';
}

/**
 * Same as above, registered per public taxonomy.
 */
function wto_register_custom_column_filters() {
	foreach ( get_taxonomies( array( 'public' => true ) ) as $taxonomy ) {
		add_filter( "manage_{$taxonomy}_custom_column", 'wto_order_column_content', 10, 3 );
	}
}
add_action( 'admin_init', 'wto_register_custom_column_filters' );

/**
 * Enqueue jQuery UI Sortable + our script on edit-tags.php for enabled
 * taxonomies, and only on the plain (unsorted, unsearched) list view.
 */
function wto_enqueue_admin_assets( $hook ) {
	if ( 'edit-tags.php' !== $hook ) {
		return;
	}
	$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : 'category';
	if ( ! wto_is_enabled( $taxonomy ) ) {
		return;
	}
	// Sorting only makes sense on the default list order.
	if ( isset( $_GET['orderby'] ) || isset( $_GET['s'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_enqueue_script(
		'wto-order',
		plugins_url( 'assets/js/wto-order.js', __FILE__ ),
		array( 'jquery', 'jquery-ui-sortable' ),
		WTO_VERSION,
		true
	);
	wp_enqueue_style(
		'wto-order',
		plugins_url( 'assets/css/wto-order.css', __FILE__ ),
		array(),
		WTO_VERSION
	);
	wp_localize_script(
		'wto-order',
		'wtoData',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wto_save_order' ),
			'taxonomy' => $taxonomy,
		)
	);
}
add_action( 'admin_enqueue_scripts', 'wto_enqueue_admin_assets' );

/**
 * AJAX handler: save the dragged order. Receives an ordered list of term
 * IDs and writes 10, 20, 30... so later single drags keep their gaps.
 */
function wto_ajax_save_order() {
	check_ajax_referer( 'wto_save_order' );

	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to reorder terms.', 'wp-term-order' ) ), 403 );
	}

	$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
	$order    = isset( $_POST['order'] ) ? (array) wp_unslash( $_POST['order'] ) : array();

	if ( ! wto_is_enabled( $taxonomy ) || empty( $order ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-term-order' ) ), 400 );
	}

	$position = 10;
	foreach ( $order as $term_id ) {
		$term_id = (int) $term_id;
		if ( $term_id <= 0 ) {
			continue;
		}
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			continue;
		}
		update_term_meta( $term_id, WTO_META_KEY, $position );
		$position += 10;
	}

	wp_send_json_success( array( 'saved' => count( $order ) ) );
}
add_action( 'wp_ajax_wto_save_order', 'wto_ajax_save_order' );

/* ------------------------------------------------------------------ */
/*  Front end: honour the manual order in get_terms()                  */
/* ------------------------------------------------------------------ */

/**
 * When a get_terms() call asks for an enabled taxonomy without an explicit
 * orderby, sort by the stored order value instead of the default (name).
 *
 * @param array $args       get_terms() arguments.
 * @param array $taxonomies Taxonomies being queried.
 * @return array
 */
function wto_apply_term_order( $args, $taxonomies ) {
	$enabled = array_intersect( (array) $taxonomies, wto_enabled_taxonomies() );
	if ( empty( $enabled ) ) {
		return $args;
	}
	// Respect an explicit orderby the developer passed.
	if ( isset( $args['orderby'] ) && 'term_order' !== $args['orderby'] && 'none' !== $args['orderby'] ) {
		return $args;
	}

	/**
	 * Filter whether the manual order is applied to this get_terms() call.
	 *
	 * @param bool   $apply      Whether to apply the manual order.
	 * @param array  $args       get_terms() arguments.
	 * @param array  $taxonomies Taxonomies being queried.
	 */
	if ( ! apply_filters( 'wto_apply_term_order', true, $args, $taxonomies ) ) {
		return $args;
	}

	$args['meta_key'] = WTO_META_KEY;
	$args['orderby']  = 'meta_value_num';
	$args['order']    = isset( $args['order'] ) ? $args['order'] : 'ASC';
	return $args;
}
add_filter( 'get_terms_args', 'wto_apply_term_order', 10, 2 );

/* ------------------------------------------------------------------ */
/*  Settings page                                                      */
/* ------------------------------------------------------------------ */

/**
 * Register the Settings → Term Order page.
 */
function wto_register_settings_page() {
	add_options_page(
		__( 'Term Order', 'wp-term-order' ),
		__( 'Term Order', 'wp-term-order' ),
		'manage_options',
		'wp-term-order',
		'wto_render_settings_page'
	);
}
add_action( 'admin_menu', 'wto_register_settings_page' );

function wto_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-term-order' ) );
	}

	if ( isset( $_POST['wto_save'] ) ) {
		check_admin_referer( 'wto_save_settings' );
		$chosen = isset( $_POST['wto_taxonomies'] ) ? (array) wp_unslash( $_POST['wto_taxonomies'] ) : array();
		$chosen = array_values( array_intersect( array_map( 'sanitize_key', $chosen ), get_taxonomies( array( 'public' => true ) ) ) );
		update_option(
			WTO_OPTION,
			array(
				'taxonomies' => $chosen,
			)
		);
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wp-term-order' ) . '</p></div>';
	}

	$settings = wto_get_settings();
	$all      = get_taxonomies( array( 'public' => true ), 'objects' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Term Order', 'wp-term-order' ); ?></h1>
		<p><?php esc_html_e( 'Choose which taxonomies get drag-and-drop ordering. On each taxonomy list screen, drag rows to reorder — the front end (get_terms, wp_list_categories and widgets) follows the same order automatically.', 'wp-term-order' ); ?></p>
		<form method="post" action="">
			<?php wp_nonce_field( 'wto_save_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Ordered taxonomies', 'wp-term-order' ); ?></th>
					<td>
						<?php foreach ( $all as $tax ) : ?>
							<label>
								<input type="checkbox" name="wto_taxonomies[]" value="<?php echo esc_attr( $tax->name ); ?>" <?php checked( in_array( $tax->name, (array) $settings['taxonomies'], true ) ); ?> />
								<?php echo esc_html( $tax->labels->name . ' (' . $tax->name . ')' ); ?>
							</label><br />
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Terms without an order value yet get one automatically the first time they are listed.', 'wp-term-order' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Settings', 'wp-term-order' ), 'primary', 'wto_save' ); ?>
		</form>
	</div>
	<?php
}

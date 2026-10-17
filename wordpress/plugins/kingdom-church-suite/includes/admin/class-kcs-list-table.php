<?php
/**
 * Generic, config-driven list table.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table.
 */
class KCS_List_Table extends WP_List_Table {

	/**
	 * Config: columns, sortable, render (callable col,item), query (callable args → items/total), bulk, views, singular, plural.
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param array $config Config.
	 */
	public function __construct( $config ) {
		$this->config = wp_parse_args(
			$config,
			array(
				'singular' => 'item',
				'plural'   => 'items',
				'columns'  => array(),
				'sortable' => array(),
				'bulk'     => array(),
				'views'    => array(),
				'per_page' => 20,
			)
		);
		parent::__construct(
			array(
				'singular' => $this->config['singular'],
				'plural'   => $this->config['plural'],
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		$cols = $this->config['columns'];
		if ( $this->config['bulk'] ) {
			$cols = array( 'cb' => '<input type="checkbox">' ) + $cols;
		}
		return $cols;
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		$out = array();
		foreach ( $this->config['sortable'] as $col ) {
			$out[ $col ] = array( $col, false );
		}
		return $out;
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return $this->config['bulk'];
	}

	/**
	 * Bulk action dropdown. Named kcs_bulk / kcs_bulk2 (instead of WP's "action")
	 * so it does not clash with admin-post.php routing.
	 *
	 * @param string $which top|bottom.
	 */
	protected function bulk_actions( $which = '' ) {
		$actions = $this->get_bulk_actions();
		if ( ! $actions ) {
			return;
		}
		$name = 'top' === $which ? 'kcs_bulk' : 'kcs_bulk2';
		echo '<label for="' . esc_attr( $name ) . '" class="screen-reader-text">' . esc_html__( 'Select bulk action', 'kingdom-church-suite' ) . '</label>';
		echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '"><option value="-1">' . esc_html__( 'Bulk actions', 'kingdom-church-suite' ) . '</option>';
		foreach ( $actions as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Apply', 'kingdom-church-suite' ), 'action', '', false, array( 'id' => 'doaction' . ( 'top' === $which ? '' : '2' ) ) );
	}

	/**
	 * Status views.
	 *
	 * @return array
	 */
	protected function get_views() {
		return $this->config['views'];
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="ids[]" value="' . (int) $item->id . '">';
	}

	/**
	 * Default column renderer.
	 *
	 * @param object $item        Item.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return call_user_func( $this->config['render'], $column_name, $item );
	}

	/**
	 * Fetch rows.
	 */
	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$args = array(
			'search'   => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
			'status'   => isset( $_REQUEST['status'] ) ? sanitize_key( $_REQUEST['status'] ) : '',
			'orderby'  => isset( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : '',
			'order'    => isset( $_REQUEST['order'] ) ? sanitize_key( $_REQUEST['order'] ) : '',
			'page'     => $this->get_pagenum(),
			'per_page' => $this->config['per_page'],
		);
		// phpcs:enable
		$result                = call_user_func( $this->config['query'], $args );
		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $this->config['per_page'],
			)
		);
	}

	/**
	 * Empty message.
	 */
	public function no_items() {
		esc_html_e( 'Nothing found.', 'kingdom-church-suite' );
	}
}

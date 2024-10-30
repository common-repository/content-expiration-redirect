<?php
/*
Plugin Name: Content Expiration & Redirect
Plugin URL: https://reviewsquirrel.com/tools/content-expiration-redirect-plugin/
Description: A simple plugin that lets you schedule posts or pages to expire - and where you would like the post or page to redirect to when it has expired.
Version: 1.0
Author: Review Squirrel
Author URI: https://reviewsquirrel.com/

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
	
You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA.
*/
new ExpiringPosts();
class ExpiringPosts {

	public function __construct() {
		load_plugin_textdomain('postexpiring', false, basename( dirname( __FILE__ ) ) . '/languages' );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'add_expiring_field') );

		add_action( 'save_post', array( $this, 'save_post_meta' ), 10, 2 );	
		
		add_filter( 'manage_post_posts_columns', array( $this, 'manage_posts_columns' ), 5 );
		add_action( 'manage_post_posts_custom_column', array( $this, 'manage_posts_custom_column' ), 5, 2 );	
		
		add_filter( 'manage_page_posts_columns', array( $this, 'manage_posts_columns' ), 5 );
		add_action( 'manage_page_posts_custom_column', array( $this, 'manage_posts_custom_column' ), 5, 2 );
		
		add_filter( 'posts_clauses', array( $this, 'posts_clauses' ), 10, 2 );
		
		add_filter( 'get_next_post_join', array( $this, 'posts_join_clauses' ), 10, 2 );
		add_filter( 'get_previous_post_join', array( $this, 'posts_join_clauses' ), 10, 2 );
		add_filter( 'get_next_post_where', array( $this, 'posts_where_clauses' ), 10, 2 );
		add_filter( 'get_previous_post_where', array( $this, 'posts_where_clauses' ), 10, 2 );
		add_action('template_redirect', array($this, 'check_404'), 1);
		
		session_start();
	}
	
	public function check_404()
	{
		global $wpdb;
		$_SESSION['disable_posts_clauses'] = true;
		if (isset($_SESSION['posts_clauses_query']))
		{
			$query = $_SESSION['posts_clauses_query'];
			$id = $query->queried_object->ID;
			$redirect_url = get_post_meta($id,'post_expired_redirect_url', 1);
			$_SESSION['disable_posts_clauses'] = null;
			unset($_SESSION['disable_posts_clauses']);
			unset($_SESSION['posts_clauses_query']);

			if (is_404()) {

				if (!empty($redirect_url))
				{
					$method = get_post_meta($id, 'post_redirection_method', true);
					$method = (!empty($method) ? $method : 301);
					status_header($method);
					return wp_redirect($redirect_url, $method);
				}
			}
		}
	}

	public function posts_join_clauses( $join ) {
		global $wpdb;
		if (isset($_SESSION['disable_posts_clauses']) && $_SESSION['disable_posts_clauses']) return $join; // Ignore
		$join .= " LEFT JOIN $wpdb->postmeta AS exp ON (p.ID = exp.post_id AND exp.meta_key = 'postexpired')";
		return $join;
	}
	
	public function posts_where_clauses( $where ) {
		global $wpdb, $wp_session;
		if (isset($_SESSION['disable_posts_clauses']) && $_SESSION['disable_posts_clauses']) return $where;
			$current_date = current_time( 'mysql' );
		$where .= " AND ( (exp.meta_key = 'postexpired' AND CAST(exp.meta_value AS CHAR) > '".$current_date."') OR exp.post_id IS NULL ) ";
		return $where;
	}
	
	public function posts_clauses( $clauses, $query ) {
		global $wpdb;
		if (isset($_SESSION['disable_posts_clauses']) && $_SESSION['disable_posts_clauses']) return $clauses;
		if ( is_admin() AND ( !$query->is_main_query() || !is_feed() ) ) return $clauses;
		$_SESSION['posts_clauses_query'] = $query;
		$current_date = current_time( 'mysql' );
		$clauses['join'] .= " LEFT JOIN $wpdb->postmeta AS exp ON ($wpdb->posts.ID = exp.post_id AND exp.meta_key = 'postexpired') ";
		$clauses['where'] .= " AND ( (exp.meta_key = 'postexpired' AND CAST(exp.meta_value AS CHAR) > '".$current_date."') OR exp.post_id IS NULL ) ";

		return $clauses;
	}
	
	public function enqueue_scripts( $hook ) {
		if( 'post-new.php' != $hook AND 'post.php' != $hook ) return;
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'datetimepicker', plugins_url('assets/js/jquery.datetimepicker.js', __FILE__), array('jquery'), null, true );
		wp_enqueue_script( 'post-expiring', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), null, true );
		wp_enqueue_style( 'post-expiring', plugins_url('assets/css/post-expiring.css', __FILE__) );
	}
		
	public function manage_posts_columns( $columns ){
		$columns['expiring'] = __( 'Expiring', 'postexpiring' );
		return $columns;
	}
	
	public function manage_posts_custom_column( $column_name, $id ){
		global $post;
		if( $column_name === 'expiring' ){
			$postexpired = get_post_meta( $post->ID, 'postexpired', true );
			if( preg_match("/^\d{4}-\d{2}-\d{2}$/", $postexpired) ) {
				$postexpired .= ' 00:00';
			}
			echo !empty($postexpired) ? $postexpired : __('Never');
		}
	}
	
	public function save_post_meta( $post_id, $post ) {
		if ( $post_id === null || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) return;
		if( isset($_POST['post_expiring']) ) {
			preg_match("/\d{4}\-\d{2}-\d{2} \d{2}:\d{2}/", $_POST['post_expiring'], $expired);
			if( empty( $expired ) ) {
				delete_post_meta( $post_id, 'postexpired' );
			}
			if ( !empty($_POST['post_expiring']) AND isset( $expired[0] ) ) {
				add_post_meta( $post_id, 'postexpired', esc_sql( $_POST['post_expiring'] ), true ) || update_post_meta( $post_id, 'postexpired', esc_sql( $_POST['post_expiring'] ) );
			}
		}

		if (isset($_POST['post_expiring_redirect_url']))
		{
			$url = filter_var($_POST['post_expiring_redirect_url'], FILTER_SANITIZE_URL);

			if (empty($url)) {
				delete_post_meta($post_id, 'post_expired_redirect_url');
			}
			else
			{
				add_post_meta($post_id, 'post_expired_redirect_url', esc_sql($url), true) || update_post_meta($post_id, 'post_expired_redirect_url', esc_sql($url));
			}
		}

		if (isset($_POST['redirection_method']))
		{
			$method = filter_var($_POST['redirection_method'], FILTER_SANITIZE_NUMBER_INT);

			if (empty($method))
			{
				delete_post_meta($post_id, 'post_redirection_method');
			}
			else {
				add_post_meta($post_id, 'post_redirection_method', esc_sql($method), true) || update_post_meta($post_id, 'post_redirection_method', esc_sql($method));
			}

		}
	}
	
	public function add_expiring_field() {
		
		global $post;
		if( !$post->post_type OR ( $post->post_type != 'page' AND $post->post_type != 'post' ) ) return;
		$screen = get_current_screen();
		if( $screen->base != 'post' ) return;
		$postexpired = get_post_meta( $post->ID, 'postexpired', true );
		$post_redirect_url = get_post_meta($post->ID, 'post_expired_redirect_url', true);
		$post_redirect_method = get_post_meta($post->ID, 'post_redirection_method', true);
		if( preg_match("/^\d{4}-\d{2}-\d{2}$/", $postexpired) ) {
			$postexpired .= ' 00:00';
		}
		$lang = explode( '-', get_bloginfo( 'language' ) );
		$lang = isset($lang[0]) ? $lang[0] : 'en';
		?>
		<script>
		jQuery(document).ready( function($) {
			$('.expiring-datepicker').datetimepicker({
				format:'Y-m-d H:i',
				lang: '<?php echo $lang; ?>',
				timepickerScrollbar:false
			});
		})
		</script>
		<div class="misc-pub-section curtime misc-pub-curtime">
			<span class="dashicons dashicons-clock"></span> <?php _e('Expire & Redirect:', 'postexpiring'); ?></span> <span class="setexpiringdate"><?php echo !empty($postexpired) ? $postexpired : __('Never'); ?></span>
			<a href="#edit_expiringdate" class="edit-expiringdate hide-if-no-js"><span aria-hidden="true"><?php _e( 'Edit' ); ?></span> <span class="screen-reader-text"><?php _e('Edit expiring date', 'postexpiring'); ?></span></a>

			<div id="expiringdatediv" class="hide-if-js">
				<div class="wrap"><input type="text" class="expiring-item expiring-datepicker" placeholder="Set Expiry Date/Time" data-exdate="<?php echo esc_attr($postexpired); ?>" value="<?php echo esc_attr($postexpired); ?>" size="26" name="post_expiring" />
				<span><input class="expiring-item" placeholder="Redirect to (eg http://wp.com)" size="26" type="url" value="<?php echo esc_attr($post_redirect_url) ?>" name="post_expiring_redirect_url" />
					<select class="expiring-item" name="redirection_method">
						<option <?php echo ($post_redirect_method == 301) ? 'selected' : ''; ?> value="301">Permanent Redirect ("301")</option>
						<option <?php echo ($post_redirect_method == 302) ? 'selected' : ''; ?> value="302">Temporary Redirect ("302")</option>
					</select>
				</div>
				<a class="set-expiringdate hide-if-no-js button" href="#edit_expiringdate"><?php _e('OK'); ?></a>
				<div><a class="cancel-expiringdate hide-if-no-js button-cancel" href="#edit_expiringdate"><?php _e('Cancel'); ?></a></div>
			</div>
		</div>
		<?php
	}
}
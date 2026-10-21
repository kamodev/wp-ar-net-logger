<?php
/**
 * Net permalinks.
 *
 * Every net gets its own address under a base path (default "net"):
 *
 *   /net/                                   Directory of nets
 *   /net/colorado-preparedness-net/         The net: schedule, details, upcoming sessions
 *   /net/colorado-preparedness-net/?p=134   One session of the net (details, check-in)
 *
 * The base path is a real WordPress page (created on activation, containing
 * [arnl_net]), so it works with any theme and its slug is the permalink base.
 * Sessions are addressed by their stored ID; regular sessions that have not
 * been stored yet use their date instead (?p=20261028).
 *
 * @package AR_Net_Logger
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rewrite rules, URL builders and request handling for net pages.
 */
class ARNL_Permalinks {

	const QV_NET     = 'arnl_net_page';
	const QV_SESSION = 'arnl_session';
	const OPTION     = 'arnl_net_page_id';

	/**
	 * Request cache of the host page ID (null = not resolved yet).
	 *
	 * @var int|null
	 */
	protected static $page_id = null;

	/**
	 * Resolved net / session of the current request.
	 *
	 * @var array|null
	 */
	protected static $current = null;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_create_page' ), 5 );
		add_action( 'init', array( __CLASS__, 'add_rules' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_flush' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'request', array( __CLASS__, 'request' ) );
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ), 5 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'redirect_canonical' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'document_title' ) );
		add_filter( 'get_canonical_url', array( __CLASS__, 'canonical_url' ), 10, 2 );
		add_action( 'post_updated', array( __CLASS__, 'page_updated' ), 10, 3 );
		add_action( 'trashed_post', array( __CLASS__, 'page_removed' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'page_removed' ) );
		add_action( 'deleted_post', array( __CLASS__, 'page_removed' ) );
	}

	/**
	 * ID of the published page that hosts net pages, or 0.
	 *
	 * @return int
	 */
	public static function page_id() {
		if ( null === self::$page_id ) {
			$id            = (int) get_option( self::OPTION );
			self::$page_id = $id && 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ? $id : 0;
		}
		return self::$page_id;
	}

	/**
	 * Whether net permalinks are available.
	 *
	 * @return bool
	 */
	public static function available() {
		return self::page_id() > 0;
	}

	/**
	 * Permalink base path, e.g. "net" (the host page's path).
	 *
	 * @return string
	 */
	public static function base() {
		$id = self::page_id();
		return $id ? (string) get_page_uri( $id ) : '';
	}

	/**
	 * The host page's own slug (the last part of the base path).
	 *
	 * @return string
	 */
	public static function slug() {
		$id = self::page_id();
		return $id ? (string) get_post_field( 'post_name', $id ) : '';
	}

	/**
	 * Whether pretty permalinks are enabled.
	 *
	 * @return bool
	 */
	public static function pretty() {
		return '' !== (string) get_option( 'permalink_structure' );
	}

	/**
	 * Create the host page if it does not exist (or was trashed).
	 *
	 * @param string $slug Desired slug for a new page.
	 * @return int Page ID.
	 */
	public static function ensure_page( $slug = 'net' ) {
		$id   = (int) get_option( self::OPTION );
		$post = $id ? get_post( $id ) : null;
		if ( $post && 'page' === $post->post_type && 'trash' !== $post->post_status ) {
			return $id;
		}
		$id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => __( 'Nets', 'ar-net-logger' ),
				'post_name'      => sanitize_title( $slug ),
				'post_content'   => "<!-- wp:shortcode -->\n[arnl_net]\n<!-- /wp:shortcode -->",
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		update_option( self::OPTION, (int) $id );
		self::$page_id = null;
		self::schedule_flush();
		return (int) $id;
	}

	/**
	 * Create the host page after an install or upgrade asked for it.
	 */
	public static function maybe_create_page() {
		if ( get_option( 'arnl_create_page' ) ) {
			delete_option( 'arnl_create_page' );
			self::ensure_page();
		}
	}

	/**
	 * Change the permalink base (the host page's slug).
	 *
	 * @param string $slug New slug.
	 * @return string The base actually in effect (WordPress keeps slugs unique).
	 */
	public static function set_base( $slug ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return self::base();
		}
		$id = self::ensure_page( $slug );
		if ( $id && get_post_field( 'post_name', $id ) !== $slug ) {
			wp_update_post(
				array(
					'ID'        => $id,
					'post_name' => $slug,
				)
			);
		}
		if ( $id && 'publish' !== get_post_status( $id ) ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);
		}
		self::$page_id = null;
		self::schedule_flush();
		return self::base();
	}

	/**
	 * Ask for the rewrite rules to be rebuilt at the end of the next load.
	 */
	public static function schedule_flush() {
		update_option( 'arnl_flush_rewrite', 1 );
	}

	/**
	 * Rebuild the rewrite rules when asked to (after all plugins added theirs).
	 */
	public static function maybe_flush() {
		if ( get_option( 'arnl_flush_rewrite' ) ) {
			delete_option( 'arnl_flush_rewrite' );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Add the /{base}/{net-slug}/ rewrite rule.
	 */
	public static function add_rules() {
		$id = self::page_id();
		if ( ! $id ) {
			return;
		}
		add_rewrite_rule(
			'^' . preg_quote( self::base(), '#' ) . '/([^/]+)/?$',
			'index.php?page_id=' . $id . '&' . self::QV_NET . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Public query vars.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QV_NET;
		$vars[] = self::QV_SESSION;
		return $vars;
	}

	/**
	 * On net pages "?p=" is the session, not a WordPress post ID.
	 *
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	public static function request( $query_vars ) {
		if ( empty( $query_vars[ self::QV_NET ] ) || ! self::available() ) {
			return $query_vars;
		}
		if ( isset( $query_vars['p'] ) ) {
			if ( empty( $query_vars[ self::QV_SESSION ] ) ) {
				$query_vars[ self::QV_SESSION ] = $query_vars['p'];
			}
			unset( $query_vars['p'] );
		}
		$query_vars['page_id'] = self::page_id();
		unset( $query_vars['pagename'], $query_vars['name'] );
		return $query_vars;
	}

	/**
	 * Whether the current request is for a net page.
	 *
	 * @return bool
	 */
	public static function is_net_request() {
		return '' !== (string) get_query_var( self::QV_NET ) && self::available();
	}

	/**
	 * Net and session requested on the current net page.
	 *
	 * @return array{net:array|null,occurrence:ARNL_Occurrence|null,session_requested:bool}
	 */
	public static function current() {
		if ( null === self::$current ) {
			$net        = null;
			$occurrence = null;
			$session    = (string) get_query_var( self::QV_SESSION );
			if ( self::is_net_request() ) {
				$net = ARNL_Nets::get_by_slug( sanitize_title( (string) get_query_var( self::QV_NET ) ) );
				if ( $net && 'active' !== $net['status'] ) {
					$net = null;
				}
				if ( $net && '' !== $session ) {
					$occurrence = self::find_session( $net, $session );
				}
			}
			self::$current = array(
				'net'               => $net,
				'occurrence'        => $occurrence,
				'session_requested' => '' !== $session,
			);
		}
		return self::$current;
	}

	/**
	 * Resolve a "?p=" value of a net: a stored session ID, or a date (YYYYMMDD)
	 * for a regular session that has not been stored yet.
	 *
	 * @param array  $net Net.
	 * @param string $id  Value.
	 * @return ARNL_Occurrence|null
	 */
	public static function find_session( array $net, $id ) {
		$id = trim( (string) $id );
		if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $id, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return ARNL_Schedule::find_by_date( $net['id'], $m[1] . '-' . $m[2] . '-' . $m[3] );
		}
		if ( ctype_digit( $id ) ) {
			$occurrence = ARNL_Schedule::find( 'e' . $id );
			return $occurrence && $occurrence->net_id === (int) $net['id'] ? $occurrence : null;
		}
		return null;
	}

	/**
	 * Unknown nets and sessions are 404s.
	 */
	public static function template_redirect() {
		if ( ! self::is_net_request() ) {
			return;
		}
		$current = self::current();
		if ( ! $current['net'] || ( $current['session_requested'] && ! $current['occurrence'] ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * Do not let WordPress "correct" net URLs to the host page or a post.
	 *
	 * @param string|false $redirect Redirect URL.
	 * @return string|false
	 */
	public static function redirect_canonical( $redirect ) {
		return self::is_net_request() ? false : $redirect;
	}

	/**
	 * Browser title: net name (and session date).
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public static function document_title( $parts ) {
		if ( self::is_net_request() && ! is_404() ) {
			$current = self::current();
			if ( $current['occurrence'] ) {
				/* translators: 1: net name, 2: session date. */
				$parts['title'] = sprintf( __( '%1$s – %2$s', 'ar-net-logger' ), $current['net']['name'], arnl_format_date( $current['occurrence']->date ) );
			} elseif ( $current['net'] ) {
				$parts['title'] = $current['net']['name'];
			}
		}
		return $parts;
	}

	/**
	 * Canonical URL of net and session pages.
	 *
	 * @param string  $url  Canonical URL.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function canonical_url( $url, $post ) {
		if ( self::is_net_request() && $post && self::page_id() === (int) $post->ID ) {
			$current = self::current();
			if ( $current['occurrence'] ) {
				return self::session_url( $current['occurrence'] );
			}
			if ( $current['net'] ) {
				return self::net_url( $current['net'] );
			}
		}
		return $url;
	}

	/**
	 * URL of the nets directory (the host page), or ''.
	 *
	 * @return string
	 */
	public static function directory_url() {
		$id = self::page_id();
		return $id ? (string) get_permalink( $id ) : '';
	}

	/**
	 * URL of a net's page, or '' when net permalinks are unavailable.
	 *
	 * @param array $net Net.
	 * @return string
	 */
	public static function net_url( array $net ) {
		$id = self::page_id();
		if ( ! $id || empty( $net['slug'] ) ) {
			return '';
		}
		if ( self::pretty() ) {
			return home_url( user_trailingslashit( self::base() . '/' . $net['slug'] ) );
		}
		return add_query_arg(
			array(
				'page_id'    => $id,
				self::QV_NET => $net['slug'],
			),
			home_url( '/' )
		);
	}

	/**
	 * URL of one session: /net/{slug}/?p={id}, or '' when unavailable.
	 *
	 * @param ARNL_Occurrence $occurrence Session.
	 * @return string
	 */
	public static function session_url( ARNL_Occurrence $occurrence ) {
		$url = self::net_url( $occurrence->net );
		if ( '' === $url ) {
			return '';
		}
		return add_query_arg( self::pretty() ? 'p' : self::QV_SESSION, $occurrence->url_id(), $url );
	}

	/**
	 * Rebuild rules when the host page's slug, parent or status changes.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $after   Post after the update.
	 * @param WP_Post $before  Post before the update.
	 */
	public static function page_updated( $post_id, $after, $before ) {
		if ( (int) get_option( self::OPTION ) !== (int) $post_id ) {
			return;
		}
		if ( $after->post_name !== $before->post_name || $after->post_parent !== $before->post_parent || $after->post_status !== $before->post_status ) {
			self::$page_id = null;
			self::schedule_flush();
		}
	}

	/**
	 * Rebuild rules when the host page is trashed, restored or deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function page_removed( $post_id ) {
		if ( (int) get_option( self::OPTION ) === (int) $post_id ) {
			self::$page_id = null;
			self::schedule_flush();
		}
	}
}

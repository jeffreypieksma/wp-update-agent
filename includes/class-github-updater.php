<?php
/**
 * GitHub-based auto-updater for private repositories.
 *
 * Checks the GitHub Releases API for new versions and injects
 * update data into the WordPress plugin update transient.
 *
 * Token priority:
 *   1. Encrypted token in database (set via plugin admin UI).
 *   2. WP_UPDATE_AGENT_GITHUB_TOKEN constant in wp-config.php (fallback).
 *
 * @package WP_Update_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Update_Agent_GitHub_Updater {

	/** @var string GitHub repo slug (owner/repo). */
	private string $repo;

	/** @var string Absolute path to the main plugin file. */
	private string $plugin_file;

	/** @var string Plugin basename (e.g. wp-update-agent/wp-update-agent.php). */
	private string $basename;

	/** @var string Current plugin version. */
	private string $version;

	/** @var string Plugin slug. */
	private string $slug = 'wp-update-agent';

	/** @var string DB option key for encrypted token. */
	private static string $option_key = 'wp_update_agent_github_token';

	/** @var string Transient key for caching API responses. */
	private string $cache_key = 'wp_update_agent_github_update';

	/** @var int Cache TTL in seconds (12 hours). */
	private int $cache_ttl = 43200;

	/**
	 * @param string $repo        GitHub owner/repo slug.
	 * @param string $plugin_file Main plugin file (__FILE__).
	 * @param string $basename    plugin_basename( __FILE__ ).
	 */
	public function __construct( string $repo, string $plugin_file, string $basename ) {
		$this->repo        = $repo;
		$this->plugin_file = $plugin_file;
		$this->basename    = $basename;
		$this->version     = WP_UPDATE_AGENT_VERSION;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'post_install' ), 10, 3 );
		add_filter( 'http_request_args', array( $this, 'add_auth_header' ), 10, 2 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/*--------------------------------------------------------------
	 * REST API: Token management
	 *------------------------------------------------------------*/

	public function register_rest_routes(): void {
		register_rest_route( 'wp-update-agent/v1', '/github-token', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_token' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_save_token' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args' => array(
					'token' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'rest_delete_token' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			),
		) );
	}

	public function rest_get_token(): WP_REST_Response {
		return new WP_REST_Response( array(
			'has_token' => self::has_token(),
			'masked'    => self::get_masked_token(),
			'source'    => self::get_token_source(),
		) );
	}

	public function rest_save_token( WP_REST_Request $request ): WP_REST_Response {
		$token = $request->get_param( 'token' );

		if ( empty( $token ) ) {
			return new WP_REST_Response( array( 'error' => 'Token is required.' ), 400 );
		}

		$saved = self::save_token( $token );

		if ( ! $saved ) {
			return new WP_REST_Response( array( 'error' => 'Could not save token.' ), 500 );
		}

		return new WP_REST_Response( array(
			'has_token' => true,
			'masked'    => self::get_masked_token(),
			'source'    => 'db',
		) );
	}

	public function rest_delete_token(): WP_REST_Response {
		self::delete_token();

		return new WP_REST_Response( array(
			'has_token' => self::has_token(),
			'masked'    => self::get_masked_token(),
			'source'    => self::get_token_source(),
		) );
	}

	/*--------------------------------------------------------------
	 * 1. Inject update data into WP transient
	 *------------------------------------------------------------*/

	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = ltrim( $release['tag_name'], 'vV' );

		if ( version_compare( $remote_version, $this->version, '>' ) ) {
			$transient->response[ $this->basename ] = (object) array(
				'slug'         => $this->slug,
				'plugin'       => $this->basename,
				'new_version'  => $remote_version,
				'url'          => "https://github.com/{$this->repo}",
				'package'      => $release['zipball_url'],
				'icons'        => array(),
				'banners'      => array(),
				'tested'       => '',
				'requires'     => '5.9',
				'requires_php' => '7.4',
			);
		}

		return $transient;
	}

	/*--------------------------------------------------------------
	 * 2. Plugin information popup ("View details")
	 *------------------------------------------------------------*/

	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( ! isset( $args->slug ) || $this->slug !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$remote_version = ltrim( $release['tag_name'], 'vV' );
		$plugin_data    = get_plugin_data( $this->plugin_file );

		return (object) array(
			'name'           => $plugin_data['Name'] ?? 'WP Sentinel Agent',
			'slug'           => $this->slug,
			'version'        => $remote_version,
			'author'         => $plugin_data['AuthorName'] ?? 'Jeffrey Pieksma',
			'author_profile' => $plugin_data['AuthorURI'] ?? '',
			'homepage'       => $plugin_data['PluginURI'] ?? '',
			'requires'       => '5.9',
			'requires_php'   => '7.4',
			'tested'         => '',
			'download_link'  => $release['zipball_url'],
			'trunk'          => $release['zipball_url'],
			'last_updated'   => $release['published_at'] ?? '',
			'sections'       => array(
				'description' => $plugin_data['Description'] ?? '',
				'changelog'   => nl2br( esc_html( $release['body'] ?? '' ) ),
			),
		);
	}

	/*--------------------------------------------------------------
	 * 3. Fix directory name after install
	 *------------------------------------------------------------*/

	public function post_install( $response, $hook_extra, $result ) {
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $response;
		}

		global $wp_filesystem;

		$proper_dir = WP_PLUGIN_DIR . '/' . dirname( $this->basename );
		$wp_filesystem->move( $result['destination'], $proper_dir );
		$result['destination'] = $proper_dir;

		activate_plugin( $this->basename );

		return $result;
	}

	/*--------------------------------------------------------------
	 * 4. Auth header for private repo downloads
	 *------------------------------------------------------------*/

	public function add_auth_header( $args, $url ) {
		$token = $this->get_token();
		if ( ! $token ) {
			return $args;
		}

		if ( strpos( $url, 'github.com/' . $this->repo ) !== false
			|| strpos( $url, 'api.github.com/repos/' . $this->repo ) !== false ) {
			$args['headers']['Authorization'] = 'token ' . $token;
			$args['headers']['Accept']        = 'application/vnd.github.v3+json';
		}

		return $args;
	}

	/*--------------------------------------------------------------
	 * Internal helpers
	 *------------------------------------------------------------*/

	private function get_latest_release(): ?array {
		$token = $this->get_token();

		if ( $token ) {
			$cached = get_transient( $this->cache_key );
			if ( false !== $cached ) {
				return $cached ?: null;
			}
		}

		if ( ! $token ) {
			return null;
		}

		$url = "https://api.github.com/repos/{$this->repo}/releases/latest";

		$response = wp_remote_get( $url, array(
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'token ' . $token,
				'Accept'        => 'application/vnd.github.v3+json',
				'User-Agent'    => 'WP-Update-Agent-Updater/' . $this->version,
			),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $this->cache_key, '', $this->cache_ttl );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			set_transient( $this->cache_key, '', $this->cache_ttl );
			return null;
		}

		set_transient( $this->cache_key, $body, $this->cache_ttl );

		return $body;
	}

	private function get_token(): string {
		$db_token = self::get_saved_token();
		if ( $db_token ) {
			return $db_token;
		}

		return defined( 'WP_UPDATE_AGENT_GITHUB_TOKEN' ) ? WP_UPDATE_AGENT_GITHUB_TOKEN : '';
	}

	/*--------------------------------------------------------------
	 * Token encryption / storage (static helpers for admin UI)
	 *------------------------------------------------------------*/

	public static function save_token( string $token ): bool {
		if ( empty( $token ) ) {
			return self::delete_token();
		}

		$encrypted = self::encrypt( $token );
		if ( false === $encrypted || '' === $encrypted ) {
			return false;
		}

		delete_transient( 'wp_update_agent_github_update' );

		return update_option( self::$option_key, $encrypted, false );
	}

	public static function delete_token(): bool {
		delete_transient( 'wp_update_agent_github_update' );
		return delete_option( self::$option_key );
	}

	public static function get_saved_token(): string {
		$encrypted = get_option( self::$option_key, '' );
		if ( empty( $encrypted ) ) {
			return '';
		}

		$decrypted = self::decrypt( $encrypted );
		return $decrypted ?: '';
	}

	public static function has_token(): bool {
		if ( self::get_saved_token() ) {
			return true;
		}
		return defined( 'WP_UPDATE_AGENT_GITHUB_TOKEN' ) && ! empty( WP_UPDATE_AGENT_GITHUB_TOKEN );
	}

	public static function get_masked_token(): string {
		$token = self::get_saved_token();
		if ( ! $token && defined( 'WP_UPDATE_AGENT_GITHUB_TOKEN' ) ) {
			$token = WP_UPDATE_AGENT_GITHUB_TOKEN;
		}
		if ( empty( $token ) || strlen( $token ) < 8 ) {
			return '';
		}
		return substr( $token, 0, 4 ) . '••••••••' . substr( $token, -4 );
	}

	public static function get_token_source(): string {
		if ( self::get_saved_token() ) {
			return 'db';
		}
		if ( defined( 'WP_UPDATE_AGENT_GITHUB_TOKEN' ) && ! empty( WP_UPDATE_AGENT_GITHUB_TOKEN ) ) {
			return 'config';
		}
		return '';
	}

	private static function encrypt( string $plaintext ): string {
		$key    = self::get_encryption_key();
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return '';
		}

		return base64_encode( $iv . $cipher );
	}

	private static function decrypt( string $data ): string {
		$key     = self::get_encryption_key();
		$decoded = base64_decode( $data, true );

		if ( false === $decoded || strlen( $decoded ) < 17 ) {
			return '';
		}

		$iv         = substr( $decoded, 0, 16 );
		$ciphertext = substr( $decoded, 16 );
		$plaintext  = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return $plaintext ?: '';
	}

	private static function get_encryption_key(): string {
		$salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wp-update-agent-fallback-key';
		return hash( 'sha256', $salt, true );
	}
}

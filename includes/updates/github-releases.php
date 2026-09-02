<?php
/**
 * GitHub Releases updates for NovaStream plugins.
 *
 * @package NovaStreamThemeHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register a plugin hosted in a GitHub repository.
 *
 * @param array $args Plugin file, current version, and owner/repository pair.
 * @return void
 */
function novastream_register_github_plugin_update( $args ) {
	$defaults = array(
		'plugin_file' => '',
		'version'     => '',
		'repository'  => '',
	);
	$args     = wp_parse_args( $args, $defaults );
	$slug     = dirname( plugin_basename( $args['plugin_file'] ) );

	if ( ! $args['plugin_file'] || ! $args['version'] || ! preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $args['repository'] ) ) {
		return;
	}

	$args['slug']     = $slug;
	$args['basename'] = plugin_basename( $args['plugin_file'] );
	$args             = (array) apply_filters( 'novastream_github_plugin_update_args', $args, $slug );

	NovaStream_GitHub_Plugin_Updater::instance()->register( $args );
}

/**
 * Small release updater shared by NovaStream plugins.
 */
final class NovaStream_GitHub_Plugin_Updater {
	/** @var self|null */
	private static $instance = null;

	/** @var array<string,array> */
	private $plugins = array();

	/** @var array<string,array|WP_Error> */
	private $releases = array();

	/**
	 * Get the updater instance and attach hooks once.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_filter( 'pre_set_site_transient_update_plugins', array( self::$instance, 'check_updates' ) );
			add_filter( 'plugins_api', array( self::$instance, 'plugin_information' ), 20, 3 );
			add_filter( 'upgrader_source_selection', array( self::$instance, 'normalize_source_directory' ), 10, 4 );
		}

		return self::$instance;
	}

	/**
	 * Add a plugin configuration.
	 *
	 * @param array $args Plugin configuration.
	 * @return void
	 */
	public function register( $args ) {
		$this->plugins[ $args['basename'] ] = $args;
	}

	/**
	 * Add available releases to WordPress's update transient.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public function check_updates( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		foreach ( $this->plugins as $basename => $plugin ) {
			$release = $this->get_release( $plugin );
			if ( is_wp_error( $release ) || empty( $release['version'] ) ) {
				continue;
			}

			$update = $this->update_object( $plugin, $release );
			if ( version_compare( $plugin['version'], $release['version'], '<' ) ) {
				$transient->response[ $basename ] = $update;
			} else {
				$transient->no_update[ $basename ] = $update;
			}
		}

		return $transient;
	}

	/**
	 * Supply the modal shown by "View version details".
	 *
	 * @param false|object|array $result Existing result.
	 * @param string             $action API action.
	 * @param object             $args   API arguments.
	 * @return false|object|array
	 */
	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}

		foreach ( $this->plugins as $plugin ) {
			if ( $plugin['slug'] !== $args->slug ) {
				continue;
			}

			$release = $this->get_release( $plugin );
			if ( is_wp_error( $release ) ) {
				return $result;
			}

			return (object) array(
				'name'          => $release['name'],
				'slug'          => $plugin['slug'],
				'version'       => $release['version'],
				'author'        => '<a href="https://novastream.ca">NovaStream</a>',
				'homepage'      => 'https://github.com/' . $plugin['repository'],
				'requires'      => $release['requires'],
				'requires_php'  => $release['requires_php'],
				'last_updated'  => $release['published_at'],
				'download_link' => $release['package'],
				'sections'      => array(
					'description' => wpautop( esc_html( $release['description'] ) ),
					'changelog'   => wpautop( esc_html( $release['body'] ) ),
				),
			);
		}

		return $result;
	}

	/**
	 * Rename GitHub's generated archive directory to the stable plugin slug.
	 *
	 * @param string|WP_Error $source        Extracted source path.
	 * @param string          $remote_source Extraction root.
	 * @param WP_Upgrader     $upgrader      Upgrader instance.
	 * @param array           $hook_extra    Upgrade context.
	 * @return string|WP_Error
	 */
	public function normalize_source_directory( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( is_wp_error( $source ) || empty( $hook_extra['plugin'] ) || empty( $this->plugins[ $hook_extra['plugin'] ] ) ) {
			return $source;
		}

		$plugin = $this->plugins[ $hook_extra['plugin'] ];
		$target = trailingslashit( $remote_source ) . $plugin['slug'] . '/';
		if ( trailingslashit( $source ) === $target ) {
			return $source;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem || ! $wp_filesystem->move( $source, $target, true ) ) {
			return new WP_Error( 'novastream_github_source_directory', __( 'The GitHub plugin package could not be prepared for installation.', 'novastream-theme-helper' ) );
		}

		return $target;
	}

	/**
	 * Fetch and normalize the latest non-draft GitHub release.
	 *
	 * @param array $plugin Plugin configuration.
	 * @return array|WP_Error
	 */
	private function get_release( $plugin ) {
		if ( isset( $this->releases[ $plugin['repository'] ] ) ) {
			return $this->releases[ $plugin['repository'] ];
		}

		$cache_key = 'novastream_gh_' . md5( $plugin['repository'] );
		$cached    = get_site_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $this->releases[ $plugin['repository'] ] = $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . $plugin['repository'] . '/releases/latest',
			array(
				'timeout' => 15,
				'headers' => $this->github_headers(),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $this->releases[ $plugin['repository'] ] = $response;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $this->releases[ $plugin['repository'] ] = new WP_Error( 'novastream_github_release_failed', 'GitHub release lookup failed.' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) || empty( $data['zipball_url'] ) ) {
			return $this->releases[ $plugin['repository'] ] = new WP_Error( 'novastream_github_release_invalid', 'GitHub returned an invalid release.' );
		}

		$headers = get_file_data(
			$plugin['plugin_file'],
			array(
				'name'         => 'Plugin Name',
				'description'  => 'Description',
				'requires'     => 'Requires at least',
				'requires_php' => 'Requires PHP',
			)
		);
		$release = array(
			'name'         => $headers['name'],
			'description'  => $headers['description'],
			'version'      => ltrim( (string) $data['tag_name'], "vV \t\n\r\0\x0B" ),
			'package'      => esc_url_raw( $data['zipball_url'] ),
			'body'         => isset( $data['body'] ) ? (string) $data['body'] : '',
			'published_at' => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
			'requires'     => $headers['requires'],
			'requires_php' => $headers['requires_php'],
		);
		$release = (array) apply_filters( 'novastream_github_release_data', $release, $data, $plugin );
		set_site_transient( $cache_key, $release, 6 * HOUR_IN_SECONDS );

		return $this->releases[ $plugin['repository'] ] = $release;
	}

	/**
	 * Build WordPress's plugin update payload.
	 *
	 * @param array $plugin  Plugin configuration.
	 * @param array $release Release data.
	 * @return object
	 */
	private function update_object( $plugin, $release ) {
		return (object) array(
			'id'           => 'https://github.com/' . $plugin['repository'],
			'slug'         => $plugin['slug'],
			'plugin'       => $plugin['basename'],
			'new_version'  => $release['version'],
			'url'          => 'https://github.com/' . $plugin['repository'],
			'package'      => $release['package'],
			'requires'     => $release['requires'],
			'requires_php' => $release['requires_php'],
		);
	}

	/**
	 * GitHub API request headers.
	 *
	 * @return array
	 */
	private function github_headers() {
		return array(
			'Accept'               => 'application/vnd.github+json',
			'User-Agent'           => 'NovaStream-WordPress-Updater',
			'X-GitHub-Api-Version' => '2022-11-28',
		);
	}
}

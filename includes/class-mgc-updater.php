<?php

/**
 * Handles automatic updates from GitHub.
 */
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MGC_Updater {

	private $file;
	private $plugin;
	private $basename;
	private $active;
	private $username;
	private $repository;
	private $github_response;

	public function __construct( $file ) {
		$this->file = $file;
		$this->username = 'millaredos';
		$this->repository = 'wp-gdpr-comments';

		// Load requirements
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		// Set properties immediately to avoid race conditions (e.g. background CRON checks)
		$this->plugin   = get_plugin_data( $this->file );
		$this->basename = plugin_basename( $this->file );
		$this->active   = is_plugin_active( $this->basename );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
	}

	private function get_repository_info() {
		if ( null !== $this->github_response ) {
			return;
		}

		$url = "https://api.github.com/repos/{$this->username}/{$this->repository}/releases/latest";
		
		// GitHub API strictly requires a User-Agent
		$response = wp_remote_get( $url, array(
			'user-agent' => 'Millaredos-GDPR-Updater',
		) );

		if ( is_wp_error( $response ) ) {
			return;
		}

		$this->github_response = json_decode( wp_remote_retrieve_body( $response ) );
	}

	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$this->get_repository_info();

		if ( empty( $this->github_response ) || empty( $this->github_response->tag_name ) ) {
			return $transient;
		}

		// Clean and sanitize tags (e.g., v0.9.8 -> 0.9.8)
		$remote_version = ltrim( sanitize_text_field( $this->github_response->tag_name ), 'v' );

		if ( true === version_compare( $remote_version, $this->plugin['Version'], '>' ) ) {
			$obj = new stdClass();
			$obj->slug = $this->basename;
			$obj->new_version = $remote_version;
			$obj->url = esc_url_raw( $this->plugin['PluginURI'] );
			$obj->package = esc_url_raw( $this->github_response->zipball_url );

			// Add icons for the update page and details modal
			$obj->icons = array(
				'128x128' => MGC_URL . 'assets/logo.svg',
				'256x256' => MGC_URL . 'assets/logo.svg',
				'svg'     => MGC_URL . 'assets/logo.svg',
			);

			$transient->response[ $this->basename ] = $obj;
		}

		return $transient;
	}

	public function plugin_popup( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || $args->slug !== $this->basename ) {
			return $result;
		}

		$this->get_repository_info();

		if ( empty( $this->github_response ) ) {
			return $result;
		}

		$remote_version = ltrim( sanitize_text_field( $this->github_response->tag_name ), 'v' );

		$res = new stdClass();
		$res->name = $this->plugin['Name'];
		$res->slug = $this->basename;
		$res->version = $remote_version;
		$res->author = $this->plugin['AuthorName'];
		$res->homepage = esc_url_raw( $this->plugin['PluginURI'] );
		$res->download_link = esc_url_raw( $this->github_response->zipball_url );

		// Add icons to the modal header
		$res->icons = array(
			'128x128' => MGC_URL . 'assets/logo.svg',
			'256x256' => MGC_URL . 'assets/logo.svg',
			'svg'     => MGC_URL . 'assets/logo.svg',
		);

		$res->sections = array(
			'description' => $this->plugin['Description'],
			'changelog'   => ! empty( $this->github_response->body ) ? wp_kses_post( $this->github_response->body ) : __( 'No changelog available.', 'millaredos-gdpr-comments' ),
		);

		return $res;
	}

	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		// Verify result is not a WP_Error
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Initialize WP_Filesystem API correctly
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		
		if ( true === WP_Filesystem() ) {
			$install_directory = plugin_dir_path( $this->file );
			$wp_filesystem->move( $result['destination'], $install_directory );
			$result['destination'] = $install_directory;

			if ( true === $this->active ) {
				activate_plugin( $this->basename );
			}
		}

		return $result;
	}
}

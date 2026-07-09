<?php
declare(strict_types=1);

namespace WPMedia\MCP\OAuth\Auth\Discovery;

use WPMedia\MCP\OAuth\Context;

class Subscriber {
	/**
	 * Discovery endpoints handler
	 *
	 * @var Endpoints
	 */
	private $endpoints;

	/**
	 * OAuth server context.
	 *
	 * @var Context
	 */
	private $context;

	/**
	 * Subscriber constructor.
	 *
	 * @param Endpoints $endpoints The discovery endpoints handler.
	 * @param Context   $context   OAuth server context.
	 */
	public function __construct( Endpoints $endpoints, Context $context ) {
		$this->endpoints = $endpoints;
		$this->context   = $context;
	}

	/**
	 * Return the subscribed events.
	 *
	 * @return array<string, string|array>
	 */
	public static function get_subscribed_events(): array {
		return [
			'template_redirect' => 'handle_request',
			'query_vars'        => 'add_oauth_query_vars',
			'init'              => 'add_rewrite_rules',
		];
	}

	/**
	 * Add the OAuth query var to WordPress's list of recognised vars.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[] Modified list.
	 */
	public function add_oauth_query_vars( array $vars ): array {
		return $this->endpoints->add_oauth_query_vars( $vars );
	}

	/**
	 * Register rewrite rules for the .well-known paths.
	 *
	 * Called both on the 'init' action (normal requests) and directly during
	 * plugin activation before flush_rewrite_rules().
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		if ( ! $this->context->is_enabled() ) {
			return;
		}

		$this->endpoints->add_rewrite_rules();
	}

	/**
	 * Serve the discovery document if the request matches.
	 *
	 * @return void
	 */
	public function handle_request(): void {
		$discovery = (string) get_query_var( Endpoints::QUERY_VAR, '' );

		if ( '' === $discovery ) {
			return;
		}

		if ( ! $this->context->is_enabled() ) {
			$this->force_404();
			return;
		}

		$this->endpoints->handle_request();
	}

	/**
	 * Force a clean 404 response.
	 *
	 * Used when a stale rewrite rule still routes a request to this endpoint
	 * after the OAuth server has been disabled, before rewrite rules have
	 * been flushed. Without this, WordPress's main query would fall through
	 * to the homepage instead of returning a 404.
	 *
	 * @return void
	 */
	private function force_404(): void {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
	}
}

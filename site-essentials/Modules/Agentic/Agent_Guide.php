<?php
/**
 * Agentic — Agent Guide (shortcodes for the /mcp/ guide page)
 *
 * v1.0 | 2026-11-25 — #272
 *
 * The /mcp/ page explains to people and to AI assistants what an assistant
 * can do on this site. Most of that depends on the site: whether agent
 * enquiries reach the business before or after the person confirms
 * (scos_agentic_enquiry_delivery), the enquiry form's own fields, which kinds
 * of content exist, and the site's own addresses. Typed into each page it
 * goes wrong as soon as the page is copied or a setting changes, so these
 * shortcodes write it from the live settings and content instead:
 *
 *   [scos_agent_capabilities]   What an assistant can and can't do here.
 *   [scos_agent_enquiry_howto]  How to build the pre-filled link with this
 *                               form's fields, and how to send an enquiry.
 *   [scos_agent_tool_examples]  The four read-only tools with "Try it" links
 *                               to real pages. show="prompts" gives example
 *                               prompts for a desktop AI app instead.
 *   [scos_agent_connector]      The MCP connector address and how to add it.
 *
 * [scos_agent_privacy] and [scos_agent_enquiry_hint] (Agent_Notices) cover
 * the rest. Each renders nothing when the feature it describes is off.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

use SiteEssentials\Core\Cache_Helper;

defined( 'ABSPATH' ) || exit;

class Agent_Guide {

	const CACHE_GROUP = 'agentic_guide';

	/** Content purposes worth naming, and how to say each one. */
	const PURPOSES = [
		'service-page' => 'services',
		'product-page' => 'products',
		'case-study'   => 'case studies',
		'pillar'       => 'guides',
	];

	public static function init(): void {
		add_shortcode( 'scos_agent_capabilities', [ __CLASS__, 'render_capabilities' ] );
		add_shortcode( 'scos_agent_enquiry_howto', [ __CLASS__, 'render_enquiry_howto' ] );
		add_shortcode( 'scos_agent_tool_examples', [ __CLASS__, 'render_tool_examples' ] );
		add_shortcode( 'scos_agent_connector', [ __CLASS__, 'render_connector' ] );
		add_action( 'save_post', [ __CLASS__, 'flush' ] );
	}

	public static function flush(): void {
		Cache_Helper::flush( self::CACHE_GROUP );
	}

	// ── [scos_agent_capabilities] ───────────────────────────────────────────

	public static function render_capabilities(): string {
		$form = Enquiry_Form::is_enabled();
		$send = Enquiry_Send::is_enabled();
		$html = '';

		if ( Enquiry_Send::delivers_first() ) {
			$intro = __( 'An agent can send an enquiry for you. It reaches us straight away, marked unconfirmed, and we email you a link to confirm it came from you.', 'site-essentials' );
		} elseif ( $send ) {
			$intro = __( 'An agent can send an enquiry for you, but only you can approve it. We email you a link to check the details and press Send. Nothing reaches us until you do.', 'site-essentials' );
		} elseif ( $form ) {
			$intro = __( 'An agent can\'t send an enquiry for you, but it can give you a link that opens our contact form already filled in, for you to check and send.', 'site-essentials' );
		} else {
			$intro = __( 'An agent can read and search this site for you.', 'site-essentials' );
		}
		if ( $send && $form ) {
			$intro .= ' ' . __( 'If you\'d rather check it and send it yourself, the agent can give you a link that opens our contact form already filled in.', 'site-essentials' );
		}
		if ( $form ) {
			$intro .= ' ' . __( 'In a browser with a built-in agent, it can also fill in the form while you watch.', 'site-essentials' );
		}
		$html .= '<p class="scos-agent-note">' . esc_html( $intro ) . '</p>';

		$can = [
			sprintf(
				/* translators: %s: kinds of content, e.g. "services, case studies and articles" */
				__( 'Read any published page, search and list our %s, and get a summary of who we are.', 'site-essentials' ),
				self::content_kinds()
			),
		];
		if ( Enquiry_Send::delivers_first() ) {
			$can[] = __( 'Send an enquiry for you. It reaches us straight away, and you confirm it came from you by email.', 'site-essentials' );
		} elseif ( $send ) {
			$can[] = __( 'Send an enquiry for you. You confirm it from an email before it reaches us.', 'site-essentials' );
		}
		if ( $form ) {
			$can[] = __( 'Give you a link that opens our contact form already filled in, or fill in the form while you watch in a browser with a built-in agent.', 'site-essentials' );
		}

		$cant = [];
		if ( Enquiry_Send::delivers_first() ) {
			$cant[] = __( 'Pass an enquiry off as confirmed. We show it as unconfirmed until you click the link we email you.', 'site-essentials' );
		} elseif ( $send ) {
			$cant[] = __( 'Send an enquiry without your confirmation.', 'site-essentials' );
		}
		if ( self::consent_fields() ) {
			$cant[] = __( 'Sign you up to our mailing list. Only you can tick that box.', 'site-essentials' );
		}
		$cant[] = __( 'Make a booking, see prices that aren\'t published, or see anything that isn\'t public on this site.', 'site-essentials' );
		$cant[] = __( 'Change anything on the site, or see anything about other visitors.', 'site-essentials' );

		$html .= '<p class="scos-agent-note"><strong>' . esc_html__( 'What an agent can do here', 'site-essentials' ) . '</strong></p>' . self::list( $can );
		$html .= '<p class="scos-agent-note"><strong>' . esc_html__( 'What an agent can\'t do', 'site-essentials' ) . '</strong></p>' . self::list( $cant );

		return '<div class="scos-agent-note scos-agent-note--capabilities">' . $html . '</div>';
	}

	// ── [scos_agent_enquiry_howto] ──────────────────────────────────────────

	public static function render_enquiry_howto(): string {
		if ( ! Enquiry_Form::is_enabled() ) {
			return '';
		}

		$schema = self::schema();
		$html   = '';

		if ( ! $schema ) {
			// Form not read yet (or unreadable): enough for an agent to find the fields itself.
			$html .= '<p>' . esc_html__( 'Build a link to our contact form with the person\'s details filled in. Start with', 'site-essentials' );
			$html .= ' <code>' . esc_html( Enquiry_Form::page_url() . '#' . Enquiry_Form::FRAGMENT ) . '</code> ';
			$html .= sprintf(
				/* translators: %s: link to the form fields JSON */
				esc_html__( 'and add &name=value for each field (%s).', 'site-essentials' ),
				'<a href="' . esc_url( Enquiry_API::get_url() ) . '">' . esc_html__( 'field names', 'site-essentials' ) . '</a>'
			) . '</p>';
			return '<div class="scos-agent-note scos-agent-note--howto">' . $html . self::send_paragraph( null ) . '</div>';
		}

		$base     = $schema['url'] . '#' . Enquiry_Form::FRAGMENT;
		$template = $base;
		foreach ( $schema['fields'] as $field ) {
			if ( self::is_consent( $field ) ) {
				continue;
			}
			$template .= '&' . $field['name'] . '=' . strtoupper( preg_replace( '/[^a-z0-9]+/i', '_', $field['name'] ) );
		}

		$html .= '<p>' . esc_html__( 'No connection, token or POST request is needed. Build this link:', 'site-essentials' ) . '</p>';
		$html .= '<pre><code>' . esc_html( $template ) . '</code></pre>';

		$items = [];
		foreach ( $schema['fields'] as $field ) {
			$items[] = self::describe_field( $field );
		}
		$html .= '<ul>' . implode( '', $items ) . '</ul>';

		$html .= self::list( [
			__( 'Replace each value with the person\'s details, URL-encoded (a space is %20, @ is %40). Leave out any field you don\'t know.', 'site-essentials' ),
			__( 'Ask the person for anything required. Never invent contact details.', 'site-essentials' ),
		] );

		$html .= '<p>' . esc_html__( 'Example:', 'site-essentials' ) . '</p>';
		$html .= '<pre><code>' . esc_html( self::example_link( $schema ) ) . '</code></pre>';

		$html .= '<p><strong>' . esc_html__( 'Browser agents:', 'site-essentials' ) . '</strong> ' . esc_html__( 'open the link. Our contact form fills in. Show the person, and press the form\'s Send button only once they say yes.', 'site-essentials' ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Read-only assistants:', 'site-essentials' ) . '</strong> ' . esc_html__( 'give the person the link. Opening it fills in our contact form, and nothing is sent until they press Send, so tell them that plainly.', 'site-essentials' ) . '</p>';

		return '<div class="scos-agent-note scos-agent-note--howto">' . $html . self::send_paragraph( $schema ) . '</div>';
	}

	/**
	 * How to POST an enquiry, and what to tell the person after, for this mode.
	 */
	private static function send_paragraph( ?array $schema ): string {
		if ( ! Enquiry_Send::is_enabled() ) {
			return '';
		}

		$body = [];
		foreach ( $schema['fields'] ?? [] as $field ) {
			if ( ! empty( $field['required'] ) ) {
				$body[ $field['name'] ] = '…';
			}
		}
		if ( ! $body ) {
			$body = [ 'email' => '…', 'message' => '…' ];
		}
		$json  = wp_json_encode( [ 'fields' => $body, 'agent' => 'your name' ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$hours = (int) ( Enquiry_Send::TTL / HOUR_IN_SECONDS );

		$html  = '<p><strong>' . esc_html__( 'Agents that can make requests:', 'site-essentials' ) . '</strong> ';
		$html .= esc_html__( 'show the person what you will send, then POST', 'site-essentials' );
		$html .= ' <code>' . esc_html( rest_url( Content_API::NAMESPACE . '/enquiry/send' ) ) . '</code> ';
		$html .= esc_html__( 'with', 'site-essentials' ) . ' <code>' . esc_html( $json ) . '</code>. ';

		if ( Enquiry_Send::delivers_first() ) {
			$html .= esc_html( sprintf(
				/* translators: %d: hours the confirmation link works */
				__( 'The enquiry reaches us straight away, so don\'t tell the person they need to press Send. Tell them it has been sent, and ask them to click the confirmation link we email them within %d hours.', 'site-essentials' ),
				$hours
			) );
		} else {
			$html .= esc_html( sprintf(
				/* translators: %d: hours the confirmation link works */
				__( 'Nothing reaches us yet. Tell the person to open the email we send them within %d hours and press Send there.', 'site-essentials' ),
				$hours
			) );
		}

		$html .= ' ' . sprintf(
			/* translators: %s: link to the form fields JSON */
			esc_html__( 'Field details are at %s.', 'site-essentials' ),
			'<a href="' . esc_url( Enquiry_API::get_url() ) . '">' . esc_html( wp_parse_url( Enquiry_API::get_url(), PHP_URL_PATH ) ) . '</a>'
		);

		return $html . '</p>';
	}

	private static function describe_field( array $field ): string {
		$parts = [];
		$label = $field['label'] ?? '';
		$desc  = $field['description'] ?? '';
		if ( '' !== $label && 0 !== strcasecmp( $label, $field['name'] ) ) {
			$parts[] = $label . ( '' !== $desc && $desc !== $label ? ': ' . $desc : '' ) . '.';
		} elseif ( '' !== $desc ) {
			$parts[] = rtrim( $desc, '.' ) . '.';
		}

		if ( self::is_consent( $field ) ) {
			$parts[] = __( 'Don\'t add this unless the person asks to join.', 'site-essentials' );
		} elseif ( ! empty( $field['required'] ) ) {
			$parts[] = __( 'Required.', 'site-essentials' );
		}

		if ( ! empty( $field['options'] ) && ! self::is_consent( $field ) ) {
			$parts[] = sprintf(
				/* translators: %s: comma-separated options */
				__( 'One or more of: %s. Repeat the name for each choice.', 'site-essentials' ),
				implode( ', ', $field['options'] )
			);
		}
		if ( 'email' === $field['type'] ) {
			$parts[] = __( 'An email address.', 'site-essentials' );
		}
		if ( 'tel' === $field['type'] ) {
			$parts[] = Enquiry_Phone::format_hint();
		}
		if ( 'url' === $field['type'] ) {
			$parts[] = __( 'A web address starting with https://.', 'site-essentials' );
		}

		return '<li><code>' . esc_html( $field['name'] ) . '</code> ' . esc_html( implode( ' ', $parts ) ) . '</li>';
	}

	/**
	 * A worked example with the required fields filled in.
	 */
	private static function example_link( array $schema ): string {
		$service = self::search_term();
		$pairs   = [];
		foreach ( $schema['fields'] as $field ) {
			if ( self::is_consent( $field ) ) {
				continue;
			}
			$value = null;
			if ( 'email' === $field['type'] ) {
				$value = 'jo@example.com';
			} elseif ( empty( $field['required'] ) && empty( $field['options'] ) ) {
				continue;
			} elseif ( ! empty( $field['options'] ) ) {
				$value = $field['options'][0];
			} elseif ( 'textarea' === $field['type'] ) {
				$value = '' !== $service
					/* translators: %s: a service name */
					? sprintf( __( 'Could I get a quote for %s?', 'site-essentials' ), $service )
					: __( 'Could I get a quote?', 'site-essentials' );
			} elseif ( 'tel' === $field['type'] ) {
				continue;
			} else {
				$value = 'Jo';
			}
			$pairs[] = rawurlencode( $field['name'] ) . '=' . rawurlencode( $value );
		}
		return $schema['url'] . '#' . implode( '&', array_merge( [ Enquiry_Form::FRAGMENT ], $pairs ) );
	}

	// ── [scos_agent_tool_examples] ──────────────────────────────────────────

	public static function render_tool_examples( $atts = [] ): string {
		$atts = shortcode_atts( [ 'show' => 'tools' ], $atts, 'scos_agent_tool_examples' );
		return 'prompts' === $atts['show'] ? self::prompts() : self::tools();
	}

	private static function tools(): string {
		$api      = static function ( string $path, array $args = [] ): string {
			return add_query_arg( array_map( 'rawurlencode', $args ), rest_url( Content_API::NAMESPACE . $path ) );
		};
		$link     = static function ( string $url, string $text ): string {
			return '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
		};
		$try      = static function ( array $links ): string {
			return $links ? '<br>' . esc_html__( 'Try it:', 'site-essentials' ) . ' ' . implode( ' · ', $links ) : '';
		};
		$purposes = self::purposes();
		$term     = self::search_term();
		$pages    = self::example_pages();

		$html = '<p>' . esc_html__( 'All four tools are read-only. They return published pages only.', 'site-essentials' ) . '</p>';

		// get_site_info
		$html .= '<p><strong><code>get_site_info</code></strong><br>' . esc_html__( 'Who we are, what we do, who we work with and the topics we cover. Agents should call this first.', 'site-essentials' );
		$html .= $try( [ $link( $api( '/site-info' ), (string) wp_parse_url( $api( '/site-info' ), PHP_URL_PATH ) ) ] ) . '</p>';

		// search_content
		$links = [];
		if ( '' !== $term ) {
			/* translators: %s: search term */
			$links[] = $link( $api( '/search', [ 'query' => $term ] ), sprintf( __( 'search for "%s"', 'site-essentials' ), $term ) );
			if ( isset( $purposes['service-page'] ) ) {
				/* translators: %s: search term */
				$links[] = $link( $api( '/search', [ 'query' => $term, 'purpose' => 'service-page' ] ), sprintf( __( 'service pages about "%s"', 'site-essentials' ), $term ) );
			}
		}
		$by_purpose = $purposes
			/* translators: %s: purpose values found on this site */
			? sprintf( __( 'content type (purpose: %s)', 'site-essentials' ), implode( ', ', array_keys( $purposes ) ) )
			: __( 'content type (purpose)', 'site-essentials' );
		$html      .= '<p><strong><code>search_content</code></strong><br>' . esc_html( sprintf(
			/* translators: %s: "content type (purpose: …)" */
			__( 'Search our pages and articles by keyword. It can be narrowed by %s or by what the visitor is trying to do (intent: informational, commercial, transact_lead, trust).', 'site-essentials' ),
			$by_purpose
		) ) . $try( $links ) . '</p>';

		// list_content
		$links = [];
		foreach ( $purposes as $purpose => $label ) {
			/* translators: %s: kind of content, e.g. "services" */
			$links[] = $link( $api( '/list', [ 'purpose' => $purpose ] ), sprintf( __( 'all %s', 'site-essentials' ), $label ) );
		}
		$links[] = $link( $api( '/list', [ 'intent' => 'commercial' ] ), __( 'everything commercial', 'site-essentials' ) );
		$html   .= '<p><strong><code>list_content</code></strong><br>' . esc_html__( 'List everything published, with the same optional filters. This is useful for seeing everything we offer before looking at one page.', 'site-essentials' ) . $try( $links ) . '</p>';

		// get_content
		$links = [];
		foreach ( $pages as $page ) {
			$links[] = $link( $api( '/content/' . $page['slug'] ), '/content/' . $page['slug'] );
		}
		$html .= '<p><strong><code>get_content</code></strong><br>' . esc_html__( 'The full text of one page or article, found by its slug from a search or list.', 'site-essentials' ) . $try( $links ) . '</p>';

		return '<div class="scos-agent-note scos-agent-note--tools">' . $html . '</div>';
	}

	private static function prompts(): string {
		$name     = self::business_name();
		$purposes = self::purposes();
		$term     = self::search_term();
		$pages    = self::example_pages();

		/* translators: %s: business name */
		$items = [ sprintf( __( 'Use get_site_info to tell me about %s.', 'site-essentials' ), $name ) ];
		foreach ( array_slice( $purposes, 0, 2, true ) as $purpose => $label ) {
			/* translators: 1: purpose value, 2: kind of content */
			$items[] = sprintf( __( 'Use list_content with purpose=%1$s to show me all %2$s.', 'site-essentials' ), $purpose, $label );
		}
		if ( '' !== $term ) {
			/* translators: %s: search term */
			$items[] = sprintf( __( 'Use search_content with query=%1$s to find pages about %1$s.', 'site-essentials' ), $term );
		}
		if ( $pages ) {
			/* translators: %s: page slug */
			$items[] = sprintf( __( 'Use get_content with slug=%s to read that page in full.', 'site-essentials' ), $pages[0]['slug'] );
		}

		return self::list( $items, 'scos-agent-note scos-agent-note--prompts' );
	}

	// ── [scos_agent_connector] ──────────────────────────────────────────────

	public static function render_connector(): string {
		if ( ! Mcp_Server::is_enabled() ) {
			return '';
		}

		$html  = '<pre><code>' . esc_html( Mcp_Server::get_url() ) . '</code></pre>';
		$html .= '<ul>';
		$html .= '<li><strong>Claude:</strong> ' . esc_html__( 'Settings → Connectors → Add custom connector, then paste the address. No login needed.', 'site-essentials' ) . '</li>';
		$html .= '<li><strong>ChatGPT:</strong> ' . esc_html__( 'custom connectors need developer mode (Settings → Apps → Advanced). Then add a new app with the address.', 'site-essentials' ) . '</li>';
		$html .= '<li><strong>' . esc_html__( 'Cursor, Claude Code and other MCP clients:', 'site-essentials' ) . '</strong> ' . esc_html__( 'add it as a remote server (Streamable HTTP).', 'site-essentials' ) . '</li>';
		$html .= '</ul>';

		$name = self::business_name();
		$term = self::search_term();
		$find = '' !== $term
			/* translators: 1: business name, 2: search term */
			? sprintf( __( 'Using the %1$s connector, find their %2$s pages', 'site-essentials' ), $name, $term )
			/* translators: %s: business name */
			: sprintf( __( 'Using the %s connector, tell me what they offer', 'site-essentials' ), $name );
		$ask  = Enquiry_Send::is_enabled() ? $find . ' ' . __( 'and send them an enquiry from me.', 'site-essentials' ) : $find . '.';

		$html .= '<p>' . esc_html__( 'Then ask something like:', 'site-essentials' ) . ' “' . esc_html( $ask ) . '”</p>';

		if ( Enquiry_Send::delivers_first() ) {
			$html .= '<p>' . esc_html__( 'An enquiry sent this way reaches us straight away, and you confirm by email that it came from you.', 'site-essentials' ) . '</p>';
		} elseif ( Enquiry_Send::is_enabled() ) {
			$html .= '<p>' . esc_html__( 'You still confirm the enquiry by email before it reaches us.', 'site-essentials' ) . '</p>';
		}

		return '<div class="scos-agent-note scos-agent-note--connector">' . $html . '</div>';
	}

	// ── Site data ───────────────────────────────────────────────────────────

	private static function business_name(): string {
		$name = trim( (string) get_option( 'scos_biz_business_name', '' ) );
		return '' !== $name ? $name : (string) get_bloginfo( 'name' );
	}

	/**
	 * Purposes that have published content, value => plain name.
	 *
	 * @return array<string,string>
	 */
	private static function purposes(): array {
		return (array) Cache_Helper::remember( 'purposes', static function () {
			$found = [];
			foreach ( self::PURPOSES as $purpose => $label ) {
				$ids = get_posts( [
					'post_type'      => [ 'post', 'page' ],
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => 'scos_ca_purpose',
					'meta_value'     => $purpose,
				] );
				if ( $ids ) {
					$found[ $purpose ] = $label;
				}
			}
			return $found;
		}, 12 * HOUR_IN_SECONDS, self::CACHE_GROUP );
	}

	/**
	 * Up to three service pages: [ [ title, slug ], … ].
	 */
	private static function services(): array {
		return (array) Cache_Helper::remember( 'services', static function () {
			$posts = get_posts( [
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => 3,
				'no_found_rows'  => true,
				'meta_key'       => 'scos_ca_purpose',
				'meta_value'     => 'service-page',
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			] );
			return array_map( static function ( $p ) {
				return [ 'title' => get_the_title( $p ), 'slug' => $p->post_name ];
			}, $posts );
		}, 12 * HOUR_IN_SECONDS, self::CACHE_GROUP );
	}

	/**
	 * Two pages for the get_content examples: services first, else recent pages.
	 */
	private static function example_pages(): array {
		$pages = array_slice( self::services(), 0, 2 );
		if ( $pages ) {
			return $pages;
		}
		return (array) Cache_Helper::remember( 'pages', static function () {
			$exclude = array_filter( [ (int) get_option( 'page_on_front' ), (int) get_option( 'scos_agentic_webmcp_page_id' ) ] );
			$posts   = get_posts( [
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 2,
				'no_found_rows'  => true,
				'post__not_in'   => $exclude,
			] );
			return array_map( static function ( $p ) {
				return [ 'title' => get_the_title( $p ), 'slug' => $p->post_name ];
			}, $posts );
		}, 12 * HOUR_IN_SECONDS, self::CACHE_GROUP );
	}

	/**
	 * A search term that finds something here: the first service's name, or
	 * the first example page's when no page is marked as a service.
	 */
	private static function search_term(): string {
		$title = self::example_pages()[0]['title'] ?? '';
		$title = trim( preg_replace( '/\b(services?|and)\b/i', ' ', html_entity_decode( wp_strip_all_tags( $title ) ) ) );
		return strtolower( trim( preg_replace( '/\s+/', ' ', $title ) ) );
	}

	/**
	 * "services, case studies and articles", from what this site has.
	 */
	private static function content_kinds(): string {
		$kinds = array_values( self::purposes() );
		if ( wp_count_posts( 'post' )->publish > 0 ) {
			$kinds[] = __( 'articles', 'site-essentials' );
		}
		if ( ! self::purposes() ) {
			array_unshift( $kinds, __( 'pages', 'site-essentials' ) );
		}
		if ( 1 === count( $kinds ) ) {
			return $kinds[0];
		}
		$last = array_pop( $kinds );
		/* translators: 1: comma-separated list, 2: last item */
		return sprintf( __( '%1$s and %2$s', 'site-essentials' ), implode( ', ', $kinds ), $last );
	}

	// ── Form helpers ────────────────────────────────────────────────────────

	/**
	 * The form schema without fetching the page this is rendering on.
	 */
	private static function schema(): ?array {
		$on_enquiry_page = is_page( absint( get_option( Enquiry_Form::OPTION_PAGE, 0 ) ) );
		$schema          = Enquiry_Form::get_schema( ! $on_enquiry_page );
		if ( $schema ) {
			unset( $schema['target'] );
		}
		return $schema;
	}

	private static function consent_fields(): array {
		$schema = Enquiry_Form::is_enabled() ? self::schema() : null;
		return array_filter( $schema['fields'] ?? [], [ __CLASS__, 'is_consent' ] );
	}

	private static function is_consent( array $field ): bool {
		return 'checkbox' === ( $field['type'] ?? '' )
			&& (bool) preg_match( Enquiry_Send::CONSENT_PATTERN, $field['name'] . ' ' . ( $field['description'] ?? '' ) );
	}

	private static function list( array $items, string $class = 'scos-agent-note' ): string {
		$html = '<ul class="' . esc_attr( $class ) . '">';
		foreach ( $items as $item ) {
			$html .= '<li>' . esc_html( $item ) . '</li>';
		}
		return $html . '</ul>';
	}
}

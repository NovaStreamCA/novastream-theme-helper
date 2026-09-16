<?php
/**
 * Basic JSON-LD structured data for NovaStream SEO.
 *
 * @package NovaStreamThemeHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Determine whether another active SEO plugin owns the page schema graph.
 *
 * @return bool
 */
function novastream_seo_has_external_json_ld_provider() {
	$detected = defined( 'WPSEO_VERSION' )
		|| defined( 'RANK_MATH_VERSION' )
		|| defined( 'SEOPRESS_VERSION' )
		|| defined( 'AIOSEO_VERSION' )
		|| defined( 'THE_SEO_FRAMEWORK_VERSION' )
		|| class_exists( 'The_SEO_Framework\Load' );

	return (bool) apply_filters( 'novastream_seo_external_json_ld_provider_active', $detected );
}

/**
 * Normalize text before adding it to the structured-data graph.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function novastream_seo_json_ld_text( $value ) {
	$charset = get_bloginfo( 'charset' );
	$charset = $charset ? $charset : 'UTF-8';
	$value   = wp_strip_all_tags( (string) $value );

	return trim( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, $charset ) );
}

/**
 * Resolve the canonical public URL represented by the current request.
 *
 * @param array<string, mixed> $metadata Current SEO metadata.
 * @return string
 */
function novastream_seo_get_json_ld_url( $metadata ) {
	if ( ! empty( $metadata['url'] ) ) {
		return esc_url_raw( (string) $metadata['url'] );
	}

	if ( is_front_page() ) {
		return home_url( '/' );
	}

	if ( is_home() ) {
		$posts_page_id = (int) get_option( 'page_for_posts' );

		return $posts_page_id ? (string) get_permalink( $posts_page_id ) : home_url( '/' );
	}

	if ( is_search() ) {
		return get_search_link( get_search_query( false ) );
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$term      = get_queried_object();
		$term_link = $term instanceof WP_Term ? get_term_link( $term ) : '';

		return is_wp_error( $term_link ) ? '' : esc_url_raw( (string) $term_link );
	}

	if ( is_post_type_archive() ) {
		$post_type = get_query_var( 'post_type' );
		$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
		$archive   = $post_type ? get_post_type_archive_link( $post_type ) : '';

		return $archive ? esc_url_raw( $archive ) : '';
	}

	if ( is_author() ) {
		$author = get_queried_object();

		return $author instanceof WP_User ? get_author_posts_url( $author->ID ) : '';
	}

	if ( is_year() ) {
		return get_year_link( (int) get_query_var( 'year' ) );
	}

	if ( is_month() ) {
		return get_month_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ) );
	}

	if ( is_day() ) {
		return get_day_link(
			(int) get_query_var( 'year' ),
			(int) get_query_var( 'monthnum' ),
			(int) get_query_var( 'day' )
		);
	}

	return '';
}

/**
 * Resolve the most accurate page name for the current request.
 *
 * @param array<string, mixed> $metadata Current SEO metadata.
 * @return string
 */
function novastream_seo_get_json_ld_page_name( $metadata ) {
	if ( is_search() ) {
		return sprintf(
			/* translators: %s: search query. */
			__( 'Search results for “%s”', 'novastream-theme-helper' ),
			get_search_query( false )
		);
	}

	if ( is_home() ) {
		$posts_page_id = (int) get_option( 'page_for_posts' );

		if ( $posts_page_id ) {
			return get_the_title( $posts_page_id );
		}
	}

	if ( is_archive() ) {
		return get_the_archive_title();
	}

	return isset( $metadata['title'] ) ? (string) $metadata['title'] : '';
}

/**
 * Add one valid breadcrumb to a breadcrumb collection.
 *
 * @param array<int, array{name:string,url:string}> $items Breadcrumb items.
 * @param mixed                                     $name  Breadcrumb label.
 * @param mixed                                     $url   Breadcrumb URL.
 */
function novastream_seo_add_json_ld_breadcrumb( &$items, $name, $url ) {
	$name = novastream_seo_json_ld_text( $name );
	$url  = esc_url_raw( (string) $url );

	if ( '' === $name || '' === $url ) {
		return;
	}

	$items[] = array(
		'name' => $name,
		'url'  => $url,
	);
}

/**
 * Build a conservative breadcrumb trail from native WordPress hierarchy.
 *
 * WooCommerce already publishes its own BreadcrumbList on catalogue requests,
 * so those requests are intentionally left to WooCommerce.
 *
 * @param array<string, mixed> $metadata Current SEO metadata.
 * @return array<int, array{name:string,url:string}>
 */
function novastream_seo_get_json_ld_breadcrumb_items( $metadata ) {
	if ( is_front_page() || ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) ) {
		return array();
	}

	$items = array();
	novastream_seo_add_json_ld_breadcrumb( $items, get_bloginfo( 'name' ), home_url( '/' ) );

	if ( is_singular() ) {
		$post = get_queried_object();

		if ( $post instanceof WP_Post ) {
			$post_type = get_post_type_object( $post->post_type );

			if ( $post_type && $post_type->hierarchical ) {
				foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
					novastream_seo_add_json_ld_breadcrumb(
						$items,
						get_the_title( $ancestor_id ),
						get_permalink( $ancestor_id )
					);
				}
			} elseif ( 'post' === $post->post_type ) {
				$posts_page_id = (int) get_option( 'page_for_posts' );

				if ( $posts_page_id ) {
					novastream_seo_add_json_ld_breadcrumb(
						$items,
						get_the_title( $posts_page_id ),
						get_permalink( $posts_page_id )
					);
				}
			} elseif ( $post_type && $post_type->has_archive ) {
				$archive_url = get_post_type_archive_link( $post->post_type );
				novastream_seo_add_json_ld_breadcrumb( $items, $post_type->labels->name, $archive_url );
			}

			novastream_seo_add_json_ld_breadcrumb( $items, get_the_title( $post ), get_permalink( $post ) );
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			$taxonomy = get_taxonomy( $term->taxonomy );

			if ( $taxonomy && $taxonomy->hierarchical ) {
				foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
					$ancestor      = get_term( $ancestor_id, $term->taxonomy );
					$ancestor_link = $ancestor instanceof WP_Term ? get_term_link( $ancestor ) : '';

					if ( $ancestor instanceof WP_Term && ! is_wp_error( $ancestor_link ) ) {
						novastream_seo_add_json_ld_breadcrumb( $items, $ancestor->name, $ancestor_link );
					}
				}
			}

			$term_link = get_term_link( $term );

			if ( ! is_wp_error( $term_link ) ) {
				novastream_seo_add_json_ld_breadcrumb( $items, $term->name, $term_link );
			}
		}
	} elseif ( is_home() ) {
		$posts_page_id = (int) get_option( 'page_for_posts' );
		novastream_seo_add_json_ld_breadcrumb(
			$items,
			$posts_page_id ? get_the_title( $posts_page_id ) : __( 'Posts', 'novastream-theme-helper' ),
			novastream_seo_get_json_ld_url( $metadata )
		);
	} elseif ( is_post_type_archive() || is_author() || is_date() || is_search() ) {
		novastream_seo_add_json_ld_breadcrumb(
			$items,
			novastream_seo_get_json_ld_page_name( $metadata ),
			novastream_seo_get_json_ld_url( $metadata )
		);
	}

	$items = (array) apply_filters( 'novastream_seo_json_ld_breadcrumb_items', $items, $metadata );

	return count( $items ) > 1 ? $items : array();
}

/**
 * Resolve the site logo used by the Organization node.
 *
 * NovaStream themes store their presented logo in the ACF `header_logo`
 * option. WordPress's Custom Logo remains the dependency-free fallback.
 *
 * @param array<string, mixed> $metadata Current SEO metadata.
 * @return string
 */
function novastream_seo_get_json_ld_logo_url( $metadata ) {
	$acf_logo = function_exists( 'get_field' ) ? get_field( 'header_logo', 'option' ) : '';
	$logo_url = '';

	if ( is_array( $acf_logo ) ) {
		if ( ! empty( $acf_logo['url'] ) ) {
			$logo_url = (string) $acf_logo['url'];
		} else {
			$logo_id  = absint( $acf_logo['ID'] ?? $acf_logo['id'] ?? 0 );
			$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
		}
	} elseif ( is_numeric( $acf_logo ) ) {
		$logo_url = wp_get_attachment_image_url( absint( $acf_logo ), 'full' );
	} elseif ( is_string( $acf_logo ) ) {
		$logo_url = $acf_logo;
	}

	if ( ! $logo_url ) {
		$logo_id  = (int) get_theme_mod( 'custom_logo' );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
	}

	return esc_url_raw(
		(string) apply_filters( 'novastream_seo_json_ld_logo_url', $logo_url ? $logo_url : '', $metadata )
	);
}

/**
 * Build the basic Schema.org graph for the current request.
 *
 * @param array<string, mixed> $metadata Current SEO metadata.
 * @return array<int, array<string, mixed>>
 */
function novastream_seo_get_json_ld_graph( $metadata ) {
	$page_url = novastream_seo_get_json_ld_url( $metadata );

	if ( '' === $page_url || is_404() ) {
		return array();
	}

	$home_url                 = home_url( '/' );
	$organization_id          = $home_url . '#organization';
	$website_id               = $home_url . '#website';
	$webpage_id               = $page_url . '#webpage';
	$graph                    = array();
	$site_name                = novastream_seo_json_ld_text( get_bloginfo( 'name' ) );
	$page_name                = novastream_seo_json_ld_text( novastream_seo_get_json_ld_page_name( $metadata ) );
	$description              = novastream_seo_json_ld_text( $metadata['description'] ?? '' );
	$logo_url                 = novastream_seo_get_json_ld_logo_url( $metadata );
	$woocommerce_owns_website = function_exists( 'is_shop' ) && is_shop() && is_front_page();

	$organization = array(
		'@type' => 'Organization',
		'@id'   => $organization_id,
		'name'  => $site_name,
		'url'   => $home_url,
	);

	if ( $logo_url ) {
		$organization['logo'] = array(
			'@type'      => 'ImageObject',
			'url'        => $logo_url,
			'contentUrl' => $logo_url,
		);
	}

	$organization     = apply_filters( 'novastream_seo_json_ld_organization', $organization, $metadata );
	$has_organization = is_array( $organization ) && $organization;

	if ( $has_organization ) {
		$organization_id = ! empty( $organization['@id'] ) ? (string) $organization['@id'] : $organization_id;
		$graph[]         = $organization;
	}

	$website = array(
		'@type' => 'WebSite',
		'@id'   => $website_id,
		'url'   => $home_url,
		'name'  => $site_name,
	);

	if ( $has_organization ) {
		$website['publisher'] = array( '@id' => $organization_id );
	}

	$website     = apply_filters( 'novastream_seo_json_ld_website', $website, $metadata );
	$has_website = ! $woocommerce_owns_website && is_array( $website ) && $website;

	if ( $has_website ) {
		$website_id = ! empty( $website['@id'] ) ? (string) $website['@id'] : $website_id;
		$graph[]    = $website;
	}

	$image_id = '';

	if ( ! empty( $metadata['image'] ) ) {
		$image_id = $page_url . '#primaryimage';
		$image    = array(
			'@type'      => 'ImageObject',
			'@id'        => $image_id,
			'url'        => esc_url_raw( (string) $metadata['image'] ),
			'contentUrl' => esc_url_raw( (string) $metadata['image'] ),
		);

		if ( ! empty( $metadata['image_width'] ) && ! empty( $metadata['image_height'] ) ) {
			$image['width']  = absint( $metadata['image_width'] );
			$image['height'] = absint( $metadata['image_height'] );
		}

		$image = apply_filters( 'novastream_seo_json_ld_image', $image, $metadata );

		if ( is_array( $image ) && $image ) {
			$image_id = ! empty( $image['@id'] ) ? (string) $image['@id'] : $image_id;
			$graph[]  = $image;
		} else {
			$image_id = '';
		}
	}

	$page_type = 'WebPage';

	if ( is_search() ) {
		$page_type = 'SearchResultsPage';
	} elseif ( is_home() || is_archive() ) {
		$page_type = 'CollectionPage';
	}

	$webpage = array(
		'@type' => apply_filters( 'novastream_seo_json_ld_webpage_type', $page_type, $metadata ),
		'@id'   => $webpage_id,
		'url'   => $page_url,
		'name'  => $page_name ? $page_name : $site_name,
	);

	if ( $has_website ) {
		$webpage['isPartOf'] = array( '@id' => $website_id );
	}

	if ( $description ) {
		$webpage['description'] = $description;
	}

	if ( $has_organization ) {
		$webpage['about'] = array( '@id' => $organization_id );
	}

	if ( $image_id ) {
		$webpage['primaryImageOfPage'] = array( '@id' => $image_id );
	}

	$breadcrumbs = novastream_seo_get_json_ld_breadcrumb_items( $metadata );

	if ( $breadcrumbs ) {
		$breadcrumb_id = $page_url . '#breadcrumb';
		$list_items    = array();
		$position      = 1;

		foreach ( $breadcrumbs as $breadcrumb ) {
			if ( empty( $breadcrumb['name'] ) || empty( $breadcrumb['url'] ) ) {
				continue;
			}

			$list_items[] = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'item'     => array(
					'@id'  => esc_url_raw( $breadcrumb['url'] ),
					'name' => novastream_seo_json_ld_text( $breadcrumb['name'] ),
				),
			);
			++$position;
		}

		if ( $list_items ) {
			$webpage['breadcrumb'] = array( '@id' => $breadcrumb_id );
			$graph[]               = array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $breadcrumb_id,
				'itemListElement' => $list_items,
			);
		}
	}

	$article_post_types = (array) apply_filters( 'novastream_seo_json_ld_article_post_types', array( 'post' ) );
	$post               = is_singular() ? get_queried_object() : null;

	if ( $post instanceof WP_Post && in_array( $post->post_type, $article_post_types, true ) && ! post_password_required( $post ) ) {
		$article_id = $page_url . '#article';
		$article    = array(
			'@type'            => apply_filters( 'novastream_seo_json_ld_article_type', 'Article', $post, $metadata ),
			'@id'              => $article_id,
			'isPartOf'         => array( '@id' => $webpage_id ),
			'mainEntityOfPage' => array( '@id' => $webpage_id ),
			'headline'         => $page_name,
			'datePublished'    => get_post_time( DATE_W3C, false, $post ),
			'dateModified'     => get_post_modified_time( DATE_W3C, false, $post ),
		);

		if ( $description ) {
			$article['description'] = $description;
		}

		if ( $image_id ) {
			$article['image'] = array( '@id' => $image_id );
		}

		if ( $has_organization ) {
			$article['publisher'] = array( '@id' => $organization_id );
		}

		$author_id = (int) $post->post_author;

		if ( $author_id ) {
			$article['author'] = array(
				'@type' => 'Person',
				'@id'   => get_author_posts_url( $author_id ) . '#author',
				'name'  => novastream_seo_json_ld_text( get_the_author_meta( 'display_name', $author_id ) ),
				'url'   => get_author_posts_url( $author_id ),
			);
		}

		$article = apply_filters( 'novastream_seo_json_ld_article', $article, $post, $metadata );

		if ( is_array( $article ) && $article ) {
			$webpage['mainEntity'] = array( '@id' => $article['@id'] ?? $article_id );
			$graph[]               = $article;
		}
	}

	$graph[] = apply_filters( 'novastream_seo_json_ld_webpage', $webpage, $metadata );

	$graph = (array) apply_filters( 'novastream_seo_json_ld_graph', $graph, $metadata );

	return array_values( array_filter( $graph, 'is_array' ) );
}

/**
 * Render the current request's JSON-LD graph.
 *
 * @param array<string, mixed> $metadata Current SEO metadata.
 */
function novastream_seo_render_json_ld( $metadata ) {
	$enabled = ! novastream_seo_has_external_json_ld_provider();
	$enabled = (bool) apply_filters( 'novastream_seo_json_ld_enabled', $enabled, $metadata );

	if ( ! $enabled ) {
		return;
	}

	$graph = novastream_seo_get_json_ld_graph( $metadata );

	if ( ! $graph ) {
		return;
	}

	$data = array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);
	$data = apply_filters( 'novastream_seo_json_ld_data', $data, $metadata );

	if ( ! is_array( $data ) || empty( $data['@graph'] ) ) {
		return;
	}

	$json = wp_json_encode(
		$data,
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	if ( false !== $json ) {
		echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

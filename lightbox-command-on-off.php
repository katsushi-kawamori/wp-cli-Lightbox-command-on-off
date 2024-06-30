<?php
/**
 * Cli Name:    Lightbox command on off
 * Description: Switch the Lightbox On and Off for all posts and all pages at once.
 * Version:     2.00
 * Author:      Katsushi Kawamori
 * Author URI:  https://riverforest-wp.info/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package box
 */

/*
	Copyright (c) 2023- Katsushi Kawamori (email : dodesyoswift312@gmail.com)
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; version 2 of the License.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */


/** ==================================================
 * Search DB
 *
 * @return array $files  files.
 * @since 1.10
 */
function lightbox_search_db_files() {

	$wp_uploads = wp_upload_dir();

	$upload_url = $wp_uploads['baseurl'];
	if ( is_ssl() ) {
		$upload_url = str_replace( 'http:', 'https:', $upload_url );
	}
	$upload_url  = untrailingslashit( $upload_url );

	$args = array(
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'any',
		'posts_per_page' => -1,
	);
	$posts = get_posts( $args );

	$files = array();
	foreach ( $posts as $post ) {
		$metadata = wp_get_attachment_metadata( $post->ID );
		$path_file  = get_post_meta( $post->ID, '_wp_attached_file', true );
		$filename   = wp_basename( $path_file );
		$media_path = str_replace( $filename, '', $path_file );
		$media_url  = $upload_url . '/' . $media_path;
		$files[] = array(
			'id' => $post->ID,
			'size' => 'full',
			'url' => $media_url . $filename,
		);
		if ( ( ! empty( $metadata ) && array_key_exists( 'sizes', $metadata ) ) ) {
			if ( ! empty( $metadata['original_image'] ) ) {
				$org_url = wp_get_original_image_url( $post->ID );
				$files[] = array(
					'id' => $post->ID,
					'size' => 'original_image',
					'url' => $org_url,
				);
			}

			$thumbnails = $metadata['sizes'];
			if ( ! empty( $thumbnails ) ) {
				foreach ( $thumbnails as $key => $key2 ) {
					if ( array_key_exists( 'sources', $thumbnails[ $key ] ) ) {
						/* WP6.1 or later */
						$sources = $thumbnails[ $key ]['sources'];
						foreach ( $sources as $key2 => $value2 ) {
							$url      = $media_url . $sources[ $key2 ]['file'];
							$files[] = array(
								'id' => $post->ID,
								'size' => $key2,
								'url' => $url,
							);
						}
					} else {
						$filename = $key2['file'];
						$url      = $media_url . $key2['file'];
						$files[] = array(
							'id' => $post->ID,
							'size' => $key,
							'url' => $url,
						);
					}
				}
			}
		}
	}

	return $files;
}

/** ==================================================
 * Convert Block
 *
 * @param int    $pid  Post ID.
 * @param string $contents  Contents.
 * @param array  $files  Files.
 * @since 1.10
 */
function lightbox_convert_block( $pid, $contents, $files ) {

	$classic_contents = $contents;

	/* Remove gallery block from contents */
	preg_match_all( '/<!-- wp:gallery(.*?)\/wp:gallery -->/ims', $contents, $found_gallery_wp );
	if ( ! empty( $found_gallery_wp[1] ) ) {
		foreach ( $found_gallery_wp[1] as $value ) {
			$classic_contents = str_replace( $value, '', $classic_contents );
		}
		$classic_contents = str_replace( '<!-- wp:gallery/wp:gallery -->', '', $classic_contents );
	}

	/* Remove image block from contents */
	preg_match_all( '/<!-- wp:image(.*?)\/wp:image -->/ims', $contents, $found_wp );
	if ( ! empty( $found_wp[1] ) ) {
		foreach ( $found_wp[1] as $value ) {
			$classic_contents = str_replace( $value, '', $classic_contents );
		}
		$classic_contents = str_replace( '<!-- wp:image/wp:image -->', '', $classic_contents );
	}

	/* IMG tag to Block from Classic contents */
	if ( preg_match_all( '/<img.*?src\s*=\s*[\"|\'](.*?)[\"|\'].*?>/i', $classic_contents, $found ) !== false ) {
		if ( ! empty( $found[1] ) ) {
			$url_array = array_column( $files, 'url' );
			foreach ( $found[0] as $key => $img_html ) {
				$url = $found[1][ $key ];
				$result = array_search( $url, $url_array );
				if ( $result ) {
					$html = lightbox_image_convert_block( $files[ $result ]['id'], $files[ $result ]['size'], $files[ $result ]['url'] );
					$contents = str_replace( $img_html, $html, $contents );
				}
			}
			$post_arr = array(
				'ID' => $pid,
				'post_content' => $contents,
			);
			wp_update_post( $post_arr );
		}
	}

	/* Gallery to Block from Classic contents */
	if ( preg_match_all( '/\[gallery(.*?)\]/ims', $classic_contents, $found ) !== false ) {
		if ( ! empty( $found[1] ) ) {
			$gallery_attr_arr = array();
			foreach ( $found[0] as $key => $gallery_shortcode ) {
				$gallery_attr_arrs = explode( ' ', $found[1][ $key ] );
				$gallery_attr_arrs = array_values( array_filter( $gallery_attr_arrs ) );
				foreach ( $gallery_attr_arrs as $key => $value ) {
					$gallery_attr_arrs2 = explode( '=', $value );
					$gallery_attr_arr[ $gallery_attr_arrs2[0] ] = str_replace( '"', '', $gallery_attr_arrs2[1] );
				}
				if ( ! array_key_exists( 'columns', $gallery_attr_arr ) ) {
					$gallery_attr_arr['columns'] = 3;
				}
				if ( ! array_key_exists( 'size', $gallery_attr_arr ) ) {
					$gallery_attr_arr['size'] = 'thumbnail';
				}
				$ids = array_map( 'intval', explode( ',', $gallery_attr_arr['ids'] ) );
				$id_array = array_column( $files, 'id' );
				$html = '<!-- wp:gallery {"columns":' . intval( $gallery_attr_arr['columns'] ) . ',"linkTo":"none"} -->';
				$html .= '<figure class="wp-block-gallery has-nested-images columns-' . intval( $gallery_attr_arr['columns'] ) . ' is-cropped">';
				foreach ( $ids as $id ) {
					$result = array_search( $id, $id_array );
					$html .= lightbox_image_convert_block( $id, $gallery_attr_arr['size'], $files[ $result ]['url'] );
				}
				$html .= '</figure><!-- /wp:gallery -->';
				$contents = str_replace( $gallery_shortcode, $html, $contents );
				$post_arr = array(
					'ID' => $pid,
					'post_content' => $contents,
				);
				wp_update_post( $post_arr );
			}
		}
	}
}

/** ==================================================
 * Convert img tag to Block
 *
 * @param int    $media_id  Media ID.
 * @param string $media_size  Media size.
 * @param string $media_url  Media url.
 * @return string $html  html.
 * @since 1.10
 */
function lightbox_image_convert_block( $media_id, $media_size, $media_url ) {

	$attr_arr = array();
	$attr_arr['id'] = $media_id;
	$attr_arr['sizeSlug'] = $media_size;
	$attr_arr['linkDestination'] = 'none';
	$attr = wp_json_encode( $attr_arr, JSON_UNESCAPED_SLASHES );
	$html = null;
	$html .= '<!-- wp:image ' . $attr . ' -->';
	$html .= '<figure class="wp-block-image size-' . $media_size . '">';
	$html .= '<img src="' . $media_url . '" alt="" class="wp-image-' . $media_id . '"/>';
	$html .= '</figure><!-- /wp:image -->';

	return $html;
}

/** ==================================================
 * Lightbox command
 *
 * @param array $args  arguments.
 * @since 1.00
 */
function lightbox_command( $args ) {

	$files = lightbox_search_db_files();

	$input_error_message = 'Please enter the arguments.' . "\n";
	$input_error_message .= '1st argument(string) : on -> Lightbox On, off : Lightbox Off' . "\n";
	$input_error_message .= '2nd argument(int) : Post ID or Media ID -> Process only specified IDs.' . "\n";
	if ( is_array( $args ) && ! empty( $args ) ) {
		$command_flag = $args[0];
		$include_id = 0;
		if ( array_key_exists( 1, $args ) ) {
			$include_id = intval( $args[1] );
		}
		if ( 'on' === $command_flag || 'off' === $command_flag ) {
			$custom_post_args = array(
				'public' => true,
				'_builtin' => false,
			);
			$custom_post_types = get_post_types( $custom_post_args );
			$post_types = array_merge( array( 'post', 'page' ), array_values( $custom_post_types ) );
			$posts_args = array(
				'post_type'      => $post_types,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
			);
			$posts = get_posts( $posts_args );
			global $wpdb;
			$count = 0;
			foreach ( $posts as $post ) {
				$contents = $post->post_content;

				lightbox_convert_block( $post->ID, $contents, $files );

				if ( preg_match_all( '/<!-- wp:image(.*?)-->/ims', $contents, $found ) !== false ) {
					if ( ! empty( $found[1] ) ) {
						foreach ( $found[1] as $value ) {
							$result = array();
							$values = json_decode( $value, true );
							if ( $values ) {
								$convert = true;
								if ( 0 < $include_id ) {
									$convert = false;
									if ( $include_id === $post->ID || $include_id === $values['id'] ) {
										$convert = true;
									}
								}
								if ( array_key_exists( 'lightbox', $values ) ) {
									if ( $values['lightbox']['enabled'] ) {
										$flag = 'true';
									} else {
										$flag = 'false';
									}
								} else {
									$flag = 'non';
								}
								if ( $convert ) {
									if ( 'on' === $command_flag ) {
										if ( 'false' === $flag ) {
											$values['lightbox']['enabled'] = true;
											$value2 = wp_json_encode( $values, JSON_UNESCAPED_SLASHES );
											$result = $wpdb->query(
												$wpdb->prepare(
													"
													UPDATE {$wpdb->prefix}posts
													SET post_content = replace( post_content, %s, %s )
													WHERE ID = %d
													",
													$value,
													' ' . $value2 . ' ',
													$post->ID
												)
											);
										} else if ( 'non' === $flag ) {
											$values_new['lightbox']['enabled'] = true;
											$values_new = array_merge( $values_new, $values );
											$value2 = wp_json_encode( $values_new, JSON_UNESCAPED_SLASHES );
											$result = $wpdb->query(
												$wpdb->prepare(
													"
													UPDATE {$wpdb->prefix}posts
													SET post_content = replace( post_content, %s, %s )
													WHERE ID = %d
													",
													$value,
													' ' . $value2 . ' ',
													$post->ID
												)
											);
										}
									} else if ( 'off' === $command_flag ) {
										if ( 'true' === $flag ) {
											$values['lightbox']['enabled'] = false;
											$value2 = wp_json_encode( $values, JSON_UNESCAPED_SLASHES );
											$result = $wpdb->query(
												$wpdb->prepare(
													"
													UPDATE {$wpdb->prefix}posts
													SET post_content = replace( post_content, %s, %s )
													WHERE ID = %d
													",
													$value,
													' ' . $value2 . ' ',
													$post->ID
												)
											);
										}
									}
								}
							}
							if ( ! empty( $result ) ) {
								++$count;
								WP_CLI::success( get_the_title( $post->ID ) . '[ID:' . $post->ID . ' Type:' . $post->post_type . ' Date:' . $post->post_date . '] : ' . get_the_title( $values['id'] ) . '[ID:' . $values['id'] . ']' );
							}
						}
					}
				}
			}
			if ( 1 == $count ) {
				WP_CLI::success( sprintf( '%1$d image was converted with the lightbox effect %2$s.', $count, $args[0] ) );
			} else if ( 1 < $count ) {
				WP_CLI::success( sprintf( '%1$d images were converted with the lightbox effect %2$s.', $count, $args[0] ) );
			} else {
				WP_CLI::error( 'None to be converted.' );
			}
		} else {
			WP_CLI::error( $input_error_message );
		}
	} else {
		WP_CLI::error( $input_error_message );
	}
}
WP_CLI::add_command( 'box', 'lightbox_command' );

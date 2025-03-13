<?php
/**
 * Cli Name:    Lightbox command on off
 * Description: Switch the Lightbox On and Off for all posts and all pages at once.
 * Version:     3.03
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

$lightboxcommandonoff = new LightboxCommandOnOff();

/** ==================================================
 * Main
 */
class LightboxCommandOnOff {

	/** ==================================================
	 * Construct
	 *
	 * @since 1.00
	 */
	public function __construct() {

		WP_CLI::add_command( 'box', array( $this, 'box_command' ) );
	}

	/** ==================================================
	 * Search DB
	 *
	 * @return array $files  files.
	 * @since 2.00
	 */
	private function search_db_files() {

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
	 * @param string $contents  Contents.
	 * @param string $media_size  Media size.
	 * @param array  $files  Files.
	 * @return string $contents
	 * @since 2.00
	 */
	private function convert_block( $contents, $media_size, $files ) {

		$classic_contents = $contents;

		/* Remove block from contents */
		if ( preg_match_all( '/<!-- wp:(.*?)\/wp:(.*?) -->/ims', $contents, $found_block_wp ) !== false ) {
			if ( ! empty( $found_block_wp[0] ) ) {
				foreach ( $found_block_wp[0] as $value ) {
					$classic_contents = str_replace( $value, '', $classic_contents );
				}
			}
		}

		/* IMG tag to Block from Classic contents */
		if ( preg_match_all( '/<img.*?src\s*=\s*[\"|\'](.*?)[\"|\'].*?>/i', $classic_contents, $found ) !== false ) {
			if ( ! empty( $found[1] ) ) {
				$url_array = array_column( $files, 'url' );
				foreach ( $found[0] as $key => $img_html ) {
					$url = $found[1][ $key ];
					$result = array_search( $url, $url_array );
					if ( $result ) {
						if ( is_null( $media_size ) ) {
							$media_size = $files[ $result ]['size'];
							$media_url = $files[ $result ]['url'];
						} else {
							list( $media_size, $media_url ) = $this->verify_media_size( $media_size, $files, $files[ $result ]['id'] );
						}
						$html = $this->image_convert_block( $files[ $result ]['id'], $media_size, $media_url );
						$contents = str_replace( $img_html, $html, $contents );
					}
				}
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
						if ( is_null( $media_size ) ) {
							$media_size = $gallery_attr_arr['size'];
							$media_url = $files[ $result ]['url'];
						} else {
							list( $media_size, $media_url ) = $this->verify_media_size( $media_size, $files, $id );
						}
						$html .= $this->image_convert_block( $id, $media_size, $media_url );
					}
					$html .= '</figure><!-- /wp:gallery -->';
					$contents = str_replace( $gallery_shortcode, $html, $contents );
				}
			}
		}

		/* Remove the a tag surrounding the img block */
		if ( preg_match_all( '|<a href=\"(.*?)\".*?>(.*?)</a>|mis', $contents, $found_atag ) !== false ) {
			foreach ( $found_atag[2] as $key => $value ) {
				if ( str_contains( $value, '<!-- wp:image' ) ) {
					$contents = str_replace( $found_atag[0][ $key ], $found_atag[2][ $key ], $contents );
				}
			}
		}

		return $contents;
	}

	/** ==================================================
	 * Verify media size for commandline arguments.
	 *
	 * @param string $media_size  Media size.
	 * @param array  $files  Files.
	 * @param int    $media_id  Media ID.
	 * @return array $media_size, $media_url
	 * @since 2.01
	 */
	private function verify_media_size( $media_size, $files, $media_id ) {

		$id_array = array_column( $files, 'id' );
		$result_arr = array_keys( $id_array, $media_id );
		$media_size_arr = array();
		foreach ( $result_arr as $key => $value ) {
			$media_size_arr[] = $files[ $key ]['size'];
		}
		if ( ! in_array( $media_size, $media_size_arr ) ) {
			$media_size = 'large';
		}
		$media_url = wp_get_attachment_image_url( $media_id, $media_size );

		return array( $media_size, $media_url );
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
	private function image_convert_block( $media_id, $media_size, $media_url ) {

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

	/**
	 * Switch the Lightbox On and Off for all posts and all pages at once.
	 * ## OPTIONS
	 *
	 * <string>
	 * : on -> Lightbox On, off : Lightbox Off'
	 *
	 * [--exclude=<int>]
	 * : Optional argument1 -  Post ID -> Exclude and process the specified ID.
	 *
	 * [--exclude=<string>]
	 * : Optional argument1 -  Post IDs -> Exclude and process the specified IDs.
	 *
	 * [--include=<int>]
	 * : Optional argument2 - Post ID -> Process only specified ID.
	 *
	 * [--include=<string>]
	 * : Optional argument2 - Post IDs -> Process only specified IDs.
	 *
	 * [--size=<string>]
	 * : Optional argument3 - Convert to specified image size for convert from classic editor.
	 *
	 * ## EXAMPLES
	 *
	 * wp box on
	 * // Switch the Lightbox On for all posts and all pages.
	 *
	 * wp box off
	 * // Switch the Lightbox Off for all posts and all pages.
	 *
	 * wp box on --exclude=1
	 * // Exclude ID 1.
	 *
	 * wp box on --exclude=1,2,3
	 * // Exclude ID 1 and 2 and 3.
	 *
	 * wp box on --include=1
	 * // Only ID 1.
	 *
	 * wp box on --include=1,2,3
	 * // Only ID 1 and 2 and 3.
	 *
	 * wp box on --size=large
	 * // Set media size to large.
	 *
	 * @when after_wp_load
	 * @param array $args  arguments.
	 * @param array $assoc_args  optional arguments.
	 * @since 1.00
	 */
	public function box_command( $args, $assoc_args ) {

		$files = $this->search_db_files();

		$input_error_message = 'Please enter the arguments.' . "\n";
		$input_error_message .= '1st argument(string) : on -> Lightbox On, off : Lightbox Off' . "\n";
		$input_error_message .= 'optional argument(int or string) : --exclude=1 or --exclude=1,2,3 : Post ID -> Exclude and process the specified IDs.' . "\n";
		$input_error_message .= 'optional argument(int or string) : --include=1 or --include=1,2,3 : Post ID -> Process only specified IDs.' . "\n";
		$input_error_message .= 'optional argument(string) : --size=large : Media size -> Convert to specified image size for convert from classic editor.' . "\n";

		if ( is_array( $args ) && ! empty( $args ) ) {
			$command_flag = $args[0];
			$exclude_id = 0;
			$include_id = 0;
			$media_size = null;
			if ( array_key_exists( 'exclude', $assoc_args ) ) {
				$exclude_id = $assoc_args['exclude'];
			}
			if ( array_key_exists( 'include', $assoc_args ) ) {
				$include_id = $assoc_args['include'];
			}
			if ( array_key_exists( 'size', $assoc_args ) ) {
				$media_size = $assoc_args['size'];
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
					'include'        => $include_id,
					'exclude'        => $exclude_id,
					'posts_per_page' => -1,
					'orderby'        => 'date',
					'order'          => 'ASC',
				);
				$posts = get_posts( $posts_args );
				$count = 0;
				foreach ( $posts as $post ) {
					$contents = $post->post_content;

					$contents = $this->convert_block( $contents, $media_size, $files );

					if ( preg_match_all( '/<!-- wp:image(.*?)-->/ims', $contents, $found ) !== false ) {
						if ( ! empty( $found[1] ) ) {
							foreach ( $found[1] as $value ) {
								$result = array();
								$values = json_decode( $value, true );
								$pid = false;
								if ( $values ) {
									if ( array_key_exists( 'lightbox', $values ) ) {
										if ( $values['lightbox']['enabled'] ) {
											$flag = 'true';
										} else {
											$flag = 'false';
										}
									} else {
										$flag = 'non';
									}
									if ( 'on' === $command_flag ) {
										if ( 'false' === $flag ) {
											$values['lightbox']['enabled'] = true;
											$value2 = wp_json_encode( $values, JSON_UNESCAPED_SLASHES );
											$contents = str_replace( $value, ' ' . $value2 . ' ', $contents );
											$post_arr = array(
												'ID' => $post->ID,
												'post_content' => $contents,
											);
											$pid = wp_update_post( $post_arr );
										} else if ( 'non' === $flag ) {
											$values_new['lightbox']['enabled'] = true;
											$values_new = array_merge( $values_new, $values );
											$value2 = wp_json_encode( $values_new, JSON_UNESCAPED_SLASHES );
											$contents = str_replace( $value, ' ' . $value2 . ' ', $contents );
											$post_arr = array(
												'ID' => $post->ID,
												'post_content' => $contents,
											);
											$pid = wp_update_post( $post_arr );
										}
									} else if ( 'off' === $command_flag ) {
										if ( 'true' === $flag ) {
											$values['lightbox']['enabled'] = false;
											$value2 = wp_json_encode( $values, JSON_UNESCAPED_SLASHES );
											$contents = str_replace( $value, ' ' . $value2 . ' ', $contents );
											$post_arr = array(
												'ID' => $post->ID,
												'post_content' => $contents,
											);
											$pid = wp_update_post( $post_arr );
										}
									}
								}
								if ( $pid ) {
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
		}
	}
}

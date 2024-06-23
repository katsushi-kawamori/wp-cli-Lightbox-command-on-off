<?php
/**
 * Cli Name:    Lightbox command on off
 * Description: Switch the Lightbox On and Off for all posts and all pages at once.
 * Version:     1.04
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
 * Lightbox command
 *
 * @param array $args  arguments.
 * @since 1.00
 */
function lightbox_command( $args ) {
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

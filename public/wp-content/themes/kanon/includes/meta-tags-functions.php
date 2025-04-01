<?php

//TITLE
// Фильтр для функции wp_get_document_title() вывода тега title. Начиная с WP 4.4 на нее заменили wp_title()
add_filter( 'pre_get_document_title', 'get_title_meta_tag' );
function get_title_meta_tag( ) {

  global $post;
  $queried_object = get_queried_object();
  $taxonomy = $queried_object->taxonomy;
  $term_id = $queried_object->term_id;

	if ( $taxonomy && $term_id && get_field( 'title' ) ) {
		return get_field( 'title', $taxonomy . '_' . $term_id );
	} elseif( get_field( 'title' ) ) {
		return get_field( 'title' );
	} else {
		return '';
	}
}

// DESCRIPTION
function get_meta_description( $taxonomy = '', $term_id = '' ) {

	if ( $taxonomy && $term_id && get_field( 'description', $taxonomy . '_' . $term_id ) ) {
		return get_field( 'description', $taxonomy . '_' . $term_id );
	} else {
		return get_field( 'description' );
	}
}


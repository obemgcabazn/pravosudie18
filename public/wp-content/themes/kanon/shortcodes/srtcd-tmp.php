<?php
/*
[shortcode_name]
 */
add_shortcode( 'shortcode_name', 'shortcode_name' );

function shortcode_name( $atts, $content ) {
  $atts = shortcode_atts( [
                            'title' => 'Заголовок',
                            'download' => ''
                          ], $atts );

  ob_start(); ?>

  <div class="heading"><?=$atts['title'] ?></div>

  <?php
  $output_string = ob_get_contents();
  ob_end_clean();
  return $output_string;
  wp_reset_postdata();
}

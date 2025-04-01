<?php

function var_dump_pre( $val ) {
  echo '<pre>';
  var_dump( $val );
  echo '</pre>';
}

function var_dump_foreach_pre( $array ) {
  echo '<pre>';
  foreach ( $array as $key ) {
    var_dump( $key );
  }
  echo '</pre>';
}

function print_pre( $val ) {
  echo '<pre>';
  print_r( $val );
  echo '</pre>';
}

function echo_br( $val ) {
  echo $val . "<br>";
}

function echo_br_foreach( $array ) {
  foreach ( $array as $key ) {
    echo_br( $key );
  }
}

function list_object( $object ) {
  if ( is_array( $object ) || is_object( $object ) ) {
    foreach ( $object as $key => $value ) {
      list_object( $value );
    }
  } else {
    echo $object . "<br>";
  }
}

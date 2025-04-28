<?php
/**
 * Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Blank Theme
 *
 *  1. print_func.php - Функции для форматирования вывода данных
 *  4. Вывод меню get_menu()
 *  5. Вывод мета тегов
 *      5.1 get_title_meta_tag
 *      5.2 get_meta_description
 *      5.3 get_meta_keywords
 *  6. Редирект для постоянных ссылок на файлы
 *  7. Функция is_tree() для определения является ли страница дочерней у заданной
 */
defined( 'ABSPATH' ) || exit;

$includes = array(
  '/includes/print_func.php',          // Функции для форматирования вывода данных
  '/includes/meta-tags-functions.php',
  '/includes/theme_class.php',

  // SHORTCODES
  '/shortcodes/srtcd-tmp.php',
);
foreach ($includes as $file){
  require_once get_template_directory() . $file;
}

/**
 * @var $args - Начальные параметры для темы
 */
$args = array(
  'styles' => array(
    array( 'handle' => 'banner', 'src' => 'css/banner.css?2', 'deps' => array() ),
    array( 'handle' => 'banner-style', 'src' => 'css/banner-styles.css?2', 'deps' => array() ),
    array( 'handle' => 'chevron', 'src' => 'css/chevron.css?2', 'deps' => array() ),
    array( 'handle' => 'iconochive', 'src' => 'css/iconochive.css?2', 'deps' => array() ),
    array( 'handle' => 'main', 'src' => 'css/main.css', 'deps' => array() ),
    array( 'handle' => 'navigation', 'src' => 'css/navigation.css?2', 'deps' => array() ),
    array( 'handle' => 'slider', 'src' => 'css/slider.css?2', 'deps' => array() ),
    array( 'handle' => 'styles', 'src' => 'css/styles.css?2', 'deps' => array() ),
  ),
  'scripts' => array(
    array( 'handle' => 'jquery', 'src' => 'js/jquery.min.js', 'deps' => array( ) ),
    array( 'handle' => 'wombat', 'src' => 'js/wombat.js', 'deps' => array( ) ),
    array( 'handle' => 'api', 'src' => 'js/api.js', 'deps' => array( 'jquery' ) ),
    array( 'handle' => 'app', 'src' => 'js/app.js', 'deps' => array( ) ),
    array( 'handle' => 'athena', 'src' => 'js/athena.js', 'deps' => array( ) ),
    array( 'handle' => 'bundle', 'src' => 'js/bundle-playback.js', 'deps' => array( ) ),
    array( 'handle' => 'donation', 'src' => 'js/donation-banner.min.js', 'deps' => array( ) ),
    array( 'handle' => 'navigation', 'src' => 'js/navigation.js', 'deps' => array( ) ),
    array( 'handle' => 'polyfill', 'src' => 'js/polyfill.min.js', 'deps' => array( ) ),
    array( 'handle' => 'recaptcha__en', 'src' => 'js/recaptcha__en.js', 'deps' => array( ) ),
    array( 'handle' => 'ruffle', 'src' => 'js/ruffle.js', 'deps' => array( ) ),
    array( 'handle' => 'script', 'src' => 'js/scripts.js', 'deps' => array( ) ),
  ),
  'nav_menus' => array(
    'header_menu' => __( 'Верхнее меню' ),
    'footer_menu' => __( 'Меню в подвале' ),
  ),
  'widgets' => array(
    array(
      'id'            => 'search_widget',
      'name'          => 'Поиск',
      'description'   => 'Перетащите сюда виджет поиска, чтобы добавить его в шапку.',
    ),
  ),
);
$theme = new Theme_Class( $args );

/* Подключение скриптов в хуки */
//add_action( 'init', function(){
//  wp_register_script( 'slick', get_template_directory_uri() . '/js/assets/slick.min.js', array('jquery'), '', true );
//} );

// Удаляет слово Архив из заголовка Рубрики
add_filter( 'get_the_archive_title', function( $title ){
  return preg_replace('~^[^:]+: ~', '', $title );
});


// Для того, чтобы вывести script.js как ES модуль - type="module"
add_filter('script_loader_tag', 'add_type_attribute' , 10, 3);
function add_type_attribute($tag, $handle, $src) {
  if ( 'script' !== $handle ) {
    return $tag;
  }

  $tag = '<script type="module" src="' . esc_url( $src ) . '"></script>';
  return $tag;
}

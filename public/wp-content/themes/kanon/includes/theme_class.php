<?php

class Theme_Class {
  private $_args    = array();
  private $_styles  = array();
  private $_scripts = array();
  private $_widgets = array();

  public function __construct( $args ){
    /** Начальные установки */
    $this->set_args( $args );
    $this->init();

    /** Подключаем стили */
    add_action( 'wp_print_styles', array( $this, 'enqueue_styles' ) );

    /** Подключаем скрипты */
    add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

    /** Регистрируем меню */
    register_nav_menus( $this->_args[ 'nav_menus' ] );

    /** Добавляем виджеты */
    add_action( 'widgets_init', array( $this, 'theme_widgets' ) );

    /** Удаляем кнопки с панели администрирования */
    add_action( 'wp_before_admin_bar_render', array( $this, 'remove_admin_bar_links' ) );
  }

  /** Сохраняем переданные в конструктор параметры */
  private function set_args( $args ){
    $this->_args   = $args;
    $this->_styles = $args[ 'styles' ];

    if( isset( $args[ 'scripts' ] ) ) {
      $this->_scripts = $args[ 'scripts' ];
    }
    if( isset( $args[ 'widgets' ] ) ) {
      $this->_widgets = $args[ 'widgets' ];
    }
    if( isset( $args[ 'admin_scripts' ] ) ) {
      $this->_admin_scripts = $args[ 'admin_scripts' ];
    }
  }

  /** Начальная инициализация */
  private function init(){

    // if (!is_admin()) show_admin_bar(false);

    /** Путь к нашей теме */
    define( 'THEMEPATH', get_bloginfo( 'template_url' ) . '/' );

    /** Отключаем вывод информации о движке */
    remove_action( 'wp_head', 'wp_generator' );

    // Отключаем сжатие изображений
    add_filter(
      'jpeg_quality', function( $quality ){
      return 100;
    } );

    // Поддержка виджетов
    if( !function_exists( 'theme_setup' ) ) {
      function theme_setup(){
        add_theme_support( 'html5', array( 'search-form', 'script', 'style' ) );
        add_theme_support( 'title-tag' );
        add_theme_support( 'widgets' );
        add_theme_support( 'post-thumbnails' );
      }
    }
    add_action( 'after_setup_theme', 'theme_setup' );

    /* удаляем shortlink */
    remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 );

    // Убираем мусор из шапки
    remove_action( 'wp_head', 'wp_generator' );
    remove_action( 'wp_head', 'wlwmanifest_link' );
    remove_action( 'wp_head', 'rsd_link' );

    // убираем emoji
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );

    remove_filter( 'the_content', 'wptexturize' ); /* убираем авотдобавление параграфиов */

    // Удаляем RSS ленту
    function fb_disable_feed(){
      wp_redirect( get_option( 'siteurl' ) );//будет осуществляться редирект на главную страницу
    }

    add_action( 'do_feed', 'fb_disable_feed', 1 );
    add_action( 'do_feed_rdf', 'fb_disable_feed', 1 );
    add_action( 'do_feed_rss', 'fb_disable_feed', 1 );
    add_action( 'do_feed_rss2', 'fb_disable_feed', 1 );
    add_action( 'do_feed_atom', 'fb_disable_feed', 1 );
    add_action( 'do_feed_rss2_comments', 'fb_disable_feed', 1 );
    add_action( 'do_feed_atom_comments', 'fb_disable_feed', 1 );
    remove_action( 'wp_head', 'feed_links_extra', 3 );
    remove_action( 'wp_head', 'feed_links', 2 );

    // Для функции the_excerpt() - Размер анонса новостей в количестве слов
    add_filter(
      'excerpt_length', function(){
      return 20;
    } );
  }

  /** Подключение стилей */
  public function enqueue_styles(){
    foreach( $this->_styles as $style ) {
      wp_enqueue_style( $style[ 'handle' ], THEMEPATH . $style[ 'src' ], $style[ 'deps' ] );
    }
  }

  /** Подключение скриптов */
  public function enqueue_scripts(){
    wp_deregister_script('jquery');
    foreach( $this->_scripts as $script ) {
      wp_enqueue_script( $script[ 'handle' ], THEMEPATH . $script[ 'src' ], $script[ 'deps' ], '', true );
    }
  }

  /** Подключение виджетов */
  public function theme_widgets(){
    foreach( $this->_widgets as $widget ) {
      register_sidebar( $widget );
    }
  }

  /** Удаляем кнопки с панели администрирования */
  function remove_admin_bar_links(){
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu( 'wp-logo' );          // Remove the WordPress logo
    $wp_admin_bar->remove_menu( 'about' );            // Remove the about WordPress link
    $wp_admin_bar->remove_menu( 'wporg' );            // Remove the WordPress.org link
    $wp_admin_bar->remove_menu( 'documentation' );    // Remove the WordPress documentation link
    $wp_admin_bar->remove_menu( 'support-forums' );   // Remove the support forums link
    $wp_admin_bar->remove_menu( 'feedback' );         // Remove the feedback link
    $wp_admin_bar->remove_menu( 'view-site' );        // Remove the view site link
    $wp_admin_bar->remove_menu( 'updates' );          // Remove the updates link
    $wp_admin_bar->remove_menu( 'comments' );         // Remove the comments link
    $wp_admin_bar->remove_menu( 'new-content' );      // Remove the content link
    $wp_admin_bar->remove_menu( 'w3tc' );             // If you use w3 total cache remove the performance link
  }

}

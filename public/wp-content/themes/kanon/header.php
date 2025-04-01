<?php
/**
 * The header for our theme
 *
 * Displays all of the <head> and <header> section
 *
 * @package WordPress
 * @subpackage Blank Template
 * @since Blank Template 1.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$tpl_dir = get_bloginfo( 'template_url' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <?php require 'templates/meta-properties.php' ?>
  <?php // require 'templates/favicons.php' ?>
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="wrapper">

  <div id="pillow"></div>
  <div id="header-nav">
    <div id="hot-line">
      <i></i>
      <i><span></span><a href="tel:+79225005888">8-922-500-5888</a></i>
      <i onclick="javascript:send_question_on();" title="Открыть форму отправки сообщения"><span class="sendQuestion"></span><a href="mailto:pravosudie18@bk.ru">эл. почта</a></i>
    </div>
    <div id="nav-points" class="">
      <div class="brand" style="display: inline-flex;"><a data-event="toggle_menu" class="link-toggle lto"><b></b></a></div>
      <ul id="nav-ul" class="toggle-off">
        <li id="pravo"><a href="/"><i><img src="/img/favicon450.png" alt="Правосудие18"></i><b>Правосудие 18</b></a></li>
        <li class="dropdown"><a href="/advokat/">Адвокат</a><span class="caret"></span>
          <ul class="dropdown-menu" id="line" name="ul">
            <!--            <li class="dropdown-right"><a href="/advokat/ugolovnoe_pravo">Уголовные дела</a><span class="caret-right"></span>-->
            <!--              <ul class="dropdown-menu-right" name="ul">-->
            <!--                <li><a href="/advokat/ugolovnoe_pravo/dosuda">Досудебное производство</a></li>-->
            <!--                <li><a href="/advokat/ugolovnoe_pravo/sud">Судебное производство</a></li>-->
            <!--                <li><a href="/advokat/ugolovnoe_pravo/economic_crime">Экономические и налоговые преступления</a></li>-->
            <!--                <li class="dropdown-right-next" style="position: relative;"><a href="/advokat/ugolovnoe_pravo/narkotiki228/">Оборот наркотиков</a><span id="rightDropList" class="caret-right" style="color: rgb(42, 193, 160);"></span>-->
            <!--                  <ul class="dropdown-menu-right-next" name="ul" style="top: 0px; right: -14em; display: none;">-->
            <!--                    <li class="dropdown-right-next-next"><a href="/advokat/ugolovnoe_pravo/narkotiki228/problema228">Проблема 228</a><span class="caret-right"></span>-->
            <!--                      <ul class="dropdown-menu-right-next-next" name="ul" style="width: 289px;">-->
            <!--                        <li><a href="/advokat/ugolovnoe_pravo/narkotiki228/problema228/dopros_povedenie">Как вести себя на допросе?</a></li>-->
            <!--                        <li><a href="/advokat/ugolovnoe_pravo/narkotiki228/problema228/komentarii228">Комментарий к статье 228</a></li>-->
            <!--                        <li><a href="/advokat/ugolovnoe_pravo/narkotiki228/problema228/razmer_narkotikov">Таблица с размерами нарк. веществ</a></li>-->
            <!--                      </ul>-->
            <!--                    </li>-->
            <!--                  </ul>-->
            <!--                </li>-->
            <!--                <li class="dropdown-right-next" style="position: relative;"><a href="/advokat/ugolovnoe_pravo/ud">Другие виды уголовных дел</a><span id="rightDropListUd" class="caret-right" style="color: rgb(42, 193, 160);"></span>-->
            <!--                  <ul class="dropdown-menu-right-next" name="ul" style="top: 0px; right: -21.5em; display: none;">-->
            <!--                    <li><a href="/advokat/ugolovnoe_pravo/ud/krazha">Кражи, Мошенничества</a></li>-->
            <!--                    <li><a href="/advokat/ugolovnoe_pravo/ud/razboj">Разбои, Грабежи</a></li>-->
            <!--                    <li><a href="/advokat/ugolovnoe_pravo/ud/vred_zdorovju">Причинение вреда здоровью</a></li>-->
            <!--                    <li><a href="/advokat/ugolovnoe_pravo/ud/organized_crime">Организованная преступность</a></li>-->
            <!--                  </ul>-->
            <!--                </li>-->
            <!--              </ul>-->
            <!--            </li>-->
            <li class="dropdown-right"><a href="/advokat/grazhdanskoe_pravo">Гражданские дела</a><span class="caret-right"></span>
              <ul class="dropdown-menu-right" name="ul">
                <li><a href="/advokat/grazhdanskoe_pravo/dogovor">Сделки, договора</a></li>
                <li><a href="/advokat/grazhdanskoe_pravo/trudovoe_pravo">Трудовое право</a></li>
                <li><a href="/advokat/grazhdanskoe_pravo/semeinoe_pravo">Семейное право</a></li>
                <li><a href="/advokat/grazhdanskoe_pravo/nasledstvo">Наследство</a></li>
              </ul>
            </li>
            <!--            <li class="dropdown-right"><a href="/advokat/dosrochnoe_osvobozhdenie">Досрочное осбовождение</a><span class="caret-right"></span>-->
            <!--              <ul class="dropdown-menu-right" id="fi" name="ul">-->
            <!--                <li><a href="/advokat/dosrochnoe_osvobozhdenie">Условно-досрочное освобождение</a></li>-->
            <!--                <li><a href="/advokat/dosrochnoe_osvobozhdenie/smena_rezhyma">Изменение вида исправительного учреждения</a></li>-->
            <!--                <li><a href="/advokat/dosrochnoe_osvobozhdenie/snyatie_sudimosti">Снятие судимости</a></li>-->
            <!--              </ul>-->
            <!--            </li>-->
            <li><a href="/advokat/uslugi">Стоимость услуг</a></li>
            <li><a href="/advokat/kontakty">Контакты</a></li>
          </ul>
        </li>

        <li class="dropdown"><a href="/urist">Юрист</a><span class="caret"></span>
          <ul class="dropdown-menu" id="linesecond" name="ul">
            <li><a href="/urist/arbitrazhnoe_pravo">Арбитражное право</a></li>
            <li><a href="/bankrotstvo">Банкротство</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
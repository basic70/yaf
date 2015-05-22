<?php
/**
 * Theme related functions. 
 *
 */

/**
 * Get title for the webpage by concatenating page specific title with site-wide title.
 *
 * @param string $title for this page.
 * @return string/null wether the favicon is defined or not.
 */
function get_title($title) {
  global $yaf;
  return $title . (isset($yaf['title_append']) ? $yaf['title_append'] : null);
}

function generate_menu($menu , $class) {
  $items = $menu['items'];
  $html = "<nav><ul class='$class'>\n";
  $page = basename($_SERVER['SCRIPT_FILENAME']);
  foreach ($items as $key => $item) {
    $class = ($page == $item['url']) ? "class='selected active'" : null;
    $html .= "<li role='presentation'><a href='{$item['url']}' {$class}>{$item['text']}</a></li>\n";
  }
  $html .= "</ul></nav>\n";
  return $html;
}

function generate_breadcrumbs($breadcrumbs)
{
	$html = <<<EOD
<div id='breadcrumbs'>
	<ol class="breadcrumb">
EOD;
	foreach($breadcrumbs as $smula) {
		foreach ($smula as $text => $link) {
			$html .= '<li><a href="' . $link . '">' . $text . '</a></li>';
		}
	}
	$html .= <<<EOD
	</ol>
</div>
EOD;
	return $html;
}


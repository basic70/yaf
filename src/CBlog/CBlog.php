<?php

class CBlog
{
	private $content;
	private $title;

	public function __construct($content)
	{
		$this->content = $content;
	}

	public function get_title()
	{
		return $this->title;
	}

	public function build_html($record, $set_title = false)
	{
		// Sanitize content before using it.
		$title  = $record->get_title();
		$date   = $record->get_published();
		$data   = $record->get_body();
		$category = $record->get_field('category');

		$viewLink = $record->get_view_url();
		$readmore = null;
		$editLink = null;
		if ($set_title) {
			$this->title = $title;
			if (CUser::is_authenticated()) {
				$editLink = "<a href='" .  $record->get_edit_url() . "'>Uppdatera posten</a>";
				$d = $record->get_delete_url();
				if (!empty($d)) {
					$editLink .= " <a href='" .  $d . "'>Ta bort posten</a>";
				}
			}
		} else {
			$pos = strpos($data, "\n");
			// triple equals according to http://php.net/strpos
			if ($pos !== false) {
				$data = substr($data, 0, $pos);
				$readmore = "<a href='{$viewLink}'>Läs mer »</a>";
			}
		}

		$headertag = $set_title ? 'h1' : 'h2';
		$cat_text = !empty($category) ?
			" i kategori <a href='news.php?category={$category}'>{$category}</a>" :
			null;
		$html = <<<EOD
<section>
  <article>
    <header>
      <{$headertag}><a href='{$viewLink}'>{$title}</a></{$headertag}>
	  <em>Publicerat {$date}</em>{$cat_text}
	  <br/><br/>
    </header>
    {$data}
EOD;
		$html .= $readmore;
		if ($editLink)
			$html .= "<footer>{$editLink}</footer>\n";
		$html .= <<<EOD
  </article>
</section>
EOD;
		return $html;
	}

	public function load_and_build($slug)
	{
        // Prepare content and store it all in variables in the Yaf container.
        $this->title = "Bloggen";

        $contents = [
            'html' => ''
        ];

		// Get content
		$params = array();
		if ($slug) {
			$params['slug'] = $slug;
		}
		$params['order'] = 'updated desc';
		$res = $this->content->get_records('post', $params);

		if (isset($res[0])) {
            foreach($res as $record) {
                $contents['html'] .= $this->build_html($record, !empty($slug));
		    }
		} else if ($slug) {
            $contents['html'] = "Det fanns inte en sådan bloggpost.";
		} else {
            $contents['html'] = "Det fanns inga bloggposter.";
		}

        if (!isset($contents['title']))
            $contents['title'] = $this->title;

		return $contents;
	}

}

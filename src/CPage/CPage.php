<?php

class CPage
{
	private $content;
	private $url;
	private $title = null;

	public function __construct($content, $url)
	{
		$this->content = $content;
		$this->url = $url;
	}

	public function get_title()
	{
		return $this->title;
	}

	public function build_html()
	{
		$records = $this->content->get_records('page', [ 'url' => $this->url ]);

		if (empty($records)) {
		  //header('Location: content.php');
		  die('Misslyckades: det finns inget innehåll.');
		  //return null;
		}

		$user = new CUser();
		$html = '';
		foreach($records as $c) {
			// Sanitize content before using it.
			$data   = $c->get_body();

			// Prepare content and save the title for access later.
			$title = $c->get_title();
			$this->title = $title;

			$editLink = null;
			if ($user->is_authenticated()) {
				$editLink = "<a href='" . $c->get_edit_url() . "'>Uppdatera sidan</a>";
			}

			$html .= <<<EOD
<article>
  <header>
    <h1>{$title}</h1>
  </header>
  {$data}
  <footer>{$editLink}</footer
</article>
EOD;
		}
		return $html;
	}

}


<?php

class CMovieSearch
{
	private $db;	// CDatabase
	private $original = array();
	private $get_active_genres_sql;
	private $rows;
	private $max;

	private $table_movie;
	private $table_movie2genre;
	private $table_genre;

	private $id;
	private $edit = false;
	private $title;
	private $genre;
	private $hits;
	private $page;
	private $year1;
	private $year2;
	private $orderby;
	private $order;

	private function prefixed($dbconfig, $name)
	{
		if (isset($dbconfig['prefix']))
			return $dbconfig['prefix'] . '_' . $name;
		return $name;
	}

	private function update_record($options)
	{
		//var_dump($options);
		//var_dump($_FILES);
		$movie = null;
		if (!isset($options['genre'])) {
			$_SESSION['message'] = "Genre is mandatory";
			$_SESSION['options'] = $options;
			header('Location: ' . $this->get_edit_link($this->id));
			exit;
		}
		unset($_SESSION['options']);
		if (isset($this->id))
			$movie = $this->fetch_single_movie();
		$fields = array();
		$params = array();
		$markers = array();
		$valid_keys = array(
			'title',
			'year',
			'plot',
			'price',
			'imdbid',
			'youtubeid',
		);

		if (empty($movie)) {
			$sql = 'INSERT INTO ' . $this->table_movie . ' (';
			foreach ($valid_keys as $key) {
				if (isset($options[$key])) {
					$markers[] = '?';
					$fields[] = $key;
					$params[] = $options[$key];
				}
			}
			$sql .= implode(', ', $fields) .  ') VALUES (' .  implode(', ', $markers) .  ')';
			//var_dump($sql);
			$this->db->ExecuteQuery($sql, $params);
			$this->id = $this->db->LastInsertId();
		} else {
			$sql = 'UPDATE ' . $this->table_movie . ' SET updated=current_timestamp(), ';
			//var_dump($options);
			foreach ($valid_keys as $key) {
				if (isset($options[$key])) {
					$value = $options[$key];
					//var_dump($value);
					if ($movie->$key != $value) {
						$fields[] = $key;
						$params[] = $value;
					}
				}
			}
			$fields = array_map(function($key) { return $key . '=?'; }, $fields);
			$sql .= implode(', ', $fields) . ' WHERE id=?';
			$params[] = $this->id;
			$this->db->ExecuteQuery($sql, $params);

			// clear the genres list
			$sql = 'DELETE FROM ' . $this->table_movie2genre . ' WHERE idMovie=?';
			$this->db->ExecuteQuery($sql, array($this->id));
		}

		if (isset($_FILES) &&
			isset($_FILES['posterfile']) &&
			isset($_FILES['posterfile']['name']) &&
			!empty($_FILES['posterfile']['name'])
			) {
			$new_file_name = 'img/movie/poster-' . $this->id;
			if ($_FILES['posterfile']['type'] == 'image/jpeg')
				$new_file_name .= '.jpg';
			if (move_uploaded_file($_FILES['posterfile']['tmp_name'], $new_file_name)) {
				$sql = 'UPDATE ' . $this->table_movie .
					' SET updated=NOW(), image=? WHERE id=?';
				$this->db->ExecuteQuery($sql, array($new_file_name, $this->id));
			}
		}

		// add new/updated genres
		$sql = 'INSERT INTO ' . $this->table_movie2genre . ' (idMovie, idGenre) VALUES (?, ?)';
		$ds = $this->db->Prepare($sql);
		foreach ($options['genre'] as $genreid) {
			$this->db->ExecutePrepared($ds, false, array($this->id, $genreid));
		}

		header('Location: ' . $this->get_view_link($this->id)); exit;
	}

	private function delete_record()
	{
		if (empty($this->id))
			return;
		$params = array($this->id);
		$sql = "DELETE FROM " . $this->table_movie2genre .  " WHERE idMovie=?";
		$this->db->ExecuteQuery($sql, $params);
		$sql = "DELETE FROM " . $this->table_movie .  " WHERE id=?";
		$this->db->ExecuteQuery($sql, $params);
		//var_dump($sql);
		header('Location: movies.php');
		exit;
	}

	public function __construct($dbconfig, $options = null, $post_options = null)
	{
		parse_str($_SERVER['QUERY_STRING'], $this->original);
		unset($this->original['edit']);
		unset($this->original['delete']);
		$this->db = new CDatabase($dbconfig);
		$this->table_movie = $this->prefixed($dbconfig, 'Movie');
		$this->table_movie2genre = $this->prefixed($dbconfig, 'Movie2Genre');
		$this->table_genre = $this->prefixed($dbconfig, 'Genre');

		$this->get_active_genres_sql = $this->db->Prepare(
		  'SELECT DISTINCT G.name
		  FROM ' . $this->table_genre . ' AS G
			INNER JOIN ' . $this->table_movie2genre . ' AS M2G
			  ON G.id = M2G.idGenre
			ORDER BY G.name');

		$this->find_movie_sql = '
			SELECT 
				M.*,
			GROUP_CONCAT(G.name SEPARATOR ", ") AS genre
			FROM ' . $this->table_movie . ' AS M
			LEFT OUTER JOIN ' . $this->table_movie2genre . ' AS M2G
				ON M.id = M2G.idMovie
			INNER JOIN ' . $this->table_genre . ' AS G
				ON M2G.idGenre = G.id';

		// Handle parameters

		$this->id = isset($options['id']) ? $options['id'] : null;
		if (CUser::is_authenticated()) {
			if (!empty($post_options))
				$this->update_record($post_options);
			if (isset($options['delete']))
				$this->delete_record();
			if (isset($options['edit']))
				$this->edit = true;
		}
		$this->title    = isset($options['title']) ? $options['title'] : null;
		$this->genre    = isset($options['genre']) ? $options['genre'] : null;
		$this->hits     = isset($options['hits'])  ? $options['hits']  : 8;
		$this->page     = isset($options['page'])  ? $options['page']  : 1;
		$this->year1    = isset($options['year1']) && !empty($options['year1']) ? $options['year1'] : null;
		$this->year2    = isset($options['year2']) && !empty($options['year2']) ? $options['year2'] : null;
		$this->orderby  = isset($options['orderby']) ? strtolower($options['orderby']) : 'id';
		$this->order    = isset($options['order'])   ? strtolower($options['order'])   : 'asc';

		// Check that incoming parameters are valid
		is_numeric($this->hits) or die('Check: Hits must be numeric.');
		is_numeric($this->page) or die('Check: CPage must be numeric.');
		is_numeric($this->year1) || !isset($this->year1)  or die('Check: Startyear must be numeric or not set.');
		is_numeric($this->year2) || !isset($this->year2)  or die('Check: Endyear must be numeric or not set.');
	}

	public function get_sql_dump() {
		return $this->db->Dump();
	}

	// Get all genres that are active
	private function get_genres()
	{
		return $this->db->ExecutePrepared($this->get_active_genres_sql, true);
	}

	private function get_query_string($options, $pagename = "movies.php")
	{
		// Modify the existing query string with new options
		$query = array_merge($this->original, $options);

		if (!empty($query['genre'])) {
			unset($query['id']);
		}

		// Return the modified querystring
		//return 'movies.php?' . htmlentities(http_build_query($query));
		return $pagename . '?' . http_build_query($query);
	}

	public function get_view_link($id = null)
	{
		$options = array();
		if (!empty($id))
			$options['id'] = $id;
		return $this->get_query_string($options, basename($_SERVER['SCRIPT_FILENAME']));
	}

	public function get_edit_link($id = null)
	{
		$options = array();
		if (!empty($id))
			$options['id'] = $id;
		$options['edit'] = 1;
		return $this->get_query_string($options, basename($_SERVER['SCRIPT_FILENAME']));
	}

	private function get_genre_link($genre)
	{
		if (isset($this->genre) && ($genre == $this->genre)) {
			return $genre;
		}
		return "<a href='" . $this->get_query_string(array('genre' => $genre, 'page' => 1)) . "'>{$genre}</a> ";
	}

	private function get_genre_html()
	{
		$genres = $this->get_genres();
		$items = array();
		foreach ($genres as $val) {
			$items[] = $this->get_genre_link($val->name);
		}
		return implode(" ", $items);
	}

	public function get_genre_list()
	{
		unset($this->genre);
		return $this->get_genre_html();
	}

	public function build_search_form()
	{
		$genre_html = $this->get_genre_html();

		return <<<EOD
<form>
  <fieldset>
  <legend>Sök</legend>
  <input type=hidden name=genre value='{$this->genre}'/>
  <input type=hidden name=hits value='{$this->hits}'/>
  <input type=hidden name=page value='1'/>
  <p><label>Titel (delsträng, använd % som *):</label> <input type='search' name='title' value='{$this->title}'/></p>
  <p><label>Välj genre:</label> {$genre_html}</p>
  <p><label>Skapad mellan åren:</label>
      <input type='text' name='year1' value='{$this->year1}'/>
      - 
      <input type='text' name='year2' value='{$this->year2}'/>
  </p>
  <p><input type='submit' name='submit' value='Sök'/></p>
  <p><a href='?'>Visa alla</a></p>
  </fieldset>
</form>
EOD;
	}

	/**
	 * Create links for hits per page.
	 *
	 * @param array $hits a list of hits-options to display.
	 * @return string as a link to this page.
	 */
	private function getHitsPerPage($hits)
	{
		$nav = "Träffar per sida: ";
		foreach ($hits AS $val) {
			if ($this->hits == $val) {
				$nav .= "$val ";
			} else {
				$nav .= "<a href='" . $this->get_query_string(array('hits' => $val, 'page' => 1)) . "'>$val</a> ";
			}
		}
		return $nav;
	}

	/**
	 * Function to create links for sorting
	 *
	 * @param string $column the name of the database column to sort by
	 * @return string with links to order by column.
	 */
	private function build_orderby($column)
	{
		$nav  = "<a href='" .
			$this->get_query_string(array('orderby' => $column, 'order' => 'asc')) .
			"'>&darr;</a>";
		$nav .= "<a href='" .
			$this->get_query_string(array('orderby' => $column, 'order' => 'desc')) .
			"'>&uarr;</a>";
		return "<span class='orderby'>" . $nav . "</span>";
	}

	private function genres_as_links($genres)
	{
		$list = explode(",", $genres);
		$links = [];
		foreach ($list as $genre) {
			$links[] = $this->get_genre_link(trim($genre));
		}
		return implode(' ', $links);
	}

    public function get_movie_title_link($movie)
    {
        return "<a href='movie.php?id=" . $movie->id . "'>" . $movie->title . "</a>";
    }

	private function build_rows($movies)
	{
		// Put results into a HTML-table
		$tr = "<thead>";
		$tr .= "<tr>" .
			"<th>&nbsp;</th>" .
			"<th>Titel " . $this->build_orderby('title') . "</th>" .
			"<th>Pris " . $this->build_orderby('price') . "</th>" .
			"<th>År " . $this->build_orderby('year') . "</th>" .
			"<th>Genre(s)</th></tr>";
		$tr .= "</thead>";

		$tr .= "<tbody>";
		foreach ($movies AS $key => $val) {
			$tr .=
				"<td>" .
				$this->build_image_tag($val->image, $val->title, 80) .
				"</td>" .
				"<td>" . $this->get_movie_title_link($val) . "</td>" .
				"<td>{$val->price}</td>" .
				"<td>{$val->year}</td>" .
				"<td>" . $this->genres_as_links($val->genre) . "</td></tr>";
		}
		$tr .= "</tbody>";

		return $tr;
	}

	/**
	 * Create navigation among pages.
	 *
	 * @return string as a link to this page.
	 */
	private function getPageNavigation()
	{
		$min = 1;
		$nav  = ($this->page != $min) ?
			"<a href='" . $this->get_query_string(array('page' => $min)) . "'>&lt;&lt;</a> " :
			'&lt;&lt; ';
		$nav .= ($this->page > $min) ?
			"<a href='" . $this->get_query_string(array('page' => ($this->page > $min ? $this->page - 1 : $min) )) . "'>&lt;</a> " :
			'&lt; ';

		for ($i = $min; $i <= $this->max; $i++) {
			if ($this->page == $i) {
				$nav .= "$i ";
			} else {
				$nav .= "<a href='" . $this->get_query_string(array('page' => $i)) . "'>$i</a> ";
			}
		}

		$nav .= ($this->page < $this->max) ?
			"<a href='" . $this->get_query_string(array('page' => ($this->page < $this->max ? $this->page + 1 : $this->max) )) . "'>&gt;</a> " :
			'&gt; ';
		$nav .= ($this->page != $this->max) ?
			"<a href='" . $this->get_query_string(array('page' => $this->max)) . "'>&gt;&gt;</a> " :
			'&gt;&gt; ';
		return $nav;
	}

	public function build_result_table()
	{
		$movies = $this->get_selected_movies();
		if ((count($movies) == 1) && !$this->hits && !$this->page) {
			header('Location: movie.php?id=' . $movies[0]->id);
			exit;
		}
		$hitsPerPage = $this->getHitsPerPage(array(2, 4, 8));
		$navigatePage = $this->getPageNavigation();

		$html = "";

		if (CUser::is_authenticated()) {
			$html .= "<a href='movie.php?edit=1'>Ny film</a><br>\n";
		}

		$html .= "<div class='dbtable'>\n";
		$html .= "<div class='rows'>" . $this->rows . " träff" .
			(($this->rows != 1) ? "ar" : "") .
			". " . $hitsPerPage . "</div>\n";
		$html .= "<table>" . $this->build_rows($movies) . "</table>\n";
		$html .= "<div class='pages'>" . $navigatePage . "</div>\n";
		$html .= "</div>\n";
		return $html;
	}

	private function fetch_single_movie()
	{
		$this->movie = null;
		if (empty($this->id))
			return null;
		$sql = $this->find_movie_sql . " WHERE M.id=? LIMIT 1";
		$params = [ $this->id ];
		$movies = $this->db->ExecuteSelectQueryAndFetchAll($sql, $params);
		if (empty($movies))
			return null;
		$this->movie = $movies[0];
		return $this->movie;
	}

	private function build_image_tag($image, $alt, $width = -1)
	{
		if (strncmp($image, 'img/', 4) == 0)
			$image = substr($image, 4);
		$html = "<img src='img.php?src=" . $image;
		$html_width = null;
		if ($width > 0) {
			$html .= '&width=' . $width;
			$html_width = "width='" . $width . "'";
		}
		$html .= "' {$html_width} alt='{$alt}' />";
		return $html;
	}

	private function build_movie_title()
	{
		$movie = $this->movie;

		if (empty($movie->imdbid))
			return $movie->title;
		return "<a href='http://www.imdb.com/title/{$movie->imdbid}' target='_blank'>" .
			$movie->title .
			"</a>";
	}

    public function get($fieldname)
    {
        if (isset($this->$fieldname))
            return $this->$fieldname;
        $movie = $this->movie;
        if (isset($movie->$fieldname))
            return $movie->$fieldname;
        return null;
    }

	private function build_movie_form()
	{
		$movie = $this->movie;
		$html = <<<EOD
<form method="post" enctype="multipart/form-data">
  <fieldset>
    <legend>Skapa/uppdatera filminformation</legend>
EOD;
		if (isset($_SESSION['message'])) {
			$html .= '<strong class="bg-warning">' . $_SESSION['message'] . '</strong>';
		}
		if (!empty($movie->id))
			$html .= "<input type='hidden' name='id' value='{$movie->id}'/>\n";

        $hb = new CHtmlBuilder($this,
            [ 'title',  'year', 'plot', 'price', 'imdbid', 'youtubeid' ]);

        $html .= $hb->build_input_tag('Titel', 'title', [ 'placeholder' => 'Filmens titel' ]);

        $html .= $hb->build_input_tag('År', 'year', [ 'type' => 'number' ]);

        $html .= $hb->build_input_tag('Synopsis', 'plot', [
            'tag' => 'textarea',
            'rows' => 4,
        ]);

		$sql = 'SELECT id, name FROM ' . $this->table_genre . ' ORDER BY name';
		$genres = $this->db->ExecuteSelectQueryAndFetchAll($sql);
		if (!empty($genres)) {
			$current_genres = [];
			if (isset($movie->genre)) {
				foreach (explode(",", $movie->genre) as $genre) {
					$current_genres[trim($genre)] = true;
				}
			}
			$html .= '<div class="form-group form-inline">';
			$html .= "<label>Kategorier</label><br/>\n";
			$html .= '<div class="form-control">';
			foreach ($genres as $record) {
				$html .= '<div class="checkbox">' .
					' <label>' .
					'  <input type="checkbox" name="genre[]" value="' . $record->id . '"';
				if (isset($current_genres[$record->name]))
					$html .= ' checked';
				$html .= '> ' .
						$record->name .  '</input>' .
					' </label>' .
					"</div>\n";
			}
			$html .= '</div>';
			$html .= '</div>';
		}

        $html .= $hb->build_input_tag('Pris/dygn', 'price', [
            'type' => 'number',
            'min' => 1,
            'max' => 99,
		]);

        $html .= $hb->build_input_tag('ID på IMDB', 'imdbid', [
            'placeholder' => 'ID på IMDB (ttnnnn)'
        ]);

        $html .= $hb->build_input_tag('ID på Youtube', 'youtubeid', [
            'placeholder' => 'ID på Youtube'
        ]);

        $html .= $hb->build_input_tag('Poster', 'posterfile', [ 'type' => 'file' ]);

		$html .= "
			<div class='form-group'>
				<button class='btn btn-default' type='submit' name='save'>Spara</button>
				<button class='btn' type='reset'>Återställ</button>
  			</div>";

		$html .= <<<EOD
  </fieldset>
</form>
EOD;
		unset($_SESSION['message']);
		return $html;
	}

	public function build_movie_view()
	{
		$this->fetch_single_movie();
		if ($this->edit)
			return $this->build_movie_form();
		$movie = $this->movie;
		if (empty($movie))
			return null;
		$html = "<h1>" . $this->build_movie_title();
		if (!empty($movie->year))
			$html .= ' <small>(' . $movie->year . ')</small>';
		$html .= "</h1>";
		$img = $movie->image;
		if (!empty($img)) {
			$html .= "<div class='poster'>" .
				$this->build_image_tag($img, $movie->title, 300) .
				"</div>";
		}
		$filter = new CTextFilter;
		$html .= "<p>Kategorier: " . $this->genres_as_links($movie->genre) . "</p>\n";
		$html .= "<p>Pris/dygn: " . $movie->price . " kr</p>\n";
		$html .= $filter->doFilter($movie->plot, 'markdown') . "\n";
		$html .= '<div style="clear:both" />';
		if (!empty($movie->youtubeid)) {
			$html .= <<<EOD
<div id="trailer">
  <h2>Trailer</h2>
  <iframe width="560" height="315" src="https://www.youtube.com/embed/{$movie->youtubeid}" frameborder="0" allowfullscreen></iframe>
</div>
EOD;
		}
		if (CUser::is_authenticated()) {
			$html .= "<hr/>" .
				"<a href='" .
					$this->get_query_string(['id' => $movie->id, 'edit' => 1],
						basename($_SERVER['SCRIPT_FILENAME'])) .
				"' class='btn btn-default' role='button'>Uppdatera</a>" .
				" <a href='" .
					$this->get_query_string(['id' => $movie->id, 'delete' => 1],
						basename($_SERVER['SCRIPT_FILENAME'])) .
				"' class='btn btn-danger' role='button'>Ta bort</a>" .
				"<br/>";
		}
		unset($_SESSION['message']);
		return $html;
	}

	public function get_name()
	{
		if (empty($this->movie))
			return null;
		return $this->movie->title;
	}

	private function get_selected_movies()
	{
		// Prepare the query based on incoming arguments

		$where    = null;
		$groupby  = ' GROUP BY M.id';
		$limit    = null;
		$sort     = " ORDER BY $this->orderby $this->order";
		$params   = array();

		// Select by title
		if ($this->title) {
			$where .= ' AND title LIKE ?';
			$params[] = $this->title;
		} 

		// Select by year
		if ($this->year1) {
			$where .= ' AND year >= ?';
			$params[] = $this->year1;
		} 
		if ($this->year2) {
			$where .= ' AND year <= ?';
			$params[] = $this->year2;
		} 

		// Select by genre
		if ($this->genre) {
			$where .= ' AND G.name = ?';
			$params[] = $this->genre;
		} 

		// Pagination
		if ($this->hits) {
			$limit = " LIMIT $this->hits";
			if ($this->page > 1) {
				$limit .= " OFFSET " . (($this->page - 1) * $this->hits);
			}
		}

		// Complete the sql statement
		$where = $where ? " WHERE 1 {$where}" : null;
		$sql = $this->find_movie_sql . $where . $groupby . $sort . $limit;
		$movies = $this->db->ExecuteSelectQueryAndFetchAll($sql, $params);

		// Get max pages for current query, for navigation
		$sql = "
		  SELECT
			COUNT(id) AS rows
		  FROM 
		  (" . $this->find_movie_sql . $where . $groupby .
		  ") AS " . $this->table_movie;
		$res = $this->db->ExecuteSelectQueryAndFetchAll($sql, $params);
		if ($res === false) {
			$this->rows = 0;
			$this->max = 0;
			return [];
		}
		$this->rows = $res[0]->rows;
		$this->max = ceil($this->rows / $this->hits);

		return $movies;
	}

    public function get_newest_movies($count = 3)
    {
        $sql = $this->find_movie_sql .
            ' GROUP BY M.id' .
            ' ORDER BY updated DESC, created DESC' .
            ' LIMIT ' . $count;
        //var_dump($sql);
        $movies = $this->db->ExecuteSelectQueryAndFetchAll($sql);

        return $movies;
    }

}

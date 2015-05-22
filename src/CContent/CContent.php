<?php

class CRecordFrontend
{
	private $record;

	public function __construct($record)
	{
		$this->record = $record;
	}

    /**
     * Create a link to the content, based on its type.
     * @return string with url to display content.
     * @internal param object $content to link to.
     */
	public function get_view_url()
	{
		$record = $this->record;
		if (empty($record)) return null;
		switch($record->type) {
			case 'page': return "page.php?url={$record->url}"; break;
			case 'post': return "blog.php?slug={$record->slug}"; break;
			default: return null;
		}
	}

	public function get_delete_url()
	{
		$record = $this->record;
		if (empty($record)) return null;
		return "delete.php?id={$record->id}";
	}

	public function get_edit_url()
	{
		$record = $this->record;
		if (empty($record)) return null;
		return "edit.php?id={$record->id}";
	}

	public function get_title()
	{
		return $this->get_field('title');
	}

	public function get_field($field)
	{
		$record = $this->record;
		if (empty($record) || !isset($record->$field))
			return null;
		return htmlentities($record->$field, null, 'UTF-8');
	}

	public function get_body()
	{
		$record = $this->record;
		if (empty($record)) return null;
		if (empty($record->filter))
			return $record->data;
		$filter = new CTextFilter();
		return $filter->doFilter(htmlentities($record->data, null, 'UTF-8'), $record->filter);
	}

	public function get_published()
	{
		$record = $this->record;
		if (empty($record)) return null;
		$value = $record->published;
		return $value;
	}

}

class CContent
{
	private $db = null;
	private $dbconfig;
	private $table_name = null;
	private $category = null;
    private $id;
    private $title;
    private $slug;
    private $url;
    private $data;
    private $type;
    private $filter;
    private $published;
    private $save = null;
    private $output = null;
    private $viewLink = null;
	private $error = null;

	public function __construct($dbconfig, $get_options = null)
	{
		$this->db = new CDatabase($dbconfig);
		$this->dbconfig = $dbconfig;
		//var_dump($dbconfig);
		if (isset($dbconfig['prefix']))
			$this->table_name = $dbconfig['prefix'] . '_' . 'Content';
		else
			$this->table_name = 'Content';
        if (!empty($get_options))
            $this->save_options($get_options, array());
    }

    public function get_category()
    {
        return $this->category;
    }

    private function save_options($get_options, $post_options)
    {
        $this->id     = isset($post_options['id'])    ? strip_tags($post_options['id']) : (isset($get_options['id']) ? strip_tags($get_options['id']) : null);
        $this->title  = isset($post_options['title']) ? $post_options['title'] : null;
        $this->slug   = isset($post_options['slug'])  ? $post_options['slug']  : null;
        $this->url    = isset($post_options['url'])   ? strip_tags($post_options['url']) : null;
        $this->data   = isset($post_options['data'])  ? $post_options['data'] : array();
        $this->type   = isset($post_options['type'])  ? strip_tags($post_options['type']) : array();
        $this->filter = isset($post_options['filter']) ? $post_options['filter'] : array();
        $this->published = isset($post_options['published'])  ? strip_tags($post_options['published']) : array();
        $this->save   = isset($post_options['save'])  ? true : false;
        if (!empty($get_options) && isset($get_options['category']))
            $this->category = $get_options['category'];
        if (!empty($post_options) && isset($post_options['category']))
            $this->category = $post_options['category'];
        (empty($this->id) || is_numeric($this->id)) or die('Check: Id must be numeric if present.');
    }

    private function save_new_data()
    {
        if (!$this->save)
            return;
        $res = $this->update_record($this->category, $this->title, $this->slug, $this->url,
            $this->data, $this->type, $this->filter, $this->published, $this->id);
        if ($res) {
            if (empty($this->id))
                $this->id = $res;
            $this->output = "Informationen sparades som post {$this->id}.";
        } else {
            $this->output = 'Informationen sparades EJ.<br><pre>' . print_r($this->ErrorInfo(), 1) . '</pre>';
        }
    }

    private function load_current_record()
    {
        if (empty($this->id)) {
            $c = $this->empty_record();
        } else {
            $res = $this->get_records(null, [ 'id' => $this->id ]);
            if (isset($res[0])) {
                $c = $res[0];
            } else {
                $this->error = 'Misslyckades: det finns inget innehåll med sådant id.';
                return;
            }
        }

        // Sanitize content before using it.
        $this->title     = $c->get_field('title');
        $this->slug      = $c->get_field('slug');
        $this->url       = $c->get_field('url');
        $this->data      = $c->get_field('data');
        $this->type      = $c->get_field('type');
        $this->filter    = $c->get_field('filter');
        $this->published = $c->get_field('published');
        $this->viewLink  = $c->get_view_url();
    }

    public function get($fieldname)
    {
        return $this->$fieldname;
    }

    private function build_form()
    {
        $html = <<<EOD
<form method=post>
	<fieldset>
	<legend>Uppdatera innehåll</legend>
	<input type='hidden' name='id' value='{$this->id}'/>
EOD;
        $hb = new CHtmlBuilder($this,
            [ 'category', 'title', 'slug', 'url', 'data', 'type', 'filter', 'published' ]);
        $html .= $hb->build_input_tag('Kategori:', 'category');
        $html .= $hb->build_input_tag('Titel:', 'title');
        $html .= $hb->build_input_tag('Slug:', 'slug');
        $html .= $hb->build_input_tag('Url:', 'url');
        $html .= $hb->build_input_tag('Text:', 'data', [ 'tag' => 'textarea' ]);
        $html .= $hb->build_input_tag('Type:', 'type', [ 'placeholder' => 'post | page' ]);
        $html .= $hb->build_input_tag('Filter:', 'filter');
        $html .= $hb->build_input_tag('Publiceringsdatum:', 'published');
    $html .= <<<EOD
	<p class=buttons>
	    <input type='submit' name='save' value='Spara'/>
	    <input type='reset' value='Återställ'/>
    </p>
	<p><a href={$this->viewLink}>Visa post</a></p>
	<p><a href='news.php'>Visa alla</a></p>
EOD;
        $html .= <<<EOD
	<output>{$this->output}</output>
	</fieldset>
</form>
EOD;
        return $html;
    }

    public function get_form_for_entry($get_options, $post_options)
    {
        $this->save_options($get_options, $post_options);
        $this->save_new_data();
        $this->load_current_record();
        return $this->build_form();
    }

	public function ErrorInfo()
	{
		if (!empty($this->error))
			return $this->error;
		return $this->db->ErrorInfo();
	}

	public function reset()
	{
		$mysql_bin = $this->dbconfig['mysql_bin'];
		$sql      = 'contents.sql';
		$host     = $this->dbconfig['host'];
		$login    = $this->dbconfig['username'];
		$password = $this->dbconfig['password'];
		$dbname   = $this->dbconfig['dbname'];
		$cmd = "$mysql_bin -h{$host} -u{$login} -p'{$password}' {$dbname} < $sql 2>&1";
		$res = exec($cmd);
		return "<p>Databasen ${dbname} är återställd från <code>{$sql}</code></p><p>{$res}</p>";
	}

	public function empty_record()
	{
		return new CRecordFrontend(null);
	}

	private function pack_records($res)
	{
		if (empty($res))
			return null;
		$result = array();
		foreach ($res as $record) {
			$result[] = new CRecordFrontend($record);
		}
		return $result;
	}

	public function get_posts($limit = 0)
	{
		$params = [
			'order' => 'published desc, id desc'
		];
		if (!empty($this->category))
			$params['category'] = $this->category;
        if ($limit > 0 )
            $params['limit'] = $limit;

		return $this->get_records('post', $params);
	}

	public function get_records($type, $params)
	{
		$values = array();
		$sql = 'SELECT * FROM ' . $this->table_name . ' WHERE';
		if (!empty($type)) {
			$sql .= " type = ? AND";
			$values[] = $type;
		}
		if (isset($params['category'])) {
			$sql .= " category = ? AND";
			$values[] = $params['category'];
		}
		if (isset($params['url'])) {
			$sql .= " url = ? AND";
			$values[] = $params['url'];
		}
		if (isset($params['slug'])) {
			$sql .= " slug = ? AND";
			$values[] = $params['slug'];
		}
		if (isset($params['id'])) {
			$sql .= " id = ?";
			$values[] = $params['id'];
		} else {
			$sql .= " published <= NOW()";
		}
		if (isset($params['order'])) {
			$sql .= " ORDER BY " . $params['order'];
		}
		if (isset($params['limit'])) {
			$sql .= " LIMIT " . $params['limit'];
		}

		$res = $this->db->ExecuteSelectQueryAndFetchAll($sql, $values);
		return $this->pack_records($res);
	}

	/**
	 * Create a slug of a string, to be used as url.
	 *
	 * @param string $str the string to format as slug.
	 * @returns str the formatted slug.
	 */
	private function slugify($str) {
		$str = mb_strtolower(trim($str));
		$str = str_replace(array('å','ä','ö'), array('a','a','o'), $str);
		$str = preg_replace('/[^a-z0-9-]/', '-', $str);
		$str = trim(preg_replace('/-+/', '-', $str), '-');
		return $str;
	}

	public function update_record($category, $title, $slug, $url, $data, $type, $filter, $published, $id)
	{
		$url = empty($url) ? null : $url;
		$slug = empty($slug) ? null : $slug;
		if (empty($slug))
			$slug = $this->slugify($title);
		switch ($type) {
			case 'page':
				if (empty($url)) {
					$this->error = "Fältet 'url' får inte vara tomt när type=page.";
					return null;
				}
				break;
			case 'post':
				if (empty($slug)) {
					$this->error = "Fältet 'slug' får inte vara tomt när type=post.";
					return null;
				}
				break;
			default:
				$this->error = "Fältet 'type' måste vara 'post' eller 'page'";
				return null;
		}
		$params = array($title, $slug, $data, $filter, $category);
        if (empty($published)) {
            $published_sql = "NOW()";
        } else {
            $published_sql = "?";
            $params[] = $published;
        }
		if (empty($id)) {
			$params[] = $type;
			$params[] = $url;
			$sql = 'INSERT INTO ' . $this->table_name .
                        ' (title, slug, data, filter, category,' .
                        ' published,        updated, type, url)
					VALUES (   ?,    ?,    ?,      ?,        ?, ' .
                        $published_sql . ', NOW(),   ?,    ?)';
		} else {
			$sql = 'UPDATE ' . $this->table_name . ' SET
					title   = ?,
					slug    = ?,
					data    = ?,
					filter  = ?,
					category = ?,
					published = ' . $published_sql . ',
					updated = NOW()
					WHERE id = ?';
			$params[] = $id;
		}
		//var_dump($sql);
		//var_dump($params);
		$res = $this->db->ExecuteQuery($sql, $params);
		if ($res && empty($id))
			$res = $this->db->lastInsertid();
		return $res;
	}

	public function delete_record($id)
	{
		$sql = 'DELETE FROM ' . $this->table_name .  ' WHERE id = ?';
		$params = [ $id ];
		return $this->db->ExecuteQuery($sql, $params);
	}

}


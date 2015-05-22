<?php

class CHtmlBuilder
{

    private $model = null;
    private $whitelist = null;

    public function __construct($model, $whitelist)
    {
        $this->model = $model;
        $this->whitelist = $whitelist;
    }

    private function get_model_value($fieldname, $options)
    {
        if (!empty($this->whitelist) && !in_array($fieldname, $this->whitelist))
            return null;
        $value = null;
        if (isset($options['value']))
            $value = $options['value'];
        elseif (isset($this->model->$fieldname))
            $value = $this->model->$fieldname;
        else
            $value = $this->model->get($fieldname);
        if (empty($value) && isset($_SESSION['options'])) {
            $saved_options = $_SESSION['options'];
            if (isset($saved_options[$fieldname]))
                $value = $saved_options[$fieldname];
        }
        return $value;
    }

    public function build_input_tag($label, $fieldname, $options = array())
    {
        $tag = 'input';
        if (isset($options['tag']))
            $tag = $options['tag'];
        $type = 'text';
        //var_dump($options);
        if (isset($options['type']))
            $type = $options['type'];
        $html = "<div class='form-group'>
                    <label>$label</label><br/>
                    <$tag type='$type' class='form-control' name='$fieldname'";
        if (isset($options['placeholder']))
            $html .= ' placeholder="' . $options['placeholder'] . '"';
        $value = $this->get_model_value($fieldname, $options);
        $contents = isset($options['contents']) ? $options['contents'] : null;
        if ($tag == 'textarea') {
            $contents = $value;
            unset($value);
        }
        if (!empty($value))
            $html .= " value='{$value}'";
        if (isset($options['min']))
            $html .= " min='{$options['min']}'";
        if (isset($options['max']))
            $html .= " max='{$options['max']}'";
        if (isset($options['rows']))
            $html .= " rows='{$options['rows']}'";
        $html .= '>';
        if (isset($contents))
            $html .= trim($contents);
        $html .= "</$tag></div>";
        return $html;
    }

}

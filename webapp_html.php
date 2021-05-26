<?php
class webapp_html_xml extends webapp_xml
{
	function fieldset(string $legend = NULL):static
	{
		//An optional <legend> element, followed by flow content.
		$node = &$this[0]->fieldset[];
		if ($legend !== NULL)
		{
			$node->legend = $legend;
		}
		return $node;
	}
	function details(string $summary = NULL):static
	{
		//One <summary> element followed by flow content.
		$node = &$this[0]->details[];
		if ($summary !== NULL)
		{
			$node->summary = $summary;
		}
		return $node;
	}
	function labelinput(string $name, string $type, string $value, string $comment):static
	{
		$node = &$this[0]->label[];
		$node->append('input', ['type' => $type, 'name' => $name, 'value' => $value]);
		$node->text($comment);
		return $node;
	}
	function options(iterable $values, string ...$default):static
	{
		foreach ($values as $value => $content)
		{
			if (is_array($content))
			{
				$this[0]->append('optgroup', ['label' => $value])->options($content, ...$default);
				continue;
			}
			$this[0]->append('option', in_array($value, $default, TRUE) ? [$content, 'value' => $value, 'selected' => NULL] : [$content, 'value' => $value]);
		}
		return $this[0];
	}
	// function optgroup(array $values):static
	// {
	// 	foreach ($values as $label => $values)
	// 	{
	// 		$node = &$this[0]->optgroup[];
	// 		$node['label'] = $label;
	// 		$node->options($values);
	// 	}
	// 	return $this[0];
	// }
	function select(iterable $values, string ...$default):static
	{
		return $this->append('select')->options($values, ...$default);
	}
	function progress(float $value = 0, float $max = 1):static
	{
		$node = &$this[0]->progress[];
		$node['value'] = $value;
		$node['max'] = $max;
		return $node;
	}
	function ultree(iterable $list):static
	{
		$node = &$this[0]->ul[];
		foreach ($list as $item)
		{
			if (is_iterable($item))
			{
				$node->ultree($item);
				continue;
			}
			$node->append('li', $item);
		}
		return $node;
	}
	function atree(iterable $anchors):static
	{
		$node = &$this[0]->ul[];
		foreach ($anchors as $attributes)
		{
			if (isset($attributes[1]))
			{
				$list = $attributes[1];
				unset($attributes[1]);
				$li = &$node->li[];
				if (count($attributes) > 2)
				{
					$li[0] = $attributes[0];
				}
				else
				{
					$li->append('a', $attributes);
				}
				$li->atree($list);
				continue;
			}
			$node->append('li')->append('a', $attributes);
		}
		return $node;
	}
	function foldtree(string $summary, iterable $details):static
	{
		$node = $this[0]->details($summary);
		$ul = &$node->ul;
		foreach ($details as $detail)
		{
			$li = &$ul->li[];
			if (isset($detail[1]))
			{
				$li->foldtree($detail[0], $detail[1]);
				continue;
			}
			$li->append('a', $detail);
		}
		return $node;
	}
	function navbar(iterable $links):static
	{
		$node = &$this[0]->nav[];
		$ul = &$node->ul;
		foreach ($links as $link)
		{
			$li = &$ul->li[];
			if (isset($link[1]))
			{
				$li->foldtree(...$link);
				continue;
			}
			$li->append('a', $link);
		}
		$node['class'] = 'webapp';
		return $node;
	}
	function form(string $action):webapp_html_form
	{
		return new webapp_html_form($this->webapp(), $this[0], $action);
		//return $this->webapp()->formdata($this[0], $action);
	}
	function table(iterable $data, closure $output = NULL, mixed ...$params):webapp_html_table
	{
		return new webapp_html_table($this->webapp(), $this[0], $data, $output, ...$params);
	}
}
class webapp_html_form
{
	public webapp_html_xml $xml, $fieldset;
	private $files = [], $fields = [], $index = 0;
	function __construct(public webapp $webapp, webapp_html_xml $node = NULL, string $action = NULL)
	{
		$this->xml = $node === NULL ? new webapp_html_xml('<form/>') : $node->append('form', [
			'autocomplete' => 'off',
			'enctype' => 'application/x-www-form-urlencoded',
			'method' => 'post',
			'action' => $action,
			'class' => 'webapp'
		]);
		$this->fieldset();
	}
	function fieldset(string $name = NULL):webapp_html_xml
	{
		return $this->fieldset = $this->xml->fieldset($name);
	}
	function progress():webapp_html_xml
	{
		return $this->fieldset->progress();
	}
	function button(string $name, string $type = 'button', array $attributes = []):webapp_html_xml
	{
		return $this->fieldset->append('button', [$name, 'type' => $type] + $attributes);
	}
	function captcha(string $name):static
	{
		$this->fieldset($name);
		$this->field('captcha_encrypt')['value'] = $random = $this->webapp->captcha_random();
		$this->field('captcha_decrypt', 'text', ['placeholder' => 'Type following captcha', 'onfocus' => 'this.select()', 'required' => NULL]);
		$this->fieldset()->attr([
			'style' => "height:{$this->webapp['captcha_size'][1]}px;background:url(?captcha/{$random}) no-repeat center",
			'onckick' => ''
		]);
		return $this;
	}
	function field(string $name, string $type = 'hidden', array $attributes = []):webapp_html_xml
	{
		$alias = $rename = preg_match('/^\w+/', $name, $retval) ? $retval[0] : $this->index++;
		switch ($typename = strtolower($type))
		{
			case 'radio':
			case 'checkbox':
				$node = &$this->fieldset->div[];
				$node['data-type'] = $typename;
				if ($typename === 'checkbox')
				{
					$alias .= '[]';
				}
				foreach ($attributes as $value => $comment)
				{
					$node->labelinput($alias, $typename, $value, $comment);
				}
				return $this->fields[$rename] = $node;
			// case 'set':
			// case 'enum':
			// case 'setinput':
			// case 'enuminput':
			case 'textarea':
				return $this->fields[$rename] = $this->fieldset->append('textarea', ['name' => $alias] + $attributes);
			case 'file':
				$this->xml['enctype'] = 'multipart/form-data';
			case 'select':
				if (array_key_exists('multiple', $attributes))
				{
					$alias .= '[]';
					$attributes['multiple'] = NULL;
				}
				if ($typename === 'select')
				{
					$node = $this->fieldset->append('select', ['name' => $alias]);
					if (array_key_exists('value', $attributes) && is_array($attributes['value']))
					{
						$node->options($attributes['value']);
						unset($attributes['value']);
					}
					if (array_key_exists('optgroup', $attributes) && is_array($attributes['optgroup']))
					{
						$node->optgroup($attributes['optgroup']);
						unset($attributes['optgroup']);
					}
					return $this->fields[$rename] = $node->attr($attributes);
				}
			default:
				return $this->{$typename === 'file' ? 'files' : 'fields'}[$rename] = $this->fieldset->append('input', ['type' => $typename, 'name' => $alias] + $attributes);
		}
	}
	function value(array $default):static
	{
		foreach ($this->fields as $name => $node)
		{
			if (isset($default[$name]))
			{
				switch ($node->getName())
				{
					case 'div':
					case 'select':
						if ($node->getName() === 'select')
						{
							$nodename = 'option';
							$attrname = 'selected';
						}
						else
						{
							$nodename = 'label/input';
							$attrname = 'checked';
						}
						$more = [];
						foreach (is_array($default[$name]) ? $default[$name] : [$default[$name]] as $value)
						{
							$more[] = "{$nodename}[@value=\"{$value}\"]";
						}
						if ($more)
						{
							foreach ($node->xpath(join('|', $more)) as $children)
							{
								$children[$attrname] = NULL;
							}
						}
						continue 2;
					case 'textarea':
						$node->text($default[$name]);
						continue 2;
					default:
						$node['value'] = $default[$name];
				}
			}
		}
		return $this;
	}
	function fetch(bool $captcha = FALSE):?array
	{
		do
		{
			$input = $this->webapp->request_content($this->xml['enctype']);
			foreach ($this->files as $name => $node)
			{
				$uploadedfile = $this->webapp->request_uploadedfile($name);
				if ((isset($node['required']) > $uploadedfile->count())
					|| (isset($node['accept']) && $uploadedfile->detect($node['accept']) === FALSE)
					|| (isset($node['data-maxfile']) && $uploadedfile->count() > intval($node['data-maxfile']))
					|| (isset($node['data-maxsize']) && $uploadedfile->size() > intval($node['data-maxsize']))) {
					break 2;
				}
				continue;
			}
			$values = [];
			foreach ($this->fields as $name => $node)
			{
				$tagname = $node->getName();
				if (isset($input[$name]) === FALSE)
				{
					switch ($tagname)
					{
						case 'div':
							foreach ($node->xpath('label/input[@name]') as $input)
							{
								if (isset($input['required']))
								{
									break 4;
								}
							}
							break;
						default:
							if (isset($node['required']))
							{
								break 3;
							}
							break;
					}
					$values[$name] = NULL;
					continue;
				}
				//数据输入检查
				$value = $input[$name];
				switch ($tagname)
				{
					case 'div':
					case 'select':
						if ($tagname === 'div')
						{
							$nodename = 'label/input';
							$multiple = (string)$node['data-type'] === 'checkbox';
						}
						else
						{
							$nodename = 'option';
							$multiple = isset($node['multiple']);
						}
						$sourcedata = [];
						foreach ($node->xpath("{$nodename}[@value]") as $children)
						{
							$sourcedata[] = (string)$children['value'];
						}
						if ($multiple)
						{
							if (is_array($value) === FALSE || array_diff($value, $sourcedata))
							{
								break 3;
							}
						}
						else
						{
							if (in_array($value, $sourcedata, TRUE) === FALSE)
							{
								break 3;
							}
						}
						break;
					default:
						if ((is_string($value) && $this->checkinput($node, $value)) === FALSE)
						{
							break 3;
						}
				}
				$values[$name] = $value;
			}
			if ($captcha)
			{
				if (array_key_exists('captcha_encrypt', $values)
					&& array_key_exists('captcha_decrypt', $values)
					&& $this->webapp->captcha_verify($values['captcha_encrypt'], $values['captcha_decrypt'])) {
					unset($values['captcha_encrypt'], $values['captcha_decrypt']);
				}
				else
				{
					$name = 'captcha_decrypt';
					break;
				}
			}
			return $values;
		} while (0);
		($this->webapp)($this)->errors[] = "Form input[{$name}] invalid";
		return NULL;
	}
	private function checkinput(webapp_html_xml $node, string $value):bool
	{
		switch (strtolower($node['type']))
		{
			case 'color':
				return preg_match('/^#[0-f]{6}$/i', $value);
			case 'date':
				return preg_match('/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[1-2]\d|3[0-2])$/', $value);
			case 'datetime':
			case 'datetime-local':
				return preg_match('/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[1-2]\d|3[0-2])T(?:[01]\d|2[0-3]):[0-5]\d$/', $value);
			case 'email':
				return preg_match('/^[\w\.]+@[0-9a-z]+(\.[0-9a-z])?/i', $value);
			case 'month':
				return preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $value);
			case 'number':
			case 'range':
				if (is_numeric($value))
				{
					$f = floatval($value);
					if ($f
						&& isset($node['step'])
						&& preg_match('/^\d+(?:\.(\d+))?$/', (string)$node['step'], $matches)
						&& floatval($matches[0])) {
						if (isset($matches[1]) && intval($matches[1]))
						{
							$v = intval(1 . str_repeat(0, strlen($matches[1])));
							if (strpos($i = $f * $v, '.') || $i % ($matches[0] * $v))
							{
								return FALSE;
							}
						}
						else
						{
							if (strpos($f, '.') || $f % intval($matches[0]))
							{
								return FALSE;
							}
						}
					}
					return (isset($node['max']) === FALSE || floatval($node['max']) >= $f)
						&& (isset($node['min']) === FALSE || floatval($node['min']) <= $f);
				}
				return FALSE;
			case 'time':
				return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value);
			case 'url':
				return preg_match('/^[a-z][a-z0-9]*\:\/\//i', $value);
			case 'week':
				return preg_match('/^\d{4}-W(?:0[1-9]|[1-4]\d|5[0-3])$/', $value);
			default:
				return (isset($node['maxlength']) === FALSE || intval($node['maxlength']) >= strlen($value))
					&& (isset($node['minlength']) === FALSE || intval($node['minlength']) <= strlen($value))
					&& (isset($node['pattern']) === FALSE || preg_match("/^{$node['pattern']}$/", $value));
		}
	}
}
class webapp_html_table
{
	public $xml, $tbody, $paging;
	function __construct(public webapp $webapp, webapp_html_xml $node, iterable $data, closure $output = NULL, ...$params)
	{
		$this->xml = &$node->table[];
		$this->tbody = &$this->xml->tbody;
		if ($output)
		{
			foreach ($data as $values)
			{
				$output->call($this, $values, ...$params);
			}
		}
		else
		{
			foreach ($data as $values)
			{
				$row = &$this->tbody->tr[];
				if (is_iterable($values))
				{
					foreach ($values as $value)
					{
						$row->td[] = $value;
					}
					continue;
				}
				$row->td[] = $values;
			}
		}
		$this->paging = $data->paging ?? NULL;
	}
	function __get(string $name)
	{
		switch ($name)
		{
			case 'caption': return $this->caption = $this->xml->insert('caption', 'first');
			case 'colgroup': return $this->colgroup = $this->caption->insert('colgroup', 'after');
			case 'thead': return $this->thead = $this->tbody->insert('thead', 'before');
			case 'fieldname': return $this->fieldname = &$this->thead->tr[];
			case 'title': return (property_exists($this, 'fieldname') ? $this->fieldname->insert('tr', 'before') : $this->thead->append('tr'))->append('td', ['colspan' => $this->column]);
			case 'column': return isset($this->tbody->tr->td) ? count($this->tbody->tr->td) : 0;
			case 'tfoot': return $this->tfoot = $this->tbody->insert('tfoot', 'after');
		}
	}
	function paging(string $url, int $max = 9):static
	{
		if ($this->paging && $this->paging['max'] > 1)
		{
			$node = $this->tfoot->append('tr')->append('td', ['colspan' => $this->column]);
			if ($this->paging['max'] > $max)
			{
				$halved = intval($max * 0.5);
				$offset = min($this->paging['max'], max($this->paging['index'], $halved + 1) + $halved) - 1;
				$ranges = range(max(1, $offset - $halved * 2 + 1), $offset);
				$ranges[0] = 1;
				$ranges[] = $this->paging['max'];
				$node->append('a', ['Prev', 'href' => $url . ($this->paging['index'] - 1)]);
				foreach ($ranges as $index)
				{
					$node->append('a', [$index, 'href' => "{$url}{$index}"]);
				}
				$node->append('a', ['Next', 'href' => $url . ($this->paging['index'] + 1)]);
				$node->append('input', ['type' => 'number', 'min' => 1, 'max' => $this->paging['max'], 'value' => $this->paging['index'], 'onkeypress' => 'event.keyCode===13&&location.assign(this.nextElementSibling.href+this.value)']);
				$node->append('a', ['Goto', 'href' => $url, 'onclick' => 'return !!location.assign(this.href+this.previousElementSibling.value)']);
			}
			else
			{
				for ($i = 1;$i <= $this->paging['max']; ++$i)
				{
					$node->append('a', [$i, 'href' => "{$url}{$i}"]);
				}
			}
		}
		return $this;
	}
	function fieldname(...$names):webapp_html_xml
	{
		$node = $this->fieldname;
		foreach ($names as $name)
		{
			$node->td[] = $name;
		}
		return $node;
	}
}
class webapp_html extends webapp_dom
{
	use webapp_echo;
	const appxml = 'webapp_html_xml';
	function __construct(webapp $webapp, string $data = NULL)
	{
		$this($webapp)->response_content_type("text/html; charset={$webapp['app_charset']}");
		if ($data)
		{
			str_starts_with($data, '<') ? $this->loadHTML($data) : $this->loadHTMLFile($data);
		}
		else
		{
			$this->loadHTML("<!doctype html><html><head><meta charset='{$webapp['app_charset']}'><meta name='viewport' content='width=device-width, initial-scale=1.0'/></head><body class='webapp'/></html>");
			$this->xml->head->append('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '?scss/webapp']);
			// $this->xml->head->append('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => 'webflock/core/files/ps/font-awesome.css']);
			//$this->xml->head->append('script', ['type' => 'javascript/module', 'src' => 'webapp/files/js/webapp.js']);
			$this->article = $this->xml->body->append('article');
			$this->header = $this->article->append('header');
			$this->section = $this->article->append('section');
			$this->footer = $this->article->append('footer', $this->webapp['copy_webapp']);
		}
	}
	function __toString():string
	{
		return html_entity_decode($this->saveHTML(), ENT_HTML5, $this->webapp['app_charset']);
	}
	function xpath(string $expression):array
	{
		return iterator_to_array((new DOMXPath($this))->evaluate($expression));
	}
	function title(string $title):void
	{
		$this->xml->head->title = $title;
	}
	function aside(bool $after = FALSE):webapp_html_xml
	{
		$this->aside = $this->article->section->append('aside');
		$this->section = $this->aside->insert('section', $after ? 'before' : 'after');
		return $this->aside;
	}



	static function form_sign_in(webapp $webapp, webapp_html_xml $node = NULL, string $authurl = NULL):webapp_html_form
	{
		$form = $webapp->formdata($node, $authurl);
		$form->fieldset('Username');
		$form->field('username', 'text', ['placeholder' => 'Type username', 'required' => NULL, 'autofocus' => NULL]);
		$form->fieldset('Password');
		$form->field('password', 'password', ['placeholder' => 'Type password', 'required' => NULL]);
		if ($webapp['captcha_echo'])
		{
			$form->captcha('Captcha');
		}
		$form->fieldset();
		$form->button('Sign In', 'submit');
		return $form;
	}
}
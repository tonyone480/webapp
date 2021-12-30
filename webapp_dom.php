<?php
declare(strict_types=1);
class webapp_xml extends SimpleXMLElement
{
	function webapp():?webapp
	{
		return $this->dom()->ownerDocument->webapp ?? NULL;
	}
	function dom():DOMNode
	{
		return dom_import_simplexml($this[0]);
	}
	function clone(bool $deep = TRUE):DOMNode
	{
		return $this[0]->dom()->cloneNode($deep);
	}
	function cdata(string $value):DOMNode
	{
		return $this[0]->dom()->appendChild(new DOMCdataSection($value));
	}
	function comment(string $value):DOMNode
	{
		return $this[0]->dom()->appendChild(new DOMComment($value));
	}
	function entity(string $name):DOMNode
	{
		return $this[0]->dom()->appendChild(new DOMEntityReference($name));
	}
	function pi(string ...$values):DOMNode
	{
		return $this[0]->dom()->appendChild(new DOMProcessingInstruction(...$values));
	}
	function text(string ...$values):DOMNode
	{
		return $this[0]->dom()->appendChild(new DOMText(...$values));
	}
	function xml(string $data):DOMNode
	{
		$dom = $this[0]->dom();
		$xml = $dom->ownerDocument->createDocumentFragment();
		if ($xml->appendXML($data))
		{
			$dom->appendChild($xml);
		}
		return $xml;
	}
	function iter(iterable $contents, Closure $iterator = NULL, ...$params):static
	{
		// $doc = $this[0]->dom()->ownerDocument;
		// $iterator ? $doc->iter = [$iterator, $params] : [$iterator, $params] = $doc->iter;
		if ($iterator === NULL)
		{
			//神奇骚操作，未来某个PHP版本不会改了吧？
			$backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3)[2];
			$backtrace['object'] instanceof Closure
				? [$iterator, $params] = [$backtrace['object'], array_slice($backtrace['args'], 2)]
				: $params = [$iterator = function(array $element, Closure $iterator):void
				{
					$node = $this->append(array_shift($element), $element);
					if (isset($element[0]) && is_iterable($element[0]))
					{
						$node->iter($element[0], $iterator, $iterator);
					}
				}];
		}
		foreach ($contents as $value)
		{
			$iterator->call($this[0], $value, ...$params);
		}
		return $this[0];
	}
	//获取属性
	function getattr(string $name = NULL):NULL|string|array
	{
		$attributes = ((array)$this[0]->attributes())['@attributes'] ?? [];
		return is_string($name) ? $attributes[$name] ?? NULL : $attributes;
	}
	//设置属性
	function setattr(string|array $name, $value = NULL):static
	{
		$node = $this[0];
		foreach (is_string($name) ? [$name => $value] : $name as $name => $value)
		{
			if ((is_string($name) || $name === 0) && is_scalar($value))
			{
				$node[$name] = $value;
				continue;
			}
			if (is_string($name) && $value === NULL)
			{
				$dom ??= $node->dom();
				$dom->appendChild($dom->ownerDocument->createAttribute($name));
			}
		}
		return $node;
	}
	function append(string $name, NULL|string|array $contents = NULL):static
	{
		return is_array($contents)
			? ($node = &$this[0]->{$name}[])->setattr($contents)
			: $this[0]->addChild($name, $contents);
	}
	function insert(DOMNode|string $element, string $position = NULL):DOMNode|static
	{
		$dom = $this[0]->dom();
		$node = is_string($element) ? $dom->ownerDocument->createElement($element) : $element;
		match ($position)
		{
			//插入到当前节点之后
			'after' => $dom->parentNode->insertBefore($node, $dom->nextSibling),
			//插入到当前节点之前
			'before' => $dom->parentNode->insertBefore($node, $dom),
			//插入到当前节点下开头
			'first' => $dom->insertBefore($node, $dom->firstChild),
			//插入到当前节点下末尾
			default => $dom->appendChild($node)
		};
		return $node->nodeType === XML_ELEMENT_NODE ? static::from($node) : $node;
		
	}
	function parent():static
	{
		return $this[0]->xpath('..')[0] ?? $this[0];
	}
	function remove():static
	{
		$child = $this[0]->dom();
		$parent = $child->parentNode;
		$parent->removeChild($child);
		return static::from($parent);
		unset($this[0]);
	}
	function clear():string
	{
		$text = (string)$this[0];
		$this[0] = NULL;
		return $text;
	}
	//以数组递归方式导入当前节点下所有内容
	function import(iterable $values):static
	{
		foreach ($values as $key => $value)
		{
			$node = &$this[0]->{$key}[];
			if (is_iterable($value))
			{
				$node->import($value);
			}
			else
			{
				$node[0] = $value;
			}
		}
		return $this;
	}
	//以数组递归方式导出当前节点下所有内容
	function export():array
	{
		$values = [];
		if (strlen($content = trim($this[0])))
		{
			$values[] = preg_replace('/\s+/', ' ', $content);
		}
		$key = 0;
		foreach ($this[0] as $name => $node)
		{
			if (isset($values[$name]))
			{
				if ($key === 0)
				{
					$values[$name] = [$values[$name]];
				}
				$value = &$values[$name];
				$name = ++$key;
			}
			else
			{
				$value = &$values;
			}
			if ($node->count())
			{
				$value[$name] = $node->export();
			}
			else
			{
				$value[$name] = (string)$node;
			}
		}
		return $values;
	}
	static function from(DOMNode $node):?static
	{
		return simplexml_import_dom($node, static::class);
	}
	static function charsafe(string $content):string
	{
		return preg_replace('/[\x00-\x08\x0b-\x0c\x0e-\x1f]/', '', $content);
	}
	// function query(string $selector):array
	// {
	// 	$query = ['descendant::*'];
	// 	while (preg_match('/^\s*(\w+|\*)?([\.\#]([\w\x{00c0}-\x{ffff}\-]+))?/u', $selector, $matches) && isset($matches[1]))
	// 	{
	// 		$selector = substr($selector, strlen($matches[0]));
	// 		if ($matches[1] && $matches[1] !== '*')
	// 		{
	// 			$query[] = '[translate(name(.),"abcdefghijklmnopqrstuvwxyz","ABCDEFGHIJKLMNOPQRSTUVWXYZ")="' . strtoupper($matches[1]) . '"]';
	// 		}
	// 		if (isset($matches[2]))
	// 		{
	// 			$query[] = $matches[2][0] === '#' ? '[@id="' . $matches[3] . '"]' : '[contains(concat(" ",normalize-space(@class)," "),"' . $matches[3] . '")]';
	// 		}
	// 		while (preg_match('/^\[\s*(\w+)\s*(?:(\!\=|\$\=|\*\=|\=|\^\=|\~\=|\|\=)\s*(\'|\")?([\w\x{00c0}-\x{ffff}\-]+)\3?\s*)?\]/u', $selector, $matches))
	// 		{
	// 			$selector = substr($selector, strlen($matches[0]));
	// 			if (isset($matches[2]))
	// 			{
	// 				switch ($matches[2])
	// 				{
	// 					case '!=':
	// 						$query[] = '[@' . $matches[1] . '!="' . $matches[4] . '"]';
	// 						break;
	// 					case '$=':
	// 						$query[] = '[@' . $matches[1] . '$="' . $matches[4] . '"]';
	// 						break;
	// 					case '*=':
	// 						$query[] = '[contains(@' . $matches[1] . ',"' . $matches[4] . '")]';
	// 						break;
	// 					case '=':
	// 						$query[] = '[@' . $matches[1] . '="' . $matches[4] . '"]';
	// 						break;
	// 					case '^=':
	// 						$query[] = '[starts-with(@' . $matches[1] . '," ' . $matches[4] . ' ")]';
	// 						break;
	// 					case '~=':
	// 						$query[] = '[contains(concat(" ",normalize-space(@' . $matches[1] . ')," "),"' . $matches[4] . '")]';
	// 						break;
	// 					case '|=':
	// 						$query[] = '[@' . $matches[1] . '="' . $matches[4] . '" or starts-with(@' . $matches[1] . ',"' . $matches[4] . '")]';
	// 				};
	// 				continue;
	// 			}
	// 			$query[] = '[@' . $matches[1] . ']';
	// 		}
	// 		if (preg_match('/^\s*(\+|\>|\~|\,)?/', $selector, $matches))
	// 		{
	// 			$selector = substr($selector, strlen($matches[0]));
	// 			if (isset($matches[1]))
	// 			{
	// 				switch ($matches[1])
	// 				{
	// 					case '+':
	// 						$query[] = '/following-sibling::*[1]';
	// 						break;
	// 					case '>':
	// 						$query[] = '/*';
	// 						break;
	// 					case '~':
	// 						$query[] = '/following-sibling::*';
	// 						break;
	// 					default:
	// 						$query[] = '|descendant::*';
	// 				};
	// 				continue;
	// 			}
	// 			if ($selector)
	// 			{
	// 				$query[] = '/descendant::*';
	// 				continue;
	// 			}
	// 		}
	// 		break;
	// 	}
	// 	return $this[0]->xpath(join($query));
	// }
}
class webapp_svg extends webapp_xml
{
}
class webapp_html extends webapp_xml
{
	function template(iterable $struct, array|string $attr = []):static
	{
		return $this[0]->append('template')->iter($struct)->setattr(is_array($attr) ? $attr : ['id' => $attr]);
	}
	function progress(float $value = 0, float $max = 1):static
	{
		$node = &$this[0]->progress[];
		$node['value'] = $value;
		$node['max'] = $max;
		return $node;
	}
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
	// function meter(float $value, float $min = 0, float $max = 1, float $low = NULL, float $high = NULL, float $optimum = NULL):static
	// {
	// 	return $this->append('meter', [
	// 		'value' => $value, 'min' => $min, 'max' => $max,
	// 		'low' => $low ?? $min + ($max - $min) * 0.4,
	// 		'high' => $high ?? $min + ($max - $min) * 0.9,
	// 		'optimum' => $optimum ?? $min + ($max - $min) * 0.7
	// 	]);
	// }
	// function figure(string $src):static
	// {
	// 	$node = &$this[0]->figure[];
	// 	return $node;
	// }
	function labelinput(string $name, string $type, string $value, string $comment):static
	{
		$node = &$this[0]->label[];
		$node->append('input', ['type' => $type, 'name' => $name, 'value' => $value]);
		$node->text($comment);
		return $node;
	}
	function options(iterable $contents, string ...$default):static
	{
		foreach ($contents as $value => $content)
		{
			if (is_iterable($content))
			{
				$this[0]->append('optgroup', ['label' => $value])->options($content, ...$default);
				continue;
			}
			$this[0]->append('option', in_array($value, $default, TRUE) ? [$content, 'value' => $value, 'selected' => NULL] : [$content, 'value' => $value]);
		}
		return $this[0];
	}
	function select(iterable $values, string ...$default):static
	{
		return $this[0]->append('select')->options($values, ...$default);
	}
	function section(string $title, int $level = 1):static
	{
		$node = &$this[0]->section[];
		$node->{'h' . max(1, min(6, $level))} = $title;
		return $node;
	}


	
	function appendnode(array $element):static
	{
		return $this[0]->append(array_shift($element), $element);
	}
	function appenditer(iterable $contents):static
	{
		return $this[0]->iter($contents, function(array $content)
		{
			$node = $this->append(array_shift($content), $content);
			if (array_key_exists('iter', $content) && is_iterable($content['iter']))
			{
				$node->iter($content['iter']);
			}
		});
	}
	function atree(iterable $link, bool $fold = FALSE)
	{
		return $this[0]->append('ul')->iter($link, function(array $link, bool $fold):void
		{
			$node = &$this->li[];
			if (is_iterable($link[1]))
			{
				if ($fold)
				{
					$node = $node->details($link[0]);
				}
				else
				{
					$node->append('span', $link[0]);
				}
				$node->append('ul')->iter($link[1]);
			}
			else
			{
				$link['href'] ??= $link[1];
				$node->append('a', $link);
			}
		}, $fold);
	}
	
	function appenditer1(iterable $iterator, Closure $generator = NULL):static
	{
		return $this[0]->iter($iterator, $generator ?? fn(array $item):array => [$this->append($item[0], array_slice($item, 1)), $item['iter'] ?? NULL]);
		// $generator ??= fn(array $item):array => [$this->append($item[0], array_slice($item, 1)), $item['iter'] ?? NULL];
		// foreach ($iterator as $item)
		// {
		// 	if (is_array($context = $generator->call($this[0], $item)))
		// 	{
		// 		[$node, $iter] = [...$context, NULL, NULL];
		// 		if ($node instanceof static && is_iterable($iter))
		// 		{
		// 			$node->appenditer($iter, $generator);
		// 		}
		// 	}
		// }
		// return $this[0];
		// foreach ($iter as $data)
		// {
		// 	$item = $map ? $map->call($this[0], $data) : $data;
			
		// 	if (is_array($item))
		// 	{

		// 	}

		// 	var_dump( get_debug_type($item) );

		// 	// if (is_array($item = $map ? $map($data) : $data))
		// 	// {
		// 	// 	$node = match (get_debug_type($before))
		// 	// 	{
		// 	// 		'Closure' => $before->call($this[0], $item),
		// 	// 		'string' => $this[0]->append($before),
		// 	// 		'array' => $this[0]->append($before[0], array_slice($before, 1)),
		// 	// 		default => $this[0]
		// 	// 	};
		// 	// 	$iterable = NULL;
		// 	// 	if (array_key_exists('iter', $item))
		// 	// 	{
		// 	// 		$iterable = $item['iter'];
		// 	// 		unset($item['iter']);
		// 	// 	}
		// 	// 	$node = $node->append($item[0], array_slice($item, 1));
		// 	// 	if (is_iterable($iterable))
		// 	// 	{
		// 	// 		$node = match (get_debug_type($after))
		// 	// 		{
		// 	// 			'Closure' => $after->call($node),
		// 	// 			'string' => $node->append($after),
		// 	// 			'array' => $node->append($after[0], array_slice($after, 1)),
		// 	// 			default => $node
		// 	// 		};
		// 	// 		$node->appenditer($iterable, $map, $before, $after);
		// 	// 	}
		// 	// }
		// }
		// return $this[0];
	}
	function appenditerable(iterable $content, Closure|string|array ...$extends):static
	{
		foreach ($content as $element)
		{
			if (is_array($element))
			{
				$iterable = NULL;
				if (array_key_exists('iterable', $element))
				{
					$iterable = $element['iterable'];
					unset($element['iterable']);
				}
				$node = $this[0]->append($element[0], array_slice($element, 1));
				if (is_iterable($iterable))
				{
					foreach ($extends as $extend)
					{
						$node = match (get_debug_type($extend))
						{
							'Closure' => $extend->call($node),
							'string' => $node->append($extend),
							'array' => $node->append($extend[0], array_slice($extend, 1)),
							default => $node
						};
					}
					$node->appenditerable($iterable, ...$extends);
				}
			}
		}
		return $this[0];
	}

	function appendtree(iterable $list, string $root = 'ul', string $child = 'li'):static
	{
		$node = &$this[0]->{$root}[];
		foreach ($list as $item)
		{
			$element = &$node->{$child}[];
			if (is_scalar($item))
			{
				$element->text($item);
				continue;
			}
			if (is_array($item))
			{
				//if(is_iterable($item[1]))
			}

		}
		// $node = &$this[0]->ul[];
		// foreach ($list as $item)
		// {
		// 	if (is_iterable($item))
		// 	{
		// 		$node->ultree($item);
		// 		continue;
		// 	}
		// 	$node->append('li', $item);
		// }
		return $node;
	}
	// function atree(iterable $anchors):static
	// {
	// 	$node = &$this[0]->ul[];
	// 	foreach ($anchors as $attributes)
	// 	{
	// 		if (isset($attributes[1]))
	// 		{
	// 			$list = $attributes[1];
	// 			unset($attributes[1]);
	// 			$li = &$node->li[];
	// 			if (count($attributes) > 2)
	// 			{
	// 				$li[0] = $attributes[0];
	// 			}
	// 			else
	// 			{
	// 				$li->append('a', $attributes);
	// 			}
	// 			$li->atree($list);
	// 			continue;
	// 		}
	// 		$node->append('li')->append('a', $attributes);
	// 	}
	// 	return $node;
	// }
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
	function form(?string $action = NULL):webapp_form
	{
		return new webapp_form($this[0], $action);
	}
	function table(iterable $contents = [], Closure $output = NULL, mixed ...$params):webapp_table
	{
		return new webapp_table($this[0], $contents, $output, ...$params);
	}
}
class webapp_document extends DOMDocument implements Stringable
{
	const xmltype = 'webapp_xml';
	public readonly webapp $webapp;
	public webapp_xml $xml;
	function __toString():string
	{
		return $this->saveXML();
	}
	function __invoke(bool $loaded):bool
	{
		return $loaded && $this->xml = static::xmltype::from($this);
	}
	function load(string $source, int $options = 0):bool
	{
		return $this(parent::load($source, $options));
	}
	function loadXML(string $source, int $options = 0):bool
	{
		return $this(parent::loadXML($source, $options));
	}
	function loadXMLFile(string $source, int $options = 0):bool
	{
		return $this->load($source, $options);
	}
	function loadHTML(string $source, int $options = 0):bool
	{
		return $this(parent::loadHTML($source, $options | LIBXML_NOWARNING | LIBXML_NOERROR));
	}
	function loadHTMLFile(string $source, int $options = 0):bool
	{
		return $this(parent::loadHTMLFile($source, $options | LIBXML_NOWARNING | LIBXML_NOERROR));
	}
	// function evaluate(string $expression, DOMNode $contextnode = NULL)
	// {
	// 	return (new DOMXPath($this))->evaluate($expression, $contextnode);
	// }
	// function querySelectorAll(string $selectors):array
	// {
	// 	return $this->xml->query($selectors);
	// }
	// function querySelector(string $selectors):?DOMElement
	// {
	// 	return $this->querySelectorAll($selectors)[0] ?? NULL;
	// }
	// function fragment(string $data):DOMDocumentFragment
	// {
	// 	$fragment = $this->createDocumentFragment();
	// 	$fragment->appendXML($data);
	// 	return $fragment;
	// }
	// static function html(string $data):static
	// {
	// 	$document = new static;
	// 	$document->loadHTML($data);
	// 	return $document;
	// }
}
class webapp_form
{
	public readonly bool $echo;
	public readonly ?webapp $webapp;
	public readonly webapp_html $xml, $captcha;
	private webapp_html $fieldset;
	private $files = [], $fields = [], $index = 0;
	final function __construct(private readonly array|webapp|webapp_html $context, ?string $action = NULL)
	{
		[$this->webapp, $this->xml] = ($this->echo = $context instanceof webapp_html)
			? [$context->webapp(), $context->append('form', [
				'method' => 'post',
				'autocomplete' => 'off',
				//'onsubmit' => 'webapp.submit(this)',
				'class' => 'webapp',
				...is_string($action) ? ['action' => $action] : []])]
			: [$context instanceof webapp ? $context : NULL, new webapp_html('<form/>')];
		$this->xml['enctype'] = 'application/x-www-form-urlencoded';
		$this->fieldset();
	}
	function __invoke(array $values = []):NULL|array|static
	{
		do
		{
			if ($this->echo)
			{
				return $this->setdefault($values);
			}
			if (is_array($this->context))
			{
				$errors = &$this->errors;
				$input = $this->context;
			}
			else
			{
				$errors = &($this->context)(new stdclass)->errors;
				$input = $this->context->request_content((string)$this->xml['enctype']);
				if ($this->captcha)
				{
					if (array_key_exists('captcha_encrypt', $input)
						&& array_key_exists('captcha_decrypt', $input)
						&& $this->context->captcha_verify($input['captcha_encrypt'], $input['captcha_decrypt'])) {
						unset($input['captcha_encrypt'], $input['captcha_decrypt']);
					}
					else
					{
						$name = 'captcha';
						break;
					}
				}
				// foreach ($this->files as $name => $node)
				// {
				// 	$uploadedfile = $this->context->request_uploadedfile($name);
				// 	if ((isset($node['required']) > $uploadedfile->count())
				// 		|| (isset($node['accept']) && $uploadedfile->detect($node['accept']) === FALSE)
				// 		|| (isset($node['data-maxfile']) && $uploadedfile->count() > intval($node['data-maxfile']))
				// 		|| (isset($node['data-maxsize']) && $uploadedfile->size() > intval($node['data-maxsize']))) {
				// 		break 2;
				// 	}
				// 	continue;
				// }
			}
			$values = [];
			foreach ($this->fields as $name => $node)
			{
				$tagname = $node->getName();
				// if (isset($input[$name]) === FALSE)
				// {
				// 	switch ($tagname)
				// 	{
				// 		case 'div':
				// 			foreach ($node->xpath('label/input[@name]') as $input)
				// 			{
				// 				if (isset($input['required']))
				// 				{
				// 					break 4;
				// 				}
				// 			}
				// 			break;
				// 		default:
				// 			if (isset($node['required']))
				// 			{
				// 				break 3;
				// 			}
				// 			break;
				// 	}
				// 	$values[$name] = NULL;
				// 	continue;
				// }
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
						if ((is_scalar($value) && static::validate($node, $value)) === FALSE)
						{
							break 3;
						}
				}
				$values[$name] = $value;
			}
			return $values;
		} while (0);
		$errors[] = "Form input[{$name}] invalid";
		return NULL;
		
	}
	function fieldset(string $name = NULL):webapp_html
	{
		return $this->fieldset = $this->xml->fieldset($name);
	}
	function progress():webapp_html
	{
		return $this->fieldset->progress();
	}
	function button(string $name, string $type = 'button', array $attributes = []):webapp_html
	{
		return $this->fieldset->append('button', [$name, 'type' => $type] + $attributes);
	}
	function captcha(string $name):?webapp_html
	{
		if ($this->webapp && $this->webapp['captcha_echo'])
		{
			$this->captcha = $this->fieldset($name);
			$this->field('captcha_encrypt');
			$this->field('captcha_decrypt', 'text', ['placeholder' => 'Type following captcha', 'onfocus' => 'this.select()', 'required' => NULL]);
			if ($this->echo)
			{
				$this->fields['captcha_encrypt']['value'] = $this->webapp->captcha_random($this->webapp['captcha_unit'], $this->webapp['captcha_expire']);
				$this->fieldset()->setattr([
					'style' => "height:{$this->webapp['captcha_params'][1]}px;background:url(?captcha/{$this->fields['captcha_encrypt']['value']}) no-repeat center",
					'onckick' => ''
				]);
				$this->fieldset = $this->captcha;
			}
			unset($this->fields['captcha_encrypt'], $this->fields['captcha_decrypt']);
		}
		return $this->captcha ?? NULL;
	}
	function field(string $name, string $type = 'hidden', array $info = []):webapp_html
	{
		// if (is_string($fieldname) && is_array($fieldinfo))
		// {
		// 	$alias = $name = preg_match('/^\w+/', $fieldname, $pattern) ? $pattern[0] : $this->index++;


		// 	$attributes = [
		// 		'type' => array_key_exists('type', $fieldinfo) ? strtolower($fieldinfo['type']) : 'hidden',
		// 		'name' => &$alias] + $fieldinfo;
		// 	switch ($attributes['type'])
		// 	{
		// 		case 'textarea':
		// 			//$this->fields[$name] = $this->fieldset->append('textarea', ['name' => $alias] + $attributes);
		// 			break;
		// 		case 'file':
		// 			$this->xml['enctype'] = 'multipart/form-data';
		// 		default:
		// 			$this->{$attributes['type'] === 'file' ? 'files' : 'fields'}[$name] = $this->fieldset->append('input', $attributes);
		// 	}
		// 	//print_r($attributes);
		// }
		$alias = $name = preg_match('/^\w+/', $name, $pattern) ? $pattern[0] : $this->index++;
		switch ($type = strtolower($type))
		{
			case 'radio':
			case 'checkbox':
				// $node = &$this->fieldset->div[];
				// $node['data-type'] = $typename;
				// if ($typename === 'checkbox')
				// {
				// 	$alias .= '[]';
				// }
				// foreach ($attributes as $value => $comment)
				// {
				// 	$node->labelinput($alias, $typename, $value, $comment);
				// }
				// return $this->fields[$rename] = $node;
			// case 'set':
			// case 'enum':
			// case 'setinput':
			// case 'enuminput':
			case 'textarea':
				return $this->fields[$name] = $this->fieldset->append('textarea', ['name' => $alias] + $info);
			// case 'file':
			// 	$this->xml['enctype'] = 'multipart/form-data';
			// case 'select':
			// 	if (array_key_exists('multiple', $info))
			// 	{
			// 		$alias .= '[]';
			// 		$attributes['multiple'] = NULL;
			// 	}
			// 	if ($typename === 'select')
			// 	{
			// 		$node = $this->fieldset->append('select', ['name' => $alias]);
			// 		if (array_key_exists('value', $attributes) && is_array($attributes['value']))
			// 		{
			// 			$node->options($attributes['value']);
			// 			unset($attributes['value']);
			// 		}
			// 		if (array_key_exists('optgroup', $attributes) && is_array($attributes['optgroup']))
			// 		{
			// 			$node->optgroup($attributes['optgroup']);
			// 			unset($attributes['optgroup']);
			// 		}
			// 		return $this->fields[$rename] = $node->setattr($attributes);
			// 	}
			default:
				return $this->fields[$name] = $this->fieldset->append('input', ['type' => $type, 'name' => $alias] + $info);
		}
	}
	private function setdefault(array $values):static
	{
		foreach ($this->fields as $name => $node)
		{
			if (isset($values[$name]))
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
						foreach (is_array($values[$name]) ? $values[$name] : [$values[$name]] as $value)
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
						$node->text($values[$name]);
						continue 2;
					default:
						$node['value'] = $values[$name];
				}
			}
		}
		return $this;
	}
	private static function is_numeric(webapp_html $node, string $value):bool
	{
		if (is_numeric($value))
		{
			if (($f = floatval($value))
				&& isset($node['step'])
				&& preg_match('/^\d+(?:\.(\d+))?$/', (string)$node['step'], $pattern)
				&& floatval($pattern[0])) {
				if (isset($pattern[1]) && intval($pattern[1]))
				{
					$v = intval(1 . str_repeat('0', strlen($pattern[1])));
					if (strpos((string)($i = $f * $v), '.') || $i % ($pattern[0] * $v))
					{
						return FALSE;
					}
				}
				else
				{
					if (strpos((string)$f, '.') || $f % intval($pattern[0]))
					{
						return FALSE;
					}
				}
			}
			return (isset($node['max']) === FALSE || floatval($node['max']) >= $f)
				&& (isset($node['min']) === FALSE || floatval($node['min']) <= $f);
		}
		return FALSE;
	}
	static function validate(webapp_html $node, string $value):bool
	{
		return match (strtolower((string)$node['type']))
		{
			'color' 		=> preg_match('/^#[0-9a-f]{6}$/i', $value) === 1,
			'date'			=> preg_match('/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[1-2]\d|3[0-2])$/', $value) === 1,
			'datetime',
			'datetime-local'=> preg_match('/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[1-2]\d|3[0-2])T(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1,
			'email'			=> preg_match('/^[\w\.]+@[0-9a-z]+(\.[0-9a-z])?/i', $value) === 1,
			'month'			=> preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $value) === 1,
			'number',
			'range'			=> self::is_numeric($node, $value),
			'time'			=> preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1,
			'url'			=> preg_match('/^[a-z][a-z0-9]*\:\/\//i', $value) === 1,
			'week'			=> preg_match('/^\d{4}-W(?:0[1-9]|[1-4]\d|5[0-3])$/', $value) === 1,
			default			=> (isset($node['pattern']) === FALSE || preg_match("/^{$node['pattern']}$/", $value))
				&& (isset($node['maxlength']) === FALSE || intval($node['maxlength']) >= strlen($value))
				&& (isset($node['minlength']) === FALSE || intval($node['minlength']) <= strlen($value))
		};
	}
	// static function data(array|webapp_html $fields, array|webapp $contents):array
	// {
	// 	$form = new static($contents);
	// 	$form['name'] = ['www'=> 333];
	// 	$form['age'] = ['class'=> 'wa'];
	// 	// foreach ($node->xpath('//*[@name]') as $field)
	// 	// {
	// 	// 	//if ($field->getName())
	// 	// 	print_r($field->getName());
	// 	// }



	// 	return $form();
	// }
}
class webapp_table
{
	public readonly array $paging;
	public readonly webapp_html $xml, $tbody;
	function __construct(webapp_html $node, iterable $data = [], Closure $output = NULL, mixed ...$params)
	{
		[$this->paging, $this->xml, $this->tbody] = [
			$data?->paging ?? [],
			$root = &$node->table[],
			&$root->tbody];
		$root['class'] = 'webapp';
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
	}
	function __get(string $name)
	{
		// return match ($name)
		// {
		// 	'caption'	=> $this->caption = $this->xml->insert('caption', 'first'),
		// 	'colgroup'	=> $this->colgroup = $this->caption->insert('colgroup', 'after'),
		// 	'thead'		=> $this->thead = $this->tbody->insert('thead', 'before'),
		// 	'fieldname'	=> $this->fieldname = &$this->thead->tr[],
		// 	'title'		=> (property_exists($this, 'fieldname') ? $this->fieldname->insert('tr', 'before') : $this->thead->append('tr'))->append('td', ['colspan' => $this->column]),
		// 	'column'	=> isset($this->tbody->tr->td) ? count($this->tbody->tr->td) : 0,
		// 	'tfoot'		=> $this->tfoot = $this->tbody->insert('tfoot', 'after'),
		// 	default		=> NULL
		// }

		switch ($name)
		{
			case 'caption': return $this->caption = $this->xml->insert('caption', 'first');
			case 'colgroup': return $this->colgroup = $this->caption->insert('colgroup', 'after');
			case 'thead': return $this->thead = $this->tbody->insert('thead', 'before');
			case 'fieldset': return $this->fieldset = &$this->thead->tr[];
			case 'title': return (property_exists($this, 'fieldset') ? $this->fieldset->insert('tr', 'before') : $this->thead->append('tr'))->append('td', ['colspan' => $this->column]);
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
	function fieldset(string ...$names):webapp_html
	{
		$node = $this->fieldset;
		foreach ($names as $name)
		{
			$node->td[] = $name;
		}
		return $node;
	}
}
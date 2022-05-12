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
		return dom_import_simplexml($this);
	}
	function clone(bool $deep = TRUE):DOMNode
	{
		return $this->dom()->cloneNode($deep);
	}
	function cdata(string $value):DOMNode
	{
		return $this->dom()->appendChild(new DOMCdataSection($value));
	}
	function comment(string $value):DOMNode
	{
		return $this->dom()->appendChild(new DOMComment($value));
	}
	function entity(string $name):DOMNode
	{
		return $this->dom()->appendChild(new DOMEntityReference($name));
	}
	function pi(string ...$values):DOMNode
	{
		return $this->dom()->appendChild(new DOMProcessingInstruction(...$values));
	}
	function text(string $data = ''):DOMNode
	{
		return $this->dom()->appendChild(new DOMText($data));
	}
	function xml(string $data):DOMNode
	{
		$dom = $this->dom();
		$xml = $dom->ownerDocument->createDocumentFragment();
		if ($xml->appendXML($data))
		{
			$dom->appendChild($xml);
		}
		return $xml;
	}
	function insert(DOMNode|string $element, ?string $position = NULL):DOMNode|static
	{
		$dom = $this->dom();
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
		return $this;
	}
	//获取属性
	function getattr(string $name = NULL):NULL|string|array
	{
		$attributes = ((array)$this->attributes())['@attributes'] ?? [];
		return is_string($name) ? $attributes[$name] ?? NULL : $attributes;
	}
	//设置属性
	function setattr(string|array $name, $value = NULL):static
	{
		$node = $this;
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
			? $this->addChild($name)->setattr($contents)
			: $this->addChild($name, $contents);
	}
	function appends(string $name, iterable $contents, string $keyattr = NULL):static
	{
		if ($keyattr) foreach ($contents as $key => $content)
		{
			$this->append($name, is_array($content)
				? [$keyattr => $key] + $content
				: [$content, $keyattr => $key]);
		}
		else foreach ($contents as $content)
		{
			$this->append($name, $content);
		}
		return $this;
	}

	


	function parent():static
	{
		return $this->xpath('..')[0] ?? $this;
	}
	function remove():static
	{
		$child = $this->dom();
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
	function anchor(string $href)
	{}
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
	function labelinput(string $name, string $type, float|string $value, string $comment):static
	{
		$node = &$this[0]->label[];
		$node->append('input', ['type' => $type, 'name' => $name, 'value' => $value]);
		$node->text($comment);
		return $node;
	}
	
	
	function options(iterable $values, string ...$selected):static
	{
		foreach ($values as $value => $content)
		{
			if (is_iterable($content))
			{
				$this[0]->append('optgroup', ['label' => $value])->options($content, ...$selected);
				continue;
			}
			$this[0]->append('option', in_array($value, $selected, TRUE) ? [$content, 'value' => $value, 'selected' => NULL] : [$content, 'value' => $value]);
		}
		return $this[0];
	}
	function select(iterable $options, bool $multiple = FALSE, ?string $name = NULL, ?string $placeholder = NULL):static
	{
		if ($name)
		{
			$node = $placeholder ? $this[0]->details($placeholder) : $this[0];
			if ($multiple)
			{
				$name .= '[]';
				$type = 'checkbox';
			}
			else
			{
				$type = 'radio';
			}
			$ul = $node->append('ul');//, ['class' => 'webapp-gap-menu-hover']
			foreach ($options as $value => $comment)
			{
				$ul->append('li')->labelinput($name, $type, $value, $comment);
			}

			//$node->ulselect($name, $options, $multiple);
			//$node['class'] = 'webapp-button';


		}
		else
		{
			$node = $this[0]->append('select');
			$node->options($options);
			//return $this[0]->append('select')->options($options);
		}
		
		
		
		return $node;



		
	}
	// function section(string $title, int $level = 1):static
	// {
	// 	$node = &$this[0]->section[];
	// 	$node->{'h' . max(1, min(6, $level))} = $title;
	// 	return $node;
	// }
	function ulselect(string $name, iterable $options, bool $multiple = FALSE):static
	{
		$node = $this[0]->append('ul', ['class' => 'webapp-select']);
		if ($multiple)
		{
			$name .= '[]';
			$type = 'checkbox';
		}
		else
		{
			$type = 'radio';
		}
		foreach ($options as $value => $comment)
		{
			$node->append('li')->labelinput($name, $type, $value, $comment);
		}
		
		
		return $node;
	}
	function selected(...$values):static
	{
		if ($value = join(' or ', array_map(
			fn($value) => sprintf('@value="%s"', $value),
			array_filter($values, is_scalar(...))))) {
			[$selected, $selector] = match ($this->getName())
			{
				'ul'		=> ['checked', 'li/label/input'],
				'details'	=> ['checked', 'ul/li/label/input'],
				default		=> ['selected', 'option']
			};
			foreach ($this->xpath("{$selector}[{$value}]") as $node)
			{
				$node->setattr([$selected => NULL]);
			}
		}
		return $this;
	}
	function detailed(string $name, iterable $options, bool $multiple = FALSE):static
	{
		$node = $this[0]->details('');
		$node->ulselect($name, $options, $multiple);
		$node['class'] = 'webapp-button';
		return $node;
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
	function cond(array $fields, ?string $action = NULL):static
	{
		$form = $this[0]->form($action);


		$form->xml['class'] .= '-cond';

		$form->button('Remove')['onclick'] = 'this.parentElement.remove()';
		$form->field('F', 'select', ['option' => $fields]);//['onchange'] = 'this.nextElementSibling.nextElementSibling.placeholder=this.options[this.selectedIndex].dataset.comment||""';//this.nextElementSibling.nextElementSibling.placeholder="asd";
		$form->field('d', 'select', ['option' => [
			'eq' => '=',
			'ne' => '!=',
			'gt' => '>',
			'ge' => '>=',
			'lt' => '<',
			'le' => '<=',
			'lk' => '%',
			'nl' => '!%',
			'in' => '()',
			'ni' => '!()'
		]]);
		$form->field('cond', 'search');


		$form->fieldset()['class'] = 'merge';
		$form->button('Append')['onclick'] = 'this.parentElement.parentElement.appendChild(this.parentElement.previousElementSibling.cloneNode(true))';
		$form->button('Clear')['class'] = 'danger';
		$form->button('Submit', 'submit')['class'] = 'primary';
		return $form->xml;
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
class webapp_form implements ArrayAccess
{
	public readonly bool $echo;
	public readonly ?webapp $webapp;
	public readonly webapp_html $xml, $captcha;
	public webapp_html $fieldset;
	private array $files = [], $fields = [], $format = [];
	function __construct(private readonly array|webapp|webapp_html $context, ?string $action = NULL)
	{
		[$this->webapp, $this->xml] = ($this->echo = $context instanceof webapp_html)
			? [$context->webapp(), $context->append('form', [
				'method' => 'post',
				'autocomplete' => 'off',
				//'onsubmit' => 'webapp.submit(this)',
				'class' => 'webapp',
				...is_string($action) ? ['action' => $action] : []])]
			: [$context instanceof webapp ? $context : NULL, new webapp_html('<form/>')];
		//$this->xml['enctype'] = 'application/x-www-form-urlencoded';
		$this->enctype()->fieldset();
	}
	function offsetExists(mixed $offset):bool
	{
		return array_key_exists($offset, $this->fields);
	}
	function offsetGet(mixed $offset):?webapp_html
	{
		return $this->fields[$offset] ?? NULL;
	}
	function offsetSet(mixed $offset, mixed $value):void
	{

	}
	function offsetUnset(mixed $offset):void
	{
		unset($this->fields[$offset]);
	}
	function format(string $field, callable $input, callable $output)
	{
		
	}

	//
	function a():ArrayObject
	{
		
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
				if (isset($this->captcha))
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
				$value = $input[$name] ?? NULL;
				switch ($tagname)
				{
					case 'fieldset':
						break;
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
	function fatch()
	{
		
	}
	function files(string $name):ArrayObject
	{

	}
	function enctype(string $type = 'application/x-www-form-urlencoded'):static
	{
		$this->xml['enctype'] = $type;
		return $this;
	}
	function fieldset(string $name = NULL):webapp_html
	{
		return $this->fieldset = $this->xml->fieldset($name);
	}
	function legend(string $name):webapp_html
	{
		$this->fieldset->legend = $name;
		return $this->fieldset->legend;
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
					'style' => "height:{$this->webapp['captcha_params'][1]}px;cursor:pointer;background:url(?captcha/{$this->fields['captcha_encrypt']['value']}) no-repeat center",
					'onclick' => 'fetch("?captcha").then(r=>r.text()).then(r=>this.style.backgroundImage=`url(?captcha/${this.previousElementSibling.firstElementChild.nextElementSibling.value=r})`)'
				]); 
				$this->fieldset = $this->captcha;
			}
			unset($this->fields['captcha_encrypt'], $this->fields['captcha_decrypt']);
		}
		return $this->captcha ?? NULL;
	}
	function field(string $name, string $type = 'hidden', array $attr = []):webapp_html
	{
		$alias = preg_match('/^\w+/', $name, $pattern) ? $pattern[0] : count($this->files) + count($this->fields);
		return $type === 'file'
			? $this->files[$alias] = $this->enctype('multipart/form-data')->fieldset->append('input', [
				'type' => 'file', 'name' => array_key_exists('multiple', $attr) ? "{$alias}[]" : $alias] + $attr)
			: $this->fields[$alias] = match ($type)
			{
				'webapp-select' => $this->fieldset->select(
					$attr['options'] ?? [],
					array_key_exists('data-multiple', $attr),
					$name, $attr['data-placeholder'] ?? NULL)->setattr($attr),
				'radio',
				'checkbox' => $this->fieldset->select($attr['options'] ?? [], $type === 'checkbox', $name),
				'select' => $this->fieldset->select($attr['options'] ?? [])
					->setattr(['name' => array_key_exists('multiple', $attr) ? "{$alias}[]" : $alias] + $attr),



				'textarea' => $this->fieldset->append('textarea', ['name' => $alias] + $attr),
				default => $this->fieldset->append('input', ['type' => $type, 'name' => $alias] + $attr)
			};
	}
	function novalidate():static
	{
		$this->xml->setattr('novalidate');
		return $this;
	}
	function setdefault(array $values):static
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
	private array $column = [];
	function __construct(webapp_html $node, iterable $data = [], Closure $output = NULL, mixed ...$params)
	{
		[$this->paging, $this->xml, $this->tbody] = [
			is_object($data) && property_exists($data, 'paging') && is_array($data->paging) ? $data->paging : [],
			$root = &$node->table[],
			&$root->tbody];
		$root['class'] = 'webapp';
		$output ??= fn($contents) => $this->row()->{is_iterable($contents) ? 'appends' : 'append'}('td', $contents);
		foreach ($data as $values)
		{
			$output->call($this, $values, ...$params);
		}
	}
	function __get(string $name):?webapp_html
	{
		return match ($name)
		{
			'caption'	=> $this->caption = $this->xml->caption ?? $this->xml->insert('caption', 'first'),
			'colgroup'	=> $this->colgroup = $this->xml->colgroup ?? $this->caption->insert('colgroup', 'after'),
			'fieldset'	=> $this->fieldset = $this->tbody->insert('tr', 'first'),
			'thead'		=> $this->thead = $this->xml->thead ?? $this->tbody->insert('thead', 'before'),
			'tfoot'		=> $this->tfoot = $this->xml->tfoot ?? $this->tbody->insert('tfoot', 'after'),
			'row'		=> $this->row(),
			'bar'		=> $this->maxspan($this->bar = $this->thead->append('tr')->append('td'))
								->append('div')->setattr(['class' => 'webapp-bar merge']),
			default		=> NULL
		};
	}
	function row():webapp_html
	{
		return $this->row = $this->tbody->append('tr');
	}
	function cell(NULL|string|array $contents = NULL, string $method = 'setattr'):webapp_html
	{
		return is_iterable($contents)
			? $this->row->append('td')->{$method}($contents)
			: $this->row->append('td', $contents);
	}
	function cells(iterable $contents, ?string $keyattr = NULL):webapp_html
	{
		return $this->row->appends('td', $contents, $keyattr);
	}
	
	function cond(array $fields):webapp_html
	{
		return $this->bar->details('Conditionals')->cond($fields);
	}
	function fieldset(string ...$fields):webapp_html
	{
		$this->fieldset['class'] = 'fieldset';
		foreach ($fields as $field)
		{
			$this->fieldset->td[] = $field;
		}
		return $this->fieldset;
	}
	function maxspan(webapp_html $cell):webapp_html
	{
		$colspan = 0;
		foreach ($this->tbody->tr->td ?? [] as $column)
		{
			$colspan += $column['colspan'] ?? 1;
		}
		if (in_array($cell, $this->column, TRUE) === FALSE)
		{
			$this->column[] = $cell;
		}
		if ($colspan > 1)
		{
			foreach ($this->column as $column)
			{
				$column['colspan'] = $colspan;
			}
		}
		return $cell;
	}
	function header(?string $caption = NULL):webapp_html
	{
		return $this->maxspan($this->thead->insert('tr', 'first')->append('td', [$caption]));
	}
	function footer(?string $content = NULL):webapp_html
	{
		return $this->maxspan($this->tfoot->append('tr')->append('td', [$content]));
	}
	function paging(string $url, int $max = 9):static
	{
		if ($this->paging && $this->paging['max'] > 1)
		{
			$node = $this->footer();
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
}
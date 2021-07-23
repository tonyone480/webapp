<?php
class webapp_xml extends SimpleXMLElement
{
	function webapp():?webapp
	{
		return $this->dom()->ownerDocument->webapp ?? NULL;
	}
	function dom():DOMElement
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
	function entity(string $name)
	{
		return $this[0]->dom()->appendChild(new DOMEntityReference($name));
	}
	function pi(string ...$values):DOMNode
	{
		return  $this[0]->dom()->appendChild(new DOMProcessingInstruction(...$values));
	}
	function text(string ...$values):DOMNode
	{
		return $this[0]->dom()->appendChild(new DOMText(...$values));
	}
	function xml(string $data = NULL):DOMNode
	{
		$dom = $this[0]->dom();
		$xml = $dom->ownerDocument->createDocumentFragment();
		if ($xml->appendXML($data))
		{
			$dom->appendChild($xml);
		}
		return $xml;
	}
	function append(string $name, $mixed = NULL):static
	{
		if (is_array($mixed))
		{
			$node = &$this[0]->{$name}[];
			foreach ($mixed as $attribute => $value)
			{
				$node[$attribute] = $value;
			}
			return $node;
		}
		return $this->addChild($name, $mixed);
	}
	function insert(DOMNode|string $node, string $position = NULL)
	{
		$dom = $this[0]->dom();
		if (is_string($node))
		{
			$node = $dom->ownerDocument->createElement($node);
		}
		switch ($position)
		{
			case 'after'://插入到当前节点之后
				$dom->parentNode->insertBefore($node, $dom->nextSibling);
				break;
			case 'before'://插入到当前节点之前
				$dom->parentNode->insertBefore($node, $dom);
				break;
			case 'first'://插入到当前节点下开头
				$dom->insertBefore($node, $dom->firstChild);
				break;
			default://插入到当前节点下末尾
				$dom->appendChild($node);
		}
		return $node->nodeType === XML_ELEMENT_NODE ? simplexml_import_dom($node, static::class) : $node;
	}
	function remove():void
	{
		unset($this[0]);
	}
	function parent():static
	{
		return $this[0]->xpath('..')[0] ?? $this[0];
	}
	function attr($values = NULL)
	{
		if (func_num_args())
		{
			$node = $this[0];
			if (is_array($values))
			{
				foreach ($values as $name => $value)
				{
					$node[$name] = $value;
				}
				return $node;
			}
			return isset($node[$values]) ? (string)$node[$values] : NULL;
		}
		return ((array)$this[0]->attributes())['@attributes'] ?? [];
	}



	function query(string $selector):array
	{
		$query = ['descendant::*'];
		while (preg_match('/^\s*(\w+|\*)?([\.\#]([\w\x{00c0}-\x{ffff}\-]+))?/u', $selector, $matches) && isset($matches[1]))
		{
			$selector = substr($selector, strlen($matches[0]));
			if ($matches[1] && $matches[1] !== '*')
			{
				$query[] = '[translate(name(.),"abcdefghijklmnopqrstuvwxyz","ABCDEFGHIJKLMNOPQRSTUVWXYZ")="' . strtoupper($matches[1]) . '"]';
			}
			if (isset($matches[2]))
			{
				$query[] = $matches[2][0] === '#' ? '[@id="' . $matches[3] . '"]' : '[contains(concat(" ",normalize-space(@class)," "),"' . $matches[3] . '")]';
			}
			while (preg_match('/^\[\s*(\w+)\s*(?:(\!\=|\$\=|\*\=|\=|\^\=|\~\=|\|\=)\s*(\'|\")?([\w\x{00c0}-\x{ffff}\-]+)\3?\s*)?\]/u', $selector, $matches))
			{
				$selector = substr($selector, strlen($matches[0]));
				if (isset($matches[2]))
				{
					switch ($matches[2])
					{
						case '!=':
							$query[] = '[@' . $matches[1] . '!="' . $matches[4] . '"]';
							break;
						case '$=':
							$query[] = '[@' . $matches[1] . '$="' . $matches[4] . '"]';
							break;
						case '*=':
							$query[] = '[contains(@' . $matches[1] . ',"' . $matches[4] . '")]';
							break;
						case '=':
							$query[] = '[@' . $matches[1] . '="' . $matches[4] . '"]';
							break;
						case '^=':
							$query[] = '[starts-with(@' . $matches[1] . '," ' . $matches[4] . ' ")]';
							break;
						case '~=':
							$query[] = '[contains(concat(" ",normalize-space(@' . $matches[1] . ')," "),"' . $matches[4] . '")]';
							break;
						case '|=':
							$query[] = '[@' . $matches[1] . '="' . $matches[4] . '" or starts-with(@' . $matches[1] . ',"' . $matches[4] . '")]';
					};
					continue;
				}
				$query[] = '[@' . $matches[1] . ']';
			}
			if (preg_match('/^\s*(\+|\>|\~|\,)?/', $selector, $matches))
			{
				$selector = substr($selector, strlen($matches[0]));
				if (isset($matches[1]))
				{
					switch ($matches[1])
					{
						case '+':
							$query[] = '/following-sibling::*[1]';
							break;
						case '>':
							$query[] = '/*';
							break;
						case '~':
							$query[] = '/following-sibling::*';
							break;
						default:
							$query[] = '|descendant::*';
					};
					continue;
				}
				if ($selector)
				{
					$query[] = '/descendant::*';
					continue;
				}
			}
			break;
		}
		return $this[0]->xpath(join($query));
	}
	//以数组递归方式导入当前节点下所有内容
	function import(array $values):static
	{
		foreach ($values as $key => $value)
		{
			$node = &$this[0]->{$key}[];
			if (is_array($value))
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
	static function charsafe(string $content):string
	{
		return preg_replace('/[\x00-\x08\x0b-\x0c\x0e-\x1f]/', '', $content);
	}
}
class webapp_dom extends DOMDocument implements Stringable
{
	const appxml = 'webapp_xml';
	function __toString():string
	{
		return $this->saveXML();
	}
	private function xml(bool $loaded):bool
	{
		return $loaded && ($this->xml = simplexml_import_dom($this, static::appxml)) !== FALSE;
	}
	function load(string $source, int $options = NULL):bool
	{
		return $this->loadXMLFile($source, $options);
	}
	function loadXML(string $source, int $options = NULL):bool
	{
		return $this->xml(parent::loadXML($source, $options));
	}
	function loadXMLFile(string $source, int $options = NULL):bool
	{
		return $this->xml(parent::load($source, $options));
	}
	function loadHTML(string $source, int $options = NULL):bool
	{
		return $this->xml(parent::loadHTML($source, $options | LIBXML_NOWARNING | LIBXML_NOERROR));
	}
	function loadHTMLFile(string $source, int $options = NULL):bool
	{
		return $this->xml(parent::loadHTMLFile($source, $options | LIBXML_NOWARNING | LIBXML_NOERROR));
	}
	function evaluate(string $expression, DOMNode $contextnode = NULL)
	{
		return (new DOMXPath($this))->evaluate($expression, $contextnode);
	}
	function querySelectorAll(string $selectors):array
	{
		return $this->xml->query($selectors);
	}
	function querySelector(string $selectors):?DOMElement
	{
		return $this->querySelectorAll($selectors)[0] ?? NULL;
	}
	function fragment(string $data):DOMDocumentFragment
	{
		$fragment = $this->createDocumentFragment();
		$fragment->appendXML($data);
		return $fragment;
	}
	static function html(string $data):static
	{
		$dom = new static;
		$dom->loadHTML($data);
		return $dom;
	}
}
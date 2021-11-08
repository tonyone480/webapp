<?php
trait webapp_echo
{
	private $webapp;
	abstract function __toString():string;
	function __invoke(webapp $webapp, bool $initialize = FALSE, mixed ...$params):webapp
	{
		if ($this->webapp === NULL)
		{
			$this->webapp = $webapp;
			if ($initialize)
			{
				parent::__construct(...$params);
			}
		}
		return $this->webapp;
	}
	function __get(string $name):mixed
	{
		return $this->{$name} = &$this->webapp->{$name};
	}
	function __call(string $name, array $params):mixed
	{
		return $this->webapp->{$name}(...$params);
	}
}
class webapp_echo_json extends ArrayObject implements Stringable
{
	use webapp_echo;
	function __construct(webapp $webapp, array $data = [])
	{
		$this($webapp, TRUE, $data, ArrayObject::STD_PROP_LIST)->response_content_type('application/json');
	}
	function __toString():string
	{
		return json_encode($this->getArrayCopy(), JSON_UNESCAPED_UNICODE);
	}
}
class webapp_echo_html extends webapp_document
{
	use webapp_echo;
	const xmltype = 'webapp_html';
	function __construct(webapp $webapp, string $data = NULL)
	{
		$this($webapp)->response_content_type("text/html; charset={$webapp['app_charset']}");
		if ($data)
		{
			str_starts_with($data, '<') ? $this->loadHTML($data) : $this->loadHTMLFile($data);
		}
		else
		{
			$this->loadHTML("<!doctype html><html><head><meta charset='{$webapp['app_charset']}'/></head><body/></html>", LIBXML_HTML_NOIMPLIED);
			$this->xml->head->append('meta', ['name' => 'viewport', 'content' => 'width=device-width,initial-scale=1.0']);
			// $this->xml->head->append('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '?scss/webapp']);
			// $this->xml->head->append('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => $webapp->resroot('ps/font-awesome.css')]);
			// $this->xml->head->append('script', ['type' => 'javascript/module', 'src' => $webapp->resroot('js/webapp.js')]);
			$this->article = $this->xml->body->append('article');
			$this->header = $this->article->append('header');
			$this->section = $this->article->append('section');
			$this->footer = $this->article->append('footer', $webapp['copy_webapp']);
		}
	}
	function __toString():string
	{
		//var_dump($this->ownerDocument);
		// return html_entity_decode($this->saveHTML(), ENT_HTML5, $this->webapp['app_charset']);
		return $this->saveHTML($this);
	}
	function title(string $title):void
	{
		$this->xml->head->title = $title;
	}



	function xpath(string $expression):array
	{
		return iterator_to_array((new DOMXPath($this))->evaluate($expression));
	}

	function aside(bool $after = FALSE):webapp_html
	{
		$this->aside = $this->article->section->append('aside');
		$this->section = $this->aside->insert('section', $after ? 'before' : 'after');
		return $this->aside;
	}



	static function form_sign_in(array|webapp|webapp_html $context, string $authurl = NULL):NULL|array|webapp_html_form
	{
		$form = new webapp_html_form($context, $authurl);
		$form->fieldset('Username');
		$form->field('username', 'text', ['placeholder' => 'Type username', 'required' => NULL, 'autofocus' => NULL]);
		$form->fieldset('Password');
		$form->field('password', 'password', ['placeholder' => 'Type password', 'required' => NULL]);
		$form->captcha('Captcha');
		$form->fieldset();
		$form->button('Sign In', 'submit');
		return $form();
	}
}
class webapp_echo_xml extends webapp_document
{
	use webapp_echo;
	function __construct(webapp $webapp)
	{
		$this($webapp, TRUE)->response_content_type('application/xml');
	}
}
class webapp_echo_xls extends webapp_document
{
	use webapp_echo;
	function __construct(webapp $webapp)
	{
		$this($webapp, TRUE)->response_content_type('application/xml');
		// $this($webapp, TRUE)->response_content_type('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
		$this->loadXML("<?xml version='1.0' encoding='{$webapp['app_charset']}'?><?mso-application progid='Excel.Sheet'?><Workbook/>");
		$this->xml->attr([
			'xmlns' => 'urn:schemas-microsoft-com:office:spreadsheet',
			// 'xmlns:o' => 'urn:schemas-microsoft-com:office:office',
			'xmlns:x' => 'urn:schemas-microsoft-com:office:excel',
			'xmlns:ss' => 'urn:schemas-microsoft-com:office:spreadsheet',
			// 'xmlns:html' = 'http://www.w3.org/TR/REC-html40'
		]);
		$style = $this->xml->append('Styles')->append('Style', [
			// 'ss:Name' => 'Normal',
			'ss:ID' => 'sc0'
		]);
		$style->append('Alignment', [
			// 'ss:Horizontal' => 'Center',
			// 'ss:Horizontal' => 'Fill',
			'ss:Vertical' => 'Center'
		]);
		$borders = $style->append('Borders');
		$borders->append('Border', ['ss:Position' => 'Left', 'ss:LineStyle' => 'Continuous', 'ss:Weight' => 1]);
		$borders->append('Border', ['ss:Position' => 'Top', 'ss:LineStyle' => 'Continuous', 'ss:Weight' => 1]);
		$borders->append('Border', ['ss:Position' => 'Right', 'ss:LineStyle' => 'Continuous', 'ss:Weight' => 1]);
		$borders->append('Border', ['ss:Position' => 'Bottom', 'ss:LineStyle' => 'Continuous', 'ss:Weight' => 1]);
		$this->worksheet = $this->xml->append('Worksheet', ['ss:Name' => 'webapp']);
		$this->table = $this->worksheet->append('Table');
	}
	function appendrow(...$values):webapp_xml
	{
		$row = &$this->table->Row[];
		foreach ($values as $value)
		{
			$row->append('Cell', ['ss:StyleID' => 'sc0'])->append('Data', [$value, 'ss:Type' => 'String']);
		}
		return $row;
	}
	function import(iterable $data):static
	{
		foreach ($data as $values)
		{
			$this->appendrow(...$values);
		}
		return $this;
	}
}
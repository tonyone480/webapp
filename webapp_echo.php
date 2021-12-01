<?php
declare(strict_types=1);
trait webapp_echo
{
	protected webapp $webapp;
	abstract function __toString():string;
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
	function __construct(protected webapp $webapp, array|object $array = [])
	{
		$webapp->response_content_type('application/json');
		parent::__construct($array, ArrayObject::STD_PROP_LIST);
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
	function __construct(protected webapp $webapp, string $data = NULL)
	{
		$webapp->response_content_type("text/html; charset={$webapp['app_charset']}");
		if ($data)
		{
			str_starts_with($data, '<') ? $this->loadHTML($data) : $this->loadHTMLFile($data);
		}
		else
		{
			$this->loadHTML("<!doctype html><html><head><meta charset='{$webapp['app_charset']}'/></head><body/></html>");
			$this->xml->head->append('meta', ['name' => 'viewport', 'content' => 'width=device-width,initial-scale=1.0']);
			$this->xml->head->append('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '?scss/webapp', 'media' => 'all']);
			// $this->xml->head->append('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => $webapp->resroot('ps/font-awesome.css')]);
			// $this->xml->head->append('script', ['type' => 'javascript/module', 'src' => $webapp->resroot('js/webapp.js')]);
			$this->article = $this->xml->body->append('article', ['class' => 'webapp']);
			$this->header = $this->article->append('header');
			$this->section = $this->article->append('section');
			$this->footer = $this->article->append('footer', $webapp['copy_webapp']);
		}
	}
	function __toString():string
	{
		//return html_entity_decode($this->saveHTML(), ENT_HTML5, $this->webapp['app_charset']);
		return $this->saveHTML($this);
	}
	function title(string $title):void
	{
		$this->xml->head->title = $title;
	}
	function aside(bool $before = FALSE):webapp_html
	{
		$this->aside = static::xmltype::from($this->section->insert('aside', 'first'));
		$this->section = static::xmltype::from($this->aside->insert('section', $before ? 'before' : 'after'));
		return $this->aside;
	}


	function xpath(string $expression):array
	{
		return iterator_to_array((new DOMXPath($this))->evaluate($expression));
	}





	static function form_sign_in(array|webapp|webapp_html $context, string $authurl = NULL):NULL|array|webapp_form
	{
		$form = new webapp_form($context, $authurl);
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
	function __construct(protected webapp $webapp, string ...$params)
	{
		$webapp->response_content_type('application/xml');
		if ($params)
		{
			parent::__construct(...$params);
		}
	}
}
class webapp_echo_xls extends webapp_echo_xml
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		#$webapp->response_content_type('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
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
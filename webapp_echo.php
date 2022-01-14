<?php
declare(strict_types=1);
trait webapp_echo
{
	public readonly webapp $webapp;
	abstract function __construct(webapp $webapp);
	abstract function __toString():string;
	// function __get(string $name):mixed
	// {
	// 	return $this->{$name} = &$this->webapp->{$name};
	// }
	// function __call(string $name, array $params):mixed
	// {
	// 	return $this->webapp->{$name}(...$params);
	// }
}
class webapp_echo_xml extends webapp_document
{
	use webapp_echo;
	function __construct(public readonly webapp $webapp)
	{
		$webapp->response_content_type('application/xml');
	}
}
class webapp_echo_svg extends webapp_document
{
	use webapp_echo;
	const xmltype = 'webapp_svg';
	function __construct(public readonly webapp $webapp)
	{
		$webapp->response_content_type('image/svg+xml');
		//<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
	}
}
class webapp_echo_html extends webapp_document
{
	use webapp_echo;
	const xmltype = 'webapp_html';
	public readonly webapp_html $header, $aside, $main, $footer;
	function __construct(public readonly webapp $webapp)
	{
		//parent::__construct($webapp);
		//https://validator.w3.org/nu/#textarea
		$webapp->response_content_type("text/html; charset={$webapp['app_charset']}");
		if (func_num_args() === 1)
		{
			$this->loadHTML("<!doctype html><html lang='en'><head><meta charset='{$webapp['app_charset']}'/></head><body/></html>");
			$this->xml->head->append('meta', ['name' => 'viewport', 'content' => 'width=device-width,initial-scale=1']);
			$this->xml->head->append('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/webapp/res/ps/webapp.css', 'media' => 'all']);
			// $this->xml->head->append('style', ['type' => 'text/css', 'media' => 'print'])->cdata('body>div>*:not(main){display:none}');


			
			// $this->xml->head->append('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => $webapp->resroot('ps/font-awesome.css')]);
			// $this->xml->head->append('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => $webapp->resroot('ps/font-awesome.css')]);
			// $this->xml->head->append('script', ['type' => 'text/javascript', 'src' => '/webapp/res/js/webapp.js']);
			
			$root = $this->xml->body->append('div', ['class' => 'webapp-grid']);
			[$this->header, $this->aside, $this->main, $this->footer] = [
				&$root->header, &$root->aside, &$root->main,
				$root->append('footer', $webapp['copy_webapp'])];
		}
		else
		{
			if (is_string($data = func_get_arg(1)))
			{
				str_starts_with($data, '<') ? $this->loadHTML($data) : $this->loadHTMLFile($data);
			}
		}
	}
	function __toString():string
	{
		return $this->saveHTML($this);
	}
	function title(string $title):void
	{
		$this->xml->head->title = $title;
	}
	// function addcss(string $rule):DOMText
	// {
	// 	return ($this->style ??= $this->xml->head->append('style', ['media' => 'all']))->text($rule);
	// }
	function nav(array $link):webapp_html
	{
		$node = $this->header->append('nav', ['class' => 'webapp']);
		$node->atree($link, TRUE);
		return $node;
	}
	function search(?string $action = NULL):webapp_form
	{
		$form = $this->header->form($action);
		$form->xml['method'] = 'get';
		$form->field('search', 'search');
		$form->button('Search', 'submit');
		return $form;
	}








	
	function xpath(string $expression):array
	{
		return iterator_to_array((new DOMXPath($this))->evaluate($expression));
	}

	static function form_sign_in(array|webapp|webapp_html $context, ?string $authurl = NULL):NULL|array|webapp_form
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
class webapp_echo_json extends ArrayObject implements Stringable
{
	use webapp_echo;
	function __construct(public readonly webapp $webapp, array|object $data = [])
	{
		$webapp->response_content_type('application/json');
		parent::__construct($data, ArrayObject::STD_PROP_LIST);
	}
	function __toString():string
	{
		return json_encode($this->getArrayCopy(), JSON_UNESCAPED_UNICODE);
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
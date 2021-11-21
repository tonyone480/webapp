<?php
require __DIR__ . '/../webapp_io_std.php';
$a = new webapp_mysql;

$a->select_db('gzh');

// var_dump( $a->gzh_issue->append([
// 	'key' => 'zzR9UA5EOPBS',
//     'time' => 0,
//     'name' => 123,
//     'email' => '22@123.com',
//     'contacttel' => 123,
//     'photo' => '0D70FM9ARURN.png',
//     'describe' => '123333',
//     'answer' => '阿萨大大'
// ]) );

var_dump( $a->gzh_issue->delete('where `key`="123"') );


// foreach ($a->gzh_issue('where time>-1') as $p)
// {
// 	print_r($p);
// }


//var_dump( $a->sprintf('select now(?S)', ['as' => 'ee']) );
exit;
class webapp_mysql_api extends webapp_echo_json
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp, ['connected' => $webapp->mysql_connected()]);
		//$webapp->errors($this);
	}
	function post_console()
	{
		//$this->request_uploadedfile('uploadfile', 10)->moveto('d:/cid');


		$a = 123;
		$b = 'ggg';

		//->moveto("d:/qq/{$a}/{$b}{day}/{hash,-4}.{type}")

		var_dump( $this->hash_time33('d:/qq/{date,0,4}/{date,4}/{hash,-4}.{type}') );
		var_dump( $this->hash_time33('1:/qq/{date,0,4}/{date,4}/{hash,-4}.{type}', TRUE) );
		print_r( $this->request_uploadedfile('uploadfile', 5)->moveto('d:/qq/{year}/{month}{day}/{hash,-4}.{type}') );

		// $this['success'] = ($this->webapp->mysql)('sdadwd');
		// $this['success'] = ($this->webapp->mysql)('sdadaaawd');
		//$this['success'] = 123;
	}
	function post_insert()
	{
		print_r($this->form_datatable_build($this->datatable)->fetch() );
	}
	function post_update()
	{
		print_r($this->form_datatable_build($this->datatable)->fetch() );
	}
}

new class extends webapp
{
	function __construct()
	{
		if ($this->no_sign_in_admin(new io)) return;
		if ($this['app_mapping'] === $this)
		{
			$this->app('webapp_echo_html')->title('MySQL Admin');
			$this->app->header->navbar([
				['Home', 'href' => '?'],
				['Console', 'href' => '?console'],
				['Microsoft', [
					['Windows Server 2016', 'href' => '#'],
					['Windows XP Profession', 'href' => '#'],
					['Windows XP Home', 'href' => '#'],
					['Else Windows', [
						['Windows 98', 'href' => '#'],
						['Windows 2000', 'href' => '#'],
						['Windows Me', 'href' => '#']
					]]
				]]
			]);
			if ($this->mysql_connected() === FALSE || $this['app_index'] === 'get_home')
			{
				return $this['app_index'] = $this['app_index'] === 'post_home' ? 'post_home' : 'get_home';
			}
			$this->app->aside();
			$mysql_charset = $this['mysql_charset'];
			$this->app->aside->append('div', ['style' => 'padding:0.6rem'])->append('select', [
				'style' => 'width:100%',
				'onchange' => 'location.href=`?home/${this.value}`'
			])->iter($this->mysql->iter('show character set'), function(array $item) use($mysql_charset)
			{
				$node = $this->append('option', [$item['Charset'], 'value' => $item['Charset']]);
				if ($item['Charset'] === $mysql_charset)
				{
					$node['selected'] = NULL;
				}
			});
			$mysql_database = $this['mysql_database'];
			$this->app->aside->append('ul')->iter($this->mysql->iter('show databases'), function(array $item) use($mysql_database)
			{
				$node = &$this->li[];
				$node->append('a', [$item['Database'], 'href' => "?database/{$item['Database']}"]);
			});
		}
	}
	function mysql_connected():bool
	{
		if (count($connect = json_decode($this->request_cookie_decrypt('mysql_connect'), TRUE) ?? []) === 3)
		{
			//$this->request_query('db')
			[
				$this['mysql_host'],
				$this['mysql_user'],
				$this['mysql_password'],
				$this['mysql_database'],
				$this['mysql_charset'],
			] = [...$connect, '', $this->request_cookie('mysql_charset') ?? $this['mysql_charset']];
			return @$this->mysql->connect_errno === 0;
		}
		return FALSE;
	}
	// function datatable():string
	// {
	// 	return $this->request_query('datatable');
	// }
	// function selectdb(string $fullname):string
	// {
	// 	return count($select = explode('--', $fullname, 2)) > 1 && $this->mysql->select_db($select[0]) ? $select[1] : $fullname;
	// }
	// function datatable(string $fullname = NULL):webapp_mysql_table
	// {
	// 	return $this->mysql->{$this->selectdb($fullname)};
	// }

	function post_home()
	{
		$this->response_location('?console');
		$this->response_cookie_encrypt('mysql_connect', json_encode(array_values($this->request_content()), JSON_UNESCAPED_UNICODE));
	}
	function get_home(string $charset = NULL)
	{
		if (is_string($charset))
		{
			$this->response_location($this->request_referer() ?? '?home');
			$this->response_cookie('mysql_charset', $charset);
			return;
		}
		$form = $this->app->section->form('?home');
		$form->fieldset('MySQL Host');
		$form->field('host', 'text')['value'] = $this['mysql_host'];
		$form->fieldset('Username');
		$form->field('user', 'text')['value'] = $this['mysql_user'];
		$form->fieldset('Password');
		$form->field('password', 'text')['value'] = $this['mysql_password'];
		$form->fieldset();
		$form->button('Connect to MySQL', 'submit');
	}
	function get_console()
	{
		$form = $this->app->section->form('?api/console');
		$form->field('uploadfile', 'file', ['multiple' => NULL]);
		//$form->field('createdb', 'text');
		//$form->button('Create Database', 'submit');
		$form->button('Query', 'submit');
		$form->fieldset();
		//$form->field('console', 'textarea');
		//$form->fieldset();
		
	}
	function get_database()
	{
	
		return;
		$this->app->section->style[] = <<<STYLE
table.mysql>thead>tr>td>span,
table.mysql>tbody>tr>td>span,
table.mysql>tbody>tr>td>a{
	display: list-item;
	list-style: none;
}
STYLE;
		$table = $this->app->section->table($this->mysql->list('show table status from ?a', $dbname), function(array $val, string $dbname)
		{
			$tr = &$this->tbody->tr[];
			$td = &$tr->td[];
			$td->append('a', [$val['Name'], 'href' => "?datatable/{$dbname}--{$val['Name']}"]);
			$td->span[] = "{$val['Rows']}:{$val['Comment']}";

			$td = &$tr->td[];
			$td->span[] = "{$val['Engine']}/{$val['Version']}";
			$td->span[] = $val['Collation'];

			$td = &$tr->td[];
			$td->span[] = $val['Row_format'];
			$td->span[] = $val['Rows'];

			$td = &$tr->td[];
			$td->span[] = $val['Avg_row_length'];
			$td->span[] = $val['Data_length'];

			$td = &$tr->td[];
			$td->span[] = $val['Index_length'];
			$td->span[] = $val['Data_free'];

			$td = &$tr->td[];
			$td->span[] = $val['Auto_increment'];
			$td->span[] = $val['Create_time'];

			$td = &$tr->td[];
			$td->span[] = $val['Update_time'];
			$td->span[] = $val['Check_time'];

			$td = &$tr->td[];
			$td->span[] = $val['Collation'];
			$td->span[] = $val['Checksum'];

			$td = &$tr->td[];
			$td->span[] = $val['Create_options'];
		}, $dbname);
		$fieldname = $table->fieldname;
		foreach ([
			['Name', 'Rows/Comment'], ['Engine/Version', 'Collation'], ['Row_format', 'Rows'],
			['Avg_row_length', 'Data_length'], ['Index_length', 'Data_free'], ['Auto_increment', 'Create_time'],
			['Update_time', 'Check_time'], ['Collation', 'Checksum']
			] as $titles) {
			$td = &$fieldname->td[];
			$td->span[] = $titles[0];
			$td->span[] = $titles[1];
		}
		$table->xml['class'] = 'webapp mysql';
		// ->fieldname('Name/Comment', 'Engine/Version', 'Row_format',
		// 'Rows', 'Avg_row_length', 'Data_length', 'Max_data_length',
		// 'Index_length', 'Data_free', 'Auto_increment', 'Create_time',
		// 'Update_time', 'Check_time', 'Collation', 'Checksum',
		// 'Create_options');
	}
	function get_datatable(string $fullname)
	{

		$this->app->section->table($this->mysql->list('show full fields from ?a', $this->selectdb($fullname)), function(array $val, string $fullname)
		{
			$tr = &$this->tbody->tr[];
			$tr->td[] = $val['Field'];



		}, $fullname)->fieldname('Field', 'Type', 'Collation', 'Null',
		'Key', 'Default', 'Extra', 'Privileges',
		'Comment');
		
	}

	function get_select(string $fullname)
	{
	

		$datatable = $this->mysql->{$this->selectdb($fullname)};
		$table = $this->app->section->table($datatable('order by help_keyword_id asc')->paging($this->page), function(array $val, string $fullname, string $primary):void
		{
			$tr = &$this->tbody->tr[];
			$tr->append('td')->append('a', ['Edit', 'href' => "?editor/{$fullname},primary:{$primary},key:{$val[$primary]}"]);
			foreach ($val as $col)
			{
				$tr->td[] = $col;
			}
		}, $fullname, $datatable->primary);
		$table->paging('?select/mysql--help_keyword,page:');
	}
	function form_datatable_build(string $fullname, webapp_html_xml $node = NULL, string $action = NULL):webapp_html_form
	{
		$form = $this->formdata($node, $action);
		//$form = new webapp_html_form($this, $node, $action);
		foreach ($this->mysql->list('describe ?a', $this->selectdb($fullname)) as $info)
		{
			$form->fieldset($info['Field']);
			preg_match('/\w+(?:\((\d+)\)(?:\s(\w+))?)?/', $info['Type'], $type);
			switch ($type[0])
			{
				case 'tinyint':
				case 'smallint':
				case 'mediumint':
				case 'int':
				case 'bigint':
				case 'float':
				case 'double':
				case 'decimal':
					$form->field($info['Field'], 'number');
					break;
				case 'DATE':
				case 'TIME':
				case 'YEAR':
				case 'DATETIME':
				case 'TIMESTAMP':
					$form->field($info['Field'], 'text');
					break;
				default:
					$form->field($info['Field'], 'text');
			}
		}
		$form->fieldset();
		$form->button('Insert', 'submit');
		$form->button('Update', 'submit');
		return $form;
	}
	function get_editor(string $fullname)
	{
		$this->form_datatable_build($fullname, $this->app->section, "?api/insert,datatable:{$fullname}");
	}
};
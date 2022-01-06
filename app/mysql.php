<?php
require __DIR__ . '/../webapp_io_std.php';
class webapp_router_query extends webapp_echo_json
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		if ($webapp->mysql->connect_errno)
		{
			$webapp->break(fn() => 500);
		}
		else
		{
			// if ($referer = $webapp->request_referer())
			// {
			// 	$webapp->response_location($referer);
			// }
		}
	}
	function post_console()
	{
		$this['post'] = $_POST;
	}
}
new class extends webapp
{
	function __construct()
	{
		if ($this->init_admin_sign_in(new io)) return;
		[$this->mysql_host, $this->mysql_user, $this->mysql_password]
			= json_decode($this->request_cookie_decrypt('mysql_connectto') ?? 'NULL', TRUE)
				?? [$this['mysql_host'], $this['mysql_user'], $this['mysql_password']];
		$this->mysql_database = $this->request_cookie('mysql_database');
		$this->mysql_charset = $this->request_cookie('mysql_charset') ?? $this['mysql_charset'];
		if ($this->router === $this)
		{
			$this->app('webapp_echo_html')->title('MySQL Admin');
			$this->app->nav([
				['Home', '?'],
				['Console', '?console'],
				['Microsoft', [
					['Windows Server 2016', '#'],
					['Windows XP Profession', '#'],
					['Windows XP Home', '#'],
					['Else Windows', [
						['Windows 98', '#'],
						['Windows 2000', '#'],
						['Windows Me', '#']
					]]
				]]
			]);
			if (in_array($this->method, ['get_home', 'post_home'], TRUE))
			{
				return;
			}
			if ($this->mysql->connect_errno)
			{
				return $this->break($this->get_home(...));
			}
			$this->app->main['style'] = 'padding:10px';
			$ul = $this->app->aside()->setattr(['class' => 'webapp-ultree'])->append('ul');
	
			
			$ul->append('li')->select(array_combine($this->charset, $this->charset), $this->mysql_charset)->setattr([
				'style' => 'display: block',
				'onchange' => 'location.href=`?home/${this.value}`'
			]);
			

			
			foreach ($this->query('SHOW DATABASES') as $db)
			{
				$node = $ul->append('li');
				$node->append('a', [$db['Database'], 'href' => '?db/' . $this->url64_encode($db['Database'])]);
				if ($db['Database'] === $this->mysql_database)
				{
					$node = $node->append('ul');
					foreach ($this->query('SHOW TABLE STATUS') as $tab)
					{
						$node->append('li')->append('a', ["{$tab['Name']}[" . ($tab['Rows'] ?? 0) . ']', 'href' => '?tab/' . $this->url64_encode($tab['Name'])]);
					}
				}
			}
		}
	}

	function mysql():webapp_mysql
	{
		$mysql = new webapp_mysql($this->mysql_host,
			$this->mysql_user, $this->mysql_password, $this->mysql_database);
		if ($mysql->connect_errno)
		{
			//$this->errors[] = $mysql->connect_error;
		}
		else
		{
			if (in_array($this->mysql_charset,
				$this->charset = $mysql('show character set')->column('Charset'), TRUE)) {
				$mysql->set_charset($this->mysql_charset);
			}
		}
		return $mysql;
	}
	function query(...$params):webapp_mysql
	{
		return ($this->mysql)(...$params);
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
		$this->response_cookie_encrypt('mysql_connectto', json_encode(array_values($this->request_content()), JSON_UNESCAPED_UNICODE));
		$this->response_cookie('mysql_database');
		$this->response_location('?console');
	}
	function get_home()
	{
		
		$form = $this->app->main->form('?home');
		$form->fieldset('MySQL Host');
		$form->field('host', 'text', ['value' => $this->mysql_host]);
		$form->fieldset('Username');
		$form->field('user', 'text', ['value' => $this->mysql_user]);
		$form->fieldset('Password');
		$form->field('password', 'text', ['value' => $this->mysql_password]);
		$form->fieldset();
		$form->button('Connect to MySQL', 'submit');
	}
	function get_console(string $charset = NULL)
	{
		if (is_string($charset))
		{
			$this->response_cookie('mysql_charset', $charset);
			$this->response_location($this->request_referer() ?? '?console');
			return;
		}
		$form = $this->app->main->form('?query/console');
		$form->fieldset('Create');
		$form->field('createdb', 'text');
		$form->button('Create Database', 'submit');
		
		$form->fieldset();
		$form->field('command', 'textarea');
		$form->fieldset();
		$form->button('Query', 'submit');


		
		//$form->fieldset();
		//$form->field('uploadfile', 'file', ['multiple' => NULL]);
		
	}
	function get_db(string $database = NULL)
	{
		if (is_string($database))
		{
			$this->response_cookie('mysql_database', $this->url64_decode($database));
			$this->response_location('?db');
			return 302;
		}
		$table = $this->app->main->table($this->query('SHOW TABLE STATUS')->result($fields), function(array $row, webapp $webapp)
		{
			$tr = &$this->tbody->tr[];
			$td = &$tr->td[];

			$td->append('span')->append('a', [$row['Name'], 'href' => '?tab/' . $webapp->url64_encode($row['Name'])]);
			$td->span[] = $row['Comment'];

			$td = &$tr->td[];
			//$td->span[] = $tab['Collation'];
			$td->span[] = "{$row['Engine']}:{$row['Version']}";
			$td->span[] = "{$row['Row_format']}:{$row['Rows']}";

			$td = &$tr->td[];
			$td->span[] = "{$row['Data_length']}/{$row['Data_free']}";
			$td->span[] = "{$row['Index_length']}/{$row['Avg_row_length']}";
			

			$td = &$tr->td[];
			$td->span[] = $row['Create_time'] ?? '-';
			$td->span[] = $row['Update_time'] ?? '-';

			$td = &$tr->td[];
			$td->span[] = $row['Check_time'] ?? '-';
			$td->span[] = $row['Checksum'] ?? '-';

			$td = &$tr->td[];
			$td->span[] = $row['Create_options'] ?? '-';
			$td->span[] = $row['Auto_increment'] ?? '-';
		}, $this);

		

		$td = &$table->fieldset->td[];
		$td->span[] = 'Name';
		$td->span[] = 'Comment';
		$td['style'] = 'width:200px';

		$td = &$table->fieldset->td[];
		//$td->span[] = 'Collation';
		$td->span[] = 'Engine:Version';
		$td->span[] = 'Row format:Rows';

		$td = &$table->fieldset->td[];
		$td->span[] = 'Data length/Data free';
		$td->span[] = 'Index length/Avg row length';

		$td = &$table->fieldset->td[];
		$td->span[] = 'Create time';
		$td->span[] = 'Update time';

		$td = &$table->fieldset->td[];
		$td->span[] = 'Check time';
		$td->span[] = 'Checksum';

		$td = &$table->fieldset->td[];
		$td->span[] = 'Create options';
		$td->span[] = 'Auto increment';
		


		//$table->fieldset('Name', 'Engine/Ver');

		//print_r($fields);
		$table->footer($this->query('SHOW CREATE DATABASE ?a', $this->mysql_database)->value(1));
		$table->xml['class'] .= '-grid';
		
	}
	function get_tab(string $name)
	{
		$tabname = $this->url64_decode($name);
		$table = $this->app->main->table($this->query('SHOW FULL FIELDS FROM ?a', $tabname), function(array $row)
		{
			$tr = &$this->tbody->tr[];
			
			$tr->append('td')->append('a', [
				$row['Comment'] ? "{$row['Field']}({$row['Comment']})" : $row['Field'],
				'href' => '#'
			]);

			
			$tr->td[] = $row['Type'];
			$tr->td[] = $row['Collation'];
			$tr->td[] = $row['Null'];
			$tr->td[] = $row['Key'];
			$tr->td[] = $row['Default'];
			$tr->td[] = $row['Extra'];
			$tr->td[] = $row['Privileges'];


			//print_r( $row );
		});
		$table->title($tabname);
		
		$table->bar->xml['class'] = 'webapp-bar';
		$table->bar->xml['method'] = 'get';
		$table->bar->button('View Data', 'submit')['formaction'] = '?viewdata';
		$table->bar->button('Append Field');
		$table->bar->field('asd', 'text');
		
		$table->bar->button('vvvv');

		$table->bar->button('dwdawd');
		$table->bar->button('wdwdwdwd');

		$table->fieldset('Field', 'Type', 'Collation', 'Null', 'Key', 'Default', 'Extra', 'Privileges');

		

		$table->footer()->details('Create table')->append('code', [
			$this->query('SHOW CREATE TABLE ?a', $tabname)->value(1),
			'class' => 'webapp-codeblock'
		]);
		$table->xml['class'] = 'webapp-grid';
	}
	function get_viewdata(string $name, int $page = 1, int $rows = 40)
	{
		$tabname = $this->url64_decode($name);
		$datatab = $this->mysql->{$tabname};

		
		
		$table = $this->app->main->table($datatab->paging($page, $rows)->result($fields));
		$table->title($tabname);
		
		$table->bar->xml->fieldset->append('a', ['Insert', 'href' => '?editor/' . $name]);

		$table->fieldset(...$fields);

		

		

	}
	function form_field(string $tabname, webapp_html $node = NULL, string $action = NULL)
	{
		$form = new webapp_form($node, $action);
		foreach ($this->query('SHOW FULL FIELDS FROM ?a', $tabname) as $row)
		{
			
			$form->fieldset($row['Field']);
			$attr = [];
			if ($row['Null'] === 'NO')
			{
				$attr['required'] = NULL;
			}
			if ($row['Comment'])
			{
				$attr['placeholder'] = $row['Comment'];
			}
			preg_match('/(\w+)(?:\((\d+)\)(?:\s(\w+))?)?/', $row['Type'], $type);

			if (isset($type[2]) && is_numeric($type[2]))
			{
				$attr['maxlength'] = $type[2];
			}

			$form->field($row['Field'], match ($type[1])
			{
				'tinyint' => 'number',
				default => 'text'
			}, $attr);
			//rint_r($row);
		}
		
		

	}
	function get_editor(string $name)
	{
		$tabname = $this->url64_decode($name);
		$this->form_field($tabname, $this->app->main);
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

};
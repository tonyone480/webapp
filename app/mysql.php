<?php
require __DIR__ . '/../webapp_io_std.php';
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
		if ($this->init_admin_sign_in(new io)) return;
		[$this->mysql_host, $this->mysql_user, $this->mysql_password]
			= json_decode($this->request_cookie_decrypt('mysql_connectto') ?? 'NULL', TRUE)
				?? [$this['mysql_host'], $this['mysql_user'], $this['mysql_password']];
		$this->mysql_database = $this->request_cookie('mysql_database') ?? $this['mysql_database'];
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
				$node->append('a', [$db['Database'], 'href' => "?database/{$db['Database']}"]);
				if ($db['Database'] === $this->mysql_database)
				{
					$node = $node->append('ul');
					foreach ($this->query('SHOW TABLE STATUS') as $tab)
					{
						$node->append('li')->append('a', ["{$tab['Name']}:{$tab['Rows']}", 'href' => "?datatable/{$tab['Name']}"]);
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
	function get_home(string $charset = NULL)
	{
		if (is_string($charset))
		{
			$this->response_cookie('mysql_charset', $charset);
			$this->response_location($this->request_referer() ?? '?home');
			return;
		}
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
	function get_console()
	{
		$form = $this->app->main->form('?api/console');
		$form->fieldset('Create');
		$form->field('createdb', 'text');
		$form->button('Create Database', 'submit');
		
		$form->fieldset();
		$form->field('console', 'textarea');
		$form->fieldset();
		$form->button('Query', 'submit');
		$form->xml['style'] = 'width:600px';
		//$form->fieldset();
		//$form->field('uploadfile', 'file', ['multiple' => NULL]);
		
	}
	function get_database(string $database = NULL)
	{
		if (is_string($database))
		{
			$this->response_cookie('mysql_database', $database);
			$this->response_location('?database');
			return;
		}
		$table = $this->app->main->table($this->query('SHOW TABLE STATUS')->result($fields), function(array $tab)
		{
			$tr = &$this->tbody->tr[];
			$td = &$tr->td[];

			$td->append('span')->append('a', [$tab['Name'], 'href' => "?table/{$tab['Name']}"]);
			$td->span[] = $tab['Comment'];

			$td = &$tr->td[];
			//$td->span[] = $tab['Collation'];
			$td->span[] = "{$tab['Engine']}:{$tab['Version']}";
			$td->span[] = "{$tab['Row_format']}:{$tab['Rows']}";

			$td = &$tr->td[];
			$td->span[] = "{$tab['Data_length']}/{$tab['Data_free']}";
			$td->span[] = "{$tab['Index_length']}/{$tab['Avg_row_length']}";
			

			$td = &$tr->td[];
			$td->span[] = $tab['Create_time'] ?? '-';
			$td->span[] = $tab['Update_time'] ?? '-';

			$td = &$tr->td[];
			$td->span[] = $tab['Check_time'] ?? '-';
			$td->span[] = $tab['Checksum'] ?? '-';

			$td = &$tr->td[];
			$td->span[] = $tab['Create_options'] ?? '-';
			$td->span[] = $tab['Auto_increment'] ?? '-';
		});

		

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
	function get_datatable(string $name)
	{
		$table = $this->app->main->table($this->query('SHOW FULL FIELDS FROM ?a', $name), function(array $row)
		{
			$tr = &$this->tbody->tr[];
			$tr->td[] = $row['Field'];
			$tr->td[] = $row['Type'];
			$tr->td[] = $row['Collation'];
			$tr->td[] = $row['Null'];
			$tr->td[] = $row['Key'];
			$tr->td[] = $row['Default'];
			$tr->td[] = $row['Extra'];
			$tr->td[] = $row['Privileges'];
			$tr->td[] = $row['Comment'];


			//print_r( $row );
		});
		$table->fieldset('Field', 'Type', 'Collation', 'Null', 'Key', 'Default', 'Extra', 'Privileges', 'Comment');
		$table->footer()->details('Create Table')->append('code', $this->query('SHOW CREATE TABLE ?a', $name)->value(1));
		$table->xml['class'] = 'webapp-grid';
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
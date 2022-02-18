<?php
require __DIR__ . '/../webapp_io_std.php';
require __DIR__ . '/../webapp_nfas.php';
// class nfas extends webapp_echo_json
// {
// 	function post_admin()
// 	{
// 		if ($admin = webapp_echo_html::form_sign_in($this))
// 		{
// 			var_dump($admin);
// 			if ($this->admin($signature = $this->signature($admin['username'], $input['password'])))
// 						{
// 							$this->response_refresh(0);
// 							$this->response_cookie($this['admin_cookie'], $this->app['signature'] = $signature);
// 						}
// 						else
// 						{
// 							$this->app['errors'][] = 'Sign in failed';
// 						}
// 		}
		
// 	}
// }



new class extends webapp_nfas
{
	function __construct()
	{
		parent::__construct(new io);


		// if ($this->mysql->connect_errno || 1)
		// {

		// 	$a = ($this->mysql)('SHOW TABLES LIKE ?s', 'nfas')->array();
		// 	var_dump($a);


		// 	if ($this->admin === FALSE)
		// 	{
		// 		$this->app('webapp_echo_html')->title('Initialize NFAS');
		// 		$this->get_admin();
		// 	}
		// 	return;
		// }


		// if ($this->router === $this && in_array($this->method, ['get_open', 'get_download']) === FALSE)
		// {
		// 	$this->app('webapp_echo_html')->title('NFAS');
		// }
	}
	function get_home(string $hash = NULL, int $page = 1)
	{
		if ($node = $this->node($hash))
		{
			$this->app('webapp_echo_html')->title($node['name']);
			$ul = $this->app->main->append('ul');
			foreach ($this->nodeitem($node['hash'], $page) as $item)
			{
				$ul->append('li')->append('a', [$item['name'], 'href' => "?home/{$item['hash']}"]);
			}

			return;
		}
		return 404;
	}
	function get_node(string $hash = NULL, int $page = 1)
	{
		if ($node = $this->node($hash))
		{
			$this->app('webapp_echo_json', []);
			foreach ($this->iterator([$node], $this->nodeitem($node['hash'], $page)) as $item)
			{
				$this->app[] = [
					'hash' => $item['hash'],
					'size' => $item['size'],
					'time' => $item['time'],
					'type' => $item['type'],
					'name' => $item['name']
				];
			}
			return;
		}
		return 404;
	}
	function get_echo(string $hash)
	{
		if ($file = $this->file($hash))
		{
			print_r($file);
			return;
		}
		return 404;
	}
	
	function get_admin()
	{
		webapp_echo_html::form_sign_in($this->app->main, '?nfas/admin');
	}
	function get_open(string $hash)
	{
		$this->echo($hash);
	}
};
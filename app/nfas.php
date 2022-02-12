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


		print_r( $this->delete('NNIIIILNLLNH') );
		return;
		
		if ($this->mysql->connect_errno || 1)
		{

			$a = ($this->mysql)('SHOW TABLES LIKE ?s', 'nfas')->array();
			var_dump($a);


			if ($this->admin === FALSE)
			{
				$this->app('webapp_echo_html')->title('Initialize NFAS');
				$this->get_admin();
			}
			return;
		}


		if ($this->router === $this && in_array($this->method, ['get_open', 'get_download']) === FALSE)
		{
			$this->app('webapp_echo_html')->title('NFAS');
		}
	}
	function get_home()
	{

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
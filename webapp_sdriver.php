<?php
class webapp_sdriver extends webapp
{
	function sync():webapp_client_http
	{
		return (new webapp_client_http($this['app_syncurl'], ['autoretry' => 2]))->headers([
			'Authorization' => 'Bearer ' . $this->signature($this['admin_username'], $this['admin_password'], (string)$this['app_sid'])
		]);
	}
	function get(string $router):string|webapp_xml
	{
		return $this->sync->goto("{$this->sync->path}?{$router}")->content();
	}
	function post(string $router, array $data = []):string|webapp_xml
	{
		return $this->sync->goto("{$this->sync->path}?{$router}", [
			'method' => 'POST',
			'type' => 'application/json',
			'data' => $data
		])->content();
	}
	function delete(string $router):string|webapp_xml
	{
		return $this->sync->goto("{$this->sync->path}?{$router}", ['method' => 'POST'])->content();
	}

	function get_sync(string $method)
	{
		if ($this->authorization)
		{
			if (method_exists($this, $method))
			{
				if ($this->{$method}($this->request_content()))
				{
					$this->echo('SUCCESS');
					return 200;
				}
				$this->echo('FAILURE');
				return 500;
			}
			return 404;
		}
		return 401;
	}
	function post_sync()
	{
		if ($this->authorization)
		{


			var_dump($this->authorization);
		}
		
	}
	function pull(string $router, int $size = 1000):iterable
	{
		for ($max = 1, $index = 0; $max > $index++;)
		{
			if (is_object($xml = $this->get("{$router},page:{$index},size:{$size}")))
			{
				$max = (int)$xml['max'];
				foreach ($xml->children() as $children)
				{
					yield $children;
				}
			}
		}
	}


	function account(string $signature = NULL):array
	{
		return is_object($account = $this->get($signature === NULL ? 'register' : "account/{$signature}")) && isset($account->account)
			? [...$account->account->getattr(), 'favorites' => strlen($account->account) ? str_split($account->account, 12) : []] : [];
	}
	function play(string $resource, string $signature):array
	{
		return is_object($play = $this->get("play/{$resource}{$signature}")) && isset($play->play) ? $play->play->getattr() : [];
	}
	function favorite(string $resource, string $signature, bool $append = TRUE):array
	{
		return is_object($favorite = $this->{$append ? 'post' : 'delete'}("favorite/{$resource}{$signature}")) && isset($favorite->favorite)
			? [...$favorite->favorite->getattr(), 'favorites' => strlen($favorite->favorite) ? str_split($favorite->favorite, 12) : []] : [];
	}
	function payment(string $signature, array $data)
	{
		print_r( $this->post("payment/{$signature}", $data) );
	}

	
}

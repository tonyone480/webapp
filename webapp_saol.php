<?php
class webapp_saol extends webapp
{
	function slave():webapp_client_http
	{
		return (new webapp_client_http($this['app_master']))->headers([
			'Authorization' => 'Digest ' . $this->signature($this['admin_username'], $this['admin_password'], (string)$this['app_sid'])
		]);
	}
	function get(string $router):string|array|webapp_xml
	{
		return $this->slave->goto("{$this->slave->path}?{$router}")->content();
	}
	function post(string $router, array $data):string|array|webapp_xml
	{
		return $this->slave->goto("{$this->slave->path}?{$router}", [
			'method' => 'POST',
			'type' => 'application/json',
			'data' => $data
		])->content();
	}
	function delete(string $router):bool
	{
		return is_object($this->slave->goto("{$this->slave->path}?{$router}", ['method' => 'POST'])->content());
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

}

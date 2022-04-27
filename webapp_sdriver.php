<?php
class webapp_sdriver extends webapp
{
	function sync():webapp_client_http
	{
		return (new webapp_client_http($this['app_syncurl'], ['autoretry' => 2]))->headers([
			'Authorization' => 'Digest ' . $this->signature($this['admin_username'], $this['admin_password'], (string)$this['app_sid'])
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
		return is_object($favorite = $this->{$append ? 'get' : 'delete'}("favorite/{$resource}{$signature}")) && isset($favorite->favorite)
			? [...$favorite->favorite->getattr(), 'favorites' => strlen($favorite->favorite) ? str_split($favorite->favorite, 12) : []] : [];
	}

}

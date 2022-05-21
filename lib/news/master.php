<?php
require 'admin.php';
class news_master extends webapp
{
	function clientip():string
	{
		return $this->request_header('X-Client-IP') ?? $this->request_ip();
	}
	function clientiphex():string
	{
		return $this->iphex($this->clientip);
	}
	function sync():webapp_client_http
	{
		return $this->sync[$this->site] ??= (new webapp_client_http("http://{$this['app_site'][$this->site]}/", ['autoretry' => 2]))->headers([
			'Authorization' => 'Bearer ' . $this->signature($this['admin_username'], $this['admin_password']),
			'X-Client-IP' => $this->clientip
		]);
	}
	function call(string $method, ...$params):bool|string|array|webapp_xml
	{
		foreach ($params as &$value)
		{
			if ($value instanceof webapp_xml)
			{
				$value = $value->asXML();
			}
		}
		$sync = $this->sync();
		return is_string($content = $sync->goto("{$sync->path}?sync/{$method}", [
			'method' => 'POST',
			'type' => 'application/json',
			'data' => $params
		])->content()) && preg_match('/^(SUCCESS|OK)/i', $content) ? TRUE : $content;
	}
	function pull(string $router):iterable
	{
		$sync = $this->sync();
		$max = NULL;
		do
		{
			if (is_object($xml = $sync->goto("{$sync->path}?pull/{$router}")->content()))
			{
				$max ??= intval($xml['max']);
				foreach ($xml->children() as $children)
				{
					yield $children;
				}
			}
		} while (--$max > 0);
	}
	function get_pull()
	{
		if (PHP_SAPI !== 'cli' || $this->request_ip() !== '127.0.0.1')
		{
			$this->echo('Please run at the local command line');
			return;
		}
		for (;;)
		{
			foreach ($this['app_site'] as $id => $ip)
			{
				$this->site = $id;
				var_dump($ip);
				foreach ($this->pull('incr-res') as $res)
				{
					print_r($res);
				}
				
			}
			sleep(5);
		}
	}
	function maskfile(string $src, string $dst):bool
	{
		$bin = random_bytes(8);
		$key = array_map(ord(...), str_split($bin));
		$buffer = file_get_contents($src);
		$length = strlen($buffer);
		for ($i = 0; $i < $length; ++$i)
		{
			$buffer[$i] = chr(ord($buffer[$i]) ^ $key[$i % 8]);
		}
		return file_put_contents($dst, $bin . $buffer) === $length + 8;
	}
	function randhash(bool $care = FALSE):string
	{
		return $this->hash($this->random(16), $care);
	}
	function shorthash(int|string ...$contents):string
	{
		return $this->hash($this->site . $this->time . join($contents), TRUE);
	}

	//-----------------------------------------------------------------------------------------------------


	function get_test()
	{

		foreach ($this->pull(0, 'incr-res') as $res)
		{
			print_r($res);
		}
		
		//var_dump( $this->call(0, 'aa', [webapp::xml('<xml><a>222</a></xml>')]) );
	}
	function get_home()
	{
		$this->app->xml->comment(file_get_contents(__DIR__.'/master.txt'));
	}
	//同步资源
	function get_pushdata(string $batch)
	{
		if (preg_match('/^\d{8,10}$/', $batch)
			&& is_object($xml = $this->open(sprintf($this['saol_resources'], $batch))->content('application/xml'))) {
			$count = 0;
			foreach ($xml as $res)
			{
				if ($this->mysql->resources->insert($data = $res->getattr()))
				{
					++$count;
					$this->app->xml->append('resource', $data);
				}
			}
			$this->app->xml['count'] = $count;
		}
	}
	//广告
	function ad_xml(array $ad):webapp_xml
	{
		return $this->xml->append('ad', [
			'hash' => $ad['hash'],
			'seat' => $ad['seat'],
			'timestart' => $ad['timestart'],
			'timeend' => $ad['timeend'],
			'weekset' => $ad['weekset'],
			'count' => $ad['count'],
			'click' => $ad['click'],
			'view' => $ad['view'],
			'name' => $ad['name'],
			'goto' => $ad['goto']
		]);
	}
	function get_ads()
	{
		foreach ($this->mysql->ads('WHERE site=?i AND seat', $this->site) as $ad)
		{
			$this->ad_xml($ad);
		}
	}




	//资源
	function resource_xml(array $resource):webapp_xml
	{
		$node = $this->xml->append('resource', [
			'hash' => $resource['hash'],
			'time' => $resource['time'],
			'batch' => $resource['batch'],
			'require' => $resource['require'],
			'duration' => $resource['duration'],
			'view' => $resource['view'],
			'like' => $resource['like'],
			'tags' => $resource['tags'],
			'actors' => $resource['actors'],
			//'name' => $resource['name']
		]);
		$node->cdata($resource['name']);
		return $node;
	}
	function get_resources(string $tag = NULL, int $page = 1, int $size = 1000)
	{
		//$cond = ['WHERE FIND_IN_SET(?i,sites) AND checked=1', $this->site];
		$cond = ['WHERE FIND_IN_SET(?i,sites)', $this->site];
		if ($tag)
		{
			$cond[0] .= ' AND FIND_IN_SET(?s,tags)';
			$cond[] = $tag;
		}
		$resources = $this->mysql->resources(...$cond)->paging($page, $size);
		$this->app->xml->setattr($resources->paging);
		foreach ($resources as $resource)
		{
			$this->resource_xml($resource);
		}
	}
	//标签
	function tag_xml(array $tag):webapp_xml
	{
		return $this->xml->append('tag', [
			'hash' => $tag['hash'],
			'level' => $tag['level'],
			'count' => $tag['count'],
			'click' => $tag['click'],
			'name' => $tag['name']
		]);
	}
	function get_tags(string $type = NULL, int $page = 1, int $size = 1000)
	{
		$tags = ($type ? $this->mysql->tags('WHERE type=?i', $type) : $this->mysql->tags)->paging($page, $size);
		$this->app->xml->setattr($tags->paging);
		foreach ($tags as $tag)
		{
			$this->tag_xml($tag);
		}
	}





	//账号操作
	// function uid(string $signature, &$uid):bool
	// {
	// 	return is_string($uid = $this->authorize($signature, fn(string $uid):?string
	// 		=> strlen($uid) === 10 && trim($uid, webapp::key) === '' ? $uid : NULL));
	// }
	function account(string $signature, &$account):bool
	{
		return boolval($account = $this->authorize($signature, fn(string $uid, string $pwd):array
			=> $this->mysql->accounts('WHERE uid=?s AND site=?i AND pwd=?s LIMIT 1', $uid, $this->site, $pwd)->array()));
	}
	function account_xml(array $account, string $signature = NULL):webapp_xml
	{
		$node = $this->xml->append('account', [
			'uid' => $account['uid'],
			'signature' => $signature ?? $this->signature($account['uid'], $account['pwd']),
			'expire' => $account['expire'],
			'balance' => $account['balance'],
			'lasttime' => $account['lasttime'],
			'lastip' => $account['lastip'],
			'device' => $account['device'],
			'phone' => $account['phone'],
			'name' => $account['name']
		]);
		$node->append('favorite')->cdata($account['favorite']);
		$node->append('history')->cdata($account['history']);
		return $node;
	}
	function get_register()
	{
		//这里也许要做频率限制
		$rand = $this->random(16);
		if ($this->mysql->accounts->insert($account = [
			'uid' => $this->hash($rand, TRUE),
			'site' => $this->site,
			'time' => $this->time,
			'expire' => $this->time,
			'balance' => 0,
			'lasttime' => $this->time,
			'lastip' => $this->iphex('127.0.0.1'),
			'device' => 'pc',
			'phone' => '',
			'pwd' => random_int(100000, 999999),
			'name' => $this->hash($rand),
			'favorite' => '',
			'history' => ''])) {
			$this->account_xml($account);
		}
	}
	function get_signature(string $uid)
	{
		if ($account = $this->mysql->accounts('WHERE uid=?s AND site=?i', $uid, $this->site)->array())
		{
			$this->account_xml($account);
		}
	}
	function get_account(string $signature)
	{
		if ($this->account($signature, $account))
		{
			$this->account_xml($account, $signature);
		}
	}
	function post_report(string $signature)
	{
		if ($this->account($signature, $account)
			&& is_string($describe = $this->request_content())
			&& strlen($describe) > 2
			&& strlen($describe) < 128
			&& $this->mysql->reports->insert($report = [
				'hash' => $this->randhash(TRUE),
				'site' => $this->site,
				'time' => $this->time,
				'ip' => $this->clientiphex(),
				'promise' => 'waiting',
				'account' => $account['uid'],
				'describe' => $describe])) {
			$this->xml->append('report', [
				'hash' => $report['hash'],
				'time' => $report['time'],
				'promise' => $report['promise']
			])->cdata($describe);
		}
	}
	function get_play(string $resource_signature)
	{
		if ($this->account($signature = substr($resource_signature, 12), $account))
		{
			$require = $this->mysql->resources('WHERE hash=?s LIMIT 1', $resource = substr($resource_signature, 0, 12))->array()['require'] ?? 0;
			if ($require > 0 && $this->mysql->accounts('WHERE uid=?s LIMIT 1', $account['uid'])->update('balance=balance-?i', $require) === 1)
			{
				$this->app->xml->append('play', ['signature' => $signature, 'resource' => $resource, 'balance' => $account['balance'] - $require]);
			}
		}
	}
	function get_favorite(string $resource_signature)
	{
		$resource = substr($resource_signature, 0, 12);
		if ($this->account($signature = substr($resource_signature, 12), $account))
		{
			$favorite = $account['favorite'] ? str_split(substr($account['favorite'], -384), 12) : [];
			$offset = array_search($resource, $favorite, TRUE);
			if ($offset === FALSE)
			{
				$favorite[] = $resource;
				$value = 'favorite=favorite+1';
			}
			else
			{
				array_splice($favorite, $offset, 1);
				$value = 'favorite=favorite-1';
			}
			if ($this->mysql->accounts('WHERE uid=?s LIMIT 1', $account['uid'])->update('favorite=?s', $favorite = join($favorite)))
			{
				$this->mysql->resources('WHERE hash=?s LIMIT 1', $resource)->update($value);
				$this->app->xml->append('favorite', ['signature' => $signature])->cdata($favorite);
			}
		}
	}
	function get_history(string $resource_signature)
	{
		$resource = substr($resource_signature, 0, 12);
		if ($this->account($signature = substr($resource_signature, 12), $account)
			&& $this->mysql->accounts('WHERE uid=?s LIMIT 1', $account['uid'])->update('history=?s', $history = $account['history']
				? join(array_unique(str_split(substr($account['history'] . $resource, -384), 12)))
				: $resource)) {
			$this->mysql->resources('WHERE hash=?s LIMIT 1', $resource)->update('view=view+1');
			$this->app->xml->append('history', ['signature' => $signature])->cdata($history);
		}
	}
	//支付
	function post_payment(string $signature)
	{
		if ($this->account($signature, $account))
		{
			//print_r($account);
			print_r($this->request_content());
		}
		// if (is_array($input = $this->request_content())
		// 	&& isset($input['fee'], $input['account'])) {
		// 	print_r($input);
		// }
	}
	function get_payments(string $signature)
	{
		if ($this->account($signature, $account))
		{
			$payments = $this->mysql->payments('WHERE site=?i AND account=?s', $this->site, $account['uid']);
			foreach ($payments as $pay)
			{
				$this->app->xml->append('payment', [
					'hash' => $pay['hash'],
					'time' => $pay['time'],
					'fee' => $pay['fee'],
					'paytime' => $pay['paytime']

				]);
			}
			//print_r($account);
		}
	}
};
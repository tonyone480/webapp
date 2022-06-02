<?php
require 'admin.php';
class interfaces extends webapp
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
	function get_sync()
	{
		if (PHP_SAPI !== 'cli' || $this->request_ip() !== '127.0.0.1')
		{
			$this->echo('Please run at the local command line');
			return;
		}
		$ffmpeg = static::lib('ffmpeg/interface.php');
		foreach ($this->mysql->resources('WHERE sync="waiting" AND time>1654082240') as $resource)
		{
			$cut = $ffmpeg("{$this['app_respredir']}/{$resource['hash']}");
			if ($cut->m3u8($outdir = "{$this['app_resoutdir']}/{$resource['hash']}"))
			{
				$this->maskfile("{$outdir}/play.m3u8", "{$outdir}/play");
				if ($cut->jpeg("{$outdir}/cover.jpg"))
				{
					$this->maskfile("{$outdir}/cover.jpg", "{$outdir}/cover");
				}
				echo exec("xcopy \"{$outdir}/*\" \"{$this['app_resdstdir']}/{$resource['hash']}/\" /E /C /I /F /Y", $output, $code), ":{$code}\n";
			}
		}
	}
	function get_pull()
	{
		if (PHP_SAPI !== 'cli' || $this->request_ip() !== '127.0.0.1')
		{
			$this->echo('Please run at the local command line');
			return;
		}
		foreach ($this['app_site'] as $site => $ip)
		{
			$this->site = $site;
			echo "\n-------- PULL TAGS --------\n";
			foreach ($this->pull('incr-tag') as $tag)
			{
				echo $tag['hash'], ' - ', 
					$this->mysql->tags('WHERE hash=?s LIMIT 1', $tag['hash'])
						->update('`click`=`click`+?i', $tag['click']) ? 'OK' : 'NO',
					"\n";
			}

			echo "\n-------- PULL RESOURCES --------\n";
			foreach ($this->pull('incr-res') as $resource)
			{
				echo $resource['hash'], ' - ', 
					$this->mysql->resources('WHERE FIND_IN_SET(?s,sites) AND hash=?s LIMIT 1', $site, $resource['hash'])
						->update('`favorite`=`favorite`+?i,`view`=`view`+?i,`like`=`like`+?i',
						$resource['favorite'], $resource['view'], $resource['like']) ? 'OK' : 'NO',
					"\n";
			}

			echo "\n-------- PULL AD --------\n";
			foreach ($this->pull('incr-ad') as $ad)
			{
				echo $ad['hash'], ' - ', 
					$this->mysql->ads('WHERE site=?i AND hash=?s LIMIT 1', $site, $ad['hash'])
						->update('`click`=`click`+?i,`view`=`view`+?i', $ad['click'], $ad['view']) ? 'OK' : 'NO',
					"\n";
			}

			echo "\n-------- PULL COMMENTS --------\n";
			foreach ($this->pull('comments') as $comment)
			{
				echo $comment['hash'], ' - ', 
					$this->mysql->comments->insert($comment->getattr() + ['site' => $site, 'content' => (string)$comment]) ? 'OK' : 'NO',
					"\n";

			}
			break;
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
	function runstatus():array
	{
		$status = [
			'os_http_connected' => intval(shell_exec('netstat -ano | find ":80" /c'))
		];
		foreach ($this->mysql('SELECT * FROM performance_schema.GLOBAL_STATUS WHERE VARIABLE_NAME IN(?S)', [
			'Aborted_clients',
			'Aborted_connects',//接到MySQL服务器失败的次数
			'Queries',//总查询
			'Slow_queries',//慢查询
			'Max_used_connections',//高峰连接数量
			'Max_used_connections_time',//高峰连接时间
			'Threads_cached',
			'Threads_connected',//打开的连接数
			'Threads_created',//创建过的线程数
			'Threads_running',//激活的连接数
			'Uptime',//已经运行的时长
		]) as $stat) {
			$status['mysql_' . strtolower($stat['VARIABLE_NAME'])] = $stat['VARIABLE_VALUE'];
		}
		return $status;
	}

	//-----------------------------------------------------------------------------------------------------


	function get_test()
	{
		print_r( $this->mysql->resources->array() );
	}
	function get_home()
	{
		$this->app->xml->comment(file_get_contents(__DIR__.'/interfaces.txt'));
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
	function form_resourceupload($ctx):webapp_form
	{
		$form = new webapp_form($ctx, "{$this['app_resdomain']}?resourceupload");
		$form->xml['onsubmit'] = 'return upres(this)';
		$form->progress()->setattr(['style' => 'width:100%']);
		$form->fieldset('资源文件');
		$form->field('resource', 'file', ['accept' => 'video/mp4', 'required' => NULL]);
		$form->button('Cancel', 'button', ['onclick' => 'xhr.abort()']);
		$form->fieldset('name / actors');
		$form->field('name', 'text', ['value' => '0000', 'style' => 'width:42rem', 'required' => NULL]);
		$form->field('actors', 'text', ['value' => '素人', 'required' => NULL]);
		$form->fieldset('tags');
		$tags = $this->webapp->mysql->tags('ORDER BY level ASC,click DESC,count DESC')->column('name', 'hash');
		$form->field('tags', 'checkbox', ['options' => $tags], fn($v,$i)=>$i?join(',',$v):explode(',',$v))['class'] = 'restag';
		$form->fieldset('require(下架：-2、会员：-1、免费：0、金币)');
		$form->field('require', 'number', ['value' => 0, 'min' => -1, 'required' => NULL]);
		$form->fieldset();
		$form->button('Upload Resource', 'submit');
		return $form;
	}
	function resource_create(array $data):bool
	{
		return $this->mysql->resources->insert([
			'hash' => $data['hash'],
			'time' => $this->time,
			'duration' => $data['duration'],
			'sync' => 'waiting',
			'site' => $this->site,
			'data' => json_encode([$this->site => [
				'require' => intval($data['require']),
				'favorite' => 0,
				'view' => 0,
				'like' => 0,
				'name' => $data['name']
			]], JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE),
			'tags' => $data['tags'],
			'actors' => $data['actors']
		]);
	}
	function resource_delete(string $hash):bool
	{
		if ($resource = $this->mysql->resources('WHERE FIND_IN_SET(?i,site) AND hash=?s LIMIT 1', $this->site, $hash)->array())
		{
			$site = explode(',', $resource['site']);
			array_splice($site, array_search(1, $site), 1);
			return $this->mysql->resources('WHERE hash=?s LIMIT 1', $hash)->update('site=?s,data=JSON_REMOVE(data,\'$."?i"\')', join(',', $site), $this->site);
		}
		return TRUE;
	}
	function resource_update(string $hash, array $data):bool
	{
		$update = ['data=JSON_SET(data,\'$."?i".require\',?i,\'$."?i".name\',?s)',
			$this->site, $data['require'] ?? 0, $this->site, $data['name'] ?? ''];
		if ($this->admin[2])
		{
			$update[0] .= ',tags=?s,actors=?s';
			array_push($update, $data['tags'], $data['actors']);
		}
		return $this->mysql->resources('WHERE FIND_IN_SET(?i,site) AND hash=?s LIMIT 1', $this->site, $hash)->update(...$update);
	}

	function resource_get(string|array $resource):array
	{
		if (is_string($resource))
		{
			$resource = $this->mysql->resources('WHERE FIND_IN_SET(?i,site) AND hash=?s LIMIT 1', $this->site, $resource)->array();
		}
		$resource += json_decode($resource['data'], TRUE)[$this->site] ?? [
			'require' => 0,
			'favorite' => 0,
			'view' => 0,
			'like' => 0,
			'name' => ''];
		return $resource;
	}

	function resource_assign(array $resource, int $site, array $value = []):bool
	{
		$sites = $resource['site'] ? explode(',', $resource['site']) : [];
		$value += json_decode($resource['data'], TRUE)[$site] ?? [];
		$sites[] = $site;
		return $this->mysql->resources('WHERE hash=?s LIMIT 1', $resource['hash'])
			->update('site=?s,data=JSON_SET(data,\'$."?i"\',JSON_OBJECT("require",?i,"favorite",0,"view",0,"like",0,"name",?s))',
			join(',', array_unique($sites)), $site, intval($value['require'] ?? 0), $value['name'] ?? '');
	}
	function post_resourceupload()
	{
		$resource = [];
		$uploadfile = $this->request_uploadedfile('resource', 1)[0] ?? [];
		$this->app('webapp_echo_json', [
			'resource' => &$resource,
			'uploadfile' => $uploadfile
		]);
		if (empty($uploadfile) || $uploadfile['mime'] !== 'video/mp4')
		{
			$this->app['errors'][] = '请上传有效资源！';
			return;
		}
		if ($this->form_resourceupload($this)->fetch($resource) === FALSE)
		{
			return;
		}
		if ($data = $this->mysql->resources('WHERE hash=?s LIMIT 1', $uploadfile['hash'])->array())
		{
			if ($this->resource_assign($data, $this->site, $resource))
			{
				if ($data['sync'] === 'finished')
				{
					$this->call('saveRes', $this->resource_xml($this->resource_get($data['hash'])));
					$this->app['goto'] = "?admin/resources,search:{$resource['hash']}";
				}
				else
				{
					$this->app['goto'] = '?admin/resources,sync:waiting';
				}
			}
			else
			{
				$this->app['errors'][] = '资源分配更新失败！';
			}
			return;
		}
		if ($this->form_resourceupload($this)->fetch($resource)
			&& move_uploaded_file($uploadfile['file'], $filename = "{$this['app_respredir']}/{$uploadfile['hash']}")
			&& $this->resource_create($resource + [
				'hash' => $uploadfile['hash'],
				'duration' => intval(static::lib('ffmpeg/interface.php')($filename)->duration)])) {
			$this->app['goto'] = '?admin/resources,sync:waiting';
			return;
		}
		isset($filename) && is_file($filename) && unlink($filename);
	}
	function resource_xml(array $resource):webapp_xml
	{
		$data = json_decode($resource['data'], TRUE)[$this->site] ?? [];
		$node = $this->xml->append('resource', [
			'hash' => $resource['hash'],
			'time' => $resource['time'],
			'duration' => $resource['duration'],
			'tags' => $resource['tags'],
			'actors' => $resource['actors'],
			'require' => $data['require'] ?? 0,
			'favorite' => $data['favorite'] ?? 0,
			'view' => $data['view'] ?? 0,
			'like' => $data['like'] ?? 0
		]);
		$node->cdata($data['name']);
		return $node;
	}
	function get_resources(string $tag = NULL, int $page = 1, int $size = 1000)
	{
		$cond = ['WHERE FIND_IN_SET(?i,site) AND sync="finished"', $this->site];
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
			=> $this->mysql->accounts('WHERE site=?i AND uid=?s AND pwd=?s LIMIT 1', $this->site, $uid, $pwd)->array()));
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
		$device = $this->request_content();
		if ($this->mysql->accounts->insert($account = [
			'uid' => $this->hash($rand, TRUE),
			'site' => $this->site,
			'time' => $this->time,
			'expire' => $this->time,
			'balance' => 0,
			'lasttime' => $this->time,
			'lastip' => $this->clientiphex(),
			'device' => match (1)
			{
				preg_match('/windows phone/i', $device) => 'wp',
				preg_match('/pad/i', $device) => 'pad',
				preg_match('/iphone/i', $device) => 'ios',
				preg_match('/android/i', $device) => 'android',
				default => 'pc'
			},
			'face' => 0,
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
	
	function bill(string $uid, int $fee, string $describe, &$bill):bool
	{
		return $this->mysql->sync(function() use($uid, $fee, $describe, &$bill)
		{
			return $this->mysql->accounts('WHERE site=?i AND uid=?s', $this->site, $uid)
						->update('balance=balance???i', $fee > 0 ? '+' : '-', abs($fee)) > 0
				&& $this->mysql->bills->insert($bill = [
					'hash' => $this->randhash(TRUE),
					'site' => $this->site,
					'time' => $this->time,
					'fee' => $fee,
					'account' => $uid,
					'describe' => $describe
				]);
		});
	}
	function bill_xml(array $bill)
	{
		$this->xml->append('bill', [
			'hash' => $bill['hash'],
			'time' => $bill['time'],
			'fee' => $bill['fee'],
			'account' => $bill['account'],
		])->cdata($bill['describe']);
	}
	function post_bill(string $signature)
	{
		if ($this->account($signature, $account)
			&& is_array($bill = $this->request_content())
			&& isset($bill['fee'], $bill['describe'])
			&& is_string($bill['describe'])
			&& $this->bill($account['uid'], intval($bill['fee']), $bill['describe'], $bill)) {
			$this->bill_xml($bill);
		}
	}
	function get_bills(string $signature, int $page = 1)
	{
		if ($this->account($signature, $account))
		{
			$bills = $this->mysql->bills('WHERE site=?i AND account=?s', $this->site, $account['uid'])->paging($page);
			$this->xml->setattr($bills->paging);
			foreach ($bills as $bill)
			{
				$this->bill_xml($bill);
			}
		}
	}
	function get_play(string $resource_signature)
	{
		if ($this->account($signature = substr($resource_signature, 12), $account))
		{
			$require = $this->mysql->resources('WHERE hash=?s LIMIT 1', $resource = substr($resource_signature, 0, 12))->array()['require'] ?? 0;
			if ($require > 0 && $this->bill($account['uid'], -$require, "付费播放 {$resource}", $bill))
			{
				$this->xml->append('play', ['signature' => $signature, 'resource' => $resource, 'balance' => $account['balance'] - $require]);
				$this->bill_xml($bill);
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
				$this->xml->append('favorite', ['signature' => $signature])->cdata($favorite);
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
			$this->xml->append('history', ['signature' => $signature])->cdata($history);
		}
	}
	//评论
	function get_comments(string $resource)
	{
		$comments = $this->mysql->comments('WHERE site=?i AND resource=?s ORDER BY time DESC LIMIT 200', $this->site, $resource)->all();
		$accounts = $this->mysql->accounts('WHERE uid IN(?S)', array_unique(array_column($comments, 'account')))->column('face', 'name', 'uid');
		$unknown = ['face' => 0, 'name' => 'unknown'];
		foreach ($comments as $comment)
		{
			$account = $accounts[$comment['account']] ?? $unknown;
			$this->xml->append('comment', [
				'hash' => $comment['hash'],
				'time' => $comment['time'],
				//'account' => $comment['account'],
				'face' => $account['face'],
				'name' => $account['name']
			])->cdata($comment['content']);
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
<?php
class webapp_router_admin extends webapp_echo_html
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		if (!$webapp->admin)
		{
			if (str_ends_with($webapp->method, 't_home'))
			{
				if ($webapp->method === 'post_home'
					&& ($input = webapp_echo_html::form_sign_in($webapp))
					&& $webapp->admin($signature = $webapp->signature($input['username'], $input['password']))) {
					$webapp->response_cookie($webapp['admin_cookie'], $signature);
					$webapp->response_refresh(0);
				}
				else
				{
					webapp_echo_html::form_sign_in($this->main);
				}
				return $webapp->response_status(200);
			}
			$this->main->setattr(['Unauthorized', 'style' => 'font-size:2rem']);
			return $webapp->response_status(401);
		}
		$this->nav([
			['Admin', '?admin'],
			['Accounts', '?admin/accounts'],
			['Payments', '?admin/payments'],
			['Ads', '?admin/ads'],
		]);
	}
	function form_admin($ctx)
	{
		$form = new webapp_form($ctx);

		$form->fieldset('站点');
		$form->field('site', 'number');

		$form->fieldset('站点名称');
		$form->field('name', 'text');

		$form->fieldset('站点账号');
		$form->field('uid', 'number');

		$form->fieldset('站点密码');
		$form->field('pwd', 'text');

		$form->fieldset();
		$form->button('提交', 'submit');
		return $form();
	}
	function post_home()
	{
		var_dump($this->form_admin($this->webapp) );
	}
	function get_home()
	{
		$this->form_admin($this->main);
	}
	function get_accounts(string $uid = NULL, int $page = 1)
	{

		$accounts = $this->webapp->mysql->accounts
		->select('uid,site,time,expire,balance,lasttime,device,name,gender')
		->paging($page)
		->result($fields);

		
		$table = $this->main->table($accounts, function(array $acc)
		{
			$this->row();
			$this->cell([['a', $acc['uid'], 'href' => "?admin/accounts,uid:{$acc['uid']}"]], 'iter');
			$this->cell($acc['site']);
			$this->cell(date('Y-m-d H:i:s', $acc['time']));
			$this->cell(date('Y-m-d H:i:s', $acc['expire']));
			$this->cell($acc['balance']);
			$this->cell(date('Y-m-d H:i:s', $acc['lasttime']));
			$this->cell($acc['device']);
			$this->cell($acc['name']);
			$this->cell($acc['gender']);






			//$this->cells(...array_slice($acc, 1));
		});

		$table->fieldset(...$fields);
		$table->paging('?admin/accounts,page:');
		// print_r($table);
	}
	function get_payments()
	{
	}
	function form_ads($ctx)
	{
		$form = new webapp_form($ctx);

		$form->fieldset('站点');
		$form->field('site', 'select', ['options' => $this->webapp['app_site']]);

		$form->fieldset('图片');
		$form->field('pic', 'file', ['accept' => 'image/*']);

		$form->fieldset('名称跳转');
		$form->field('name', 'text');
		$form->field('goto', 'text');

		$form->fieldset('有效时间段');
		$form->field('timestart', 'date', ['value' => date('Y-m-d')]);
		$form->field('timeend', 'date');

		$form->fieldset('每周几显示');
		$form->field('weekset', 'select');

		$form->fieldset();
		$form->button('提交', 'submit');
		return $form();
	}
	function post_ads()
	{
		$form = $this->form_ads($this->webapp);
		print_r($form);

		print_r($this->webapp->request_uploadedfile('pic'));
	}
	function get_ads()
	{
		$this->form_ads($this->main);
	}
}
class news_master extends webapp
{
	function sync(int $site, string $method, array $context = []):bool|iterable
	{
		$sync = new webapp_client_http("http://{$this['app_site'][$site]}/", ['autoretry' => 2]);
		$sync->headers([
			'Authorization' => 'Bearer ' . $this->signature($this['admin_username'], $this['admin_password'], (string)$site)
		]);
		if ($context)
		{
			$sync->goto("{$sync->path}?sync/{$method}", [
				'type' => 'application/json',
				'data' => $context
			]);
			return $sync->content() === 'SUCCESS';
		}


		while (is_object($xml = $sync->goto("{$sync->path}?{$method}")->content()) && $xml->count())
		{
			foreach ($xml->children() as $children)
			{
				yield $children;
			}
		}
		// for ($max = 1, $index = 0; $max > $index++;)
		// {
		// 	if (is_object($xml = $sync->goto("{$sync->path}?{$method}")->content()))
		// 	{
		// 		$max = (int)$xml['max'];
		// 		foreach ($xml->children() as $children)
		// 		{
		// 			yield $children;
		// 		}
		// 	}
		// }
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

	function shorthash(int|string ...$contents):string
	{
		return $this->hash($this->site . $this->time . join($contents), TRUE);
	}


	function get_test()
	{
		//var_dump( $this->sync(0, 'delItem', ['tag', 'asdasdawf']) );
		// foreach ($this->sync(0, 'incr') as $incr)
		// {
		// 	var_dump((string)$incr['hash']);
		// }
	}
	function get_home()
	{
		$this->app->xml->comment(<<<COMMENT
API

资源相关
GET ?resources 获取资源
GET ?tags 获取标签

账号相关
GET ?register 注册账号
GET ?signature/{账号（如：0FqvMsV_ox）} 账号信息
GET ?account/{签名（如：XGOLT5Q0KTphWfFK2FBGeRZOoV6s-YO-B7LtXXO5VN30pGnNF4FQXCTiM81DEuRO6oh）} 账号信息
GET ?play/{资源+签名（如：001V4BE4R1TRXGOLT5Q0KTphWfFK2FBGeRZOoV6s-YO-B7LtXXO5VN30pGnNF4FQXCTiM81DEuRO6oh）} 付费播放
POST ?favorite/{资源+签名} 收藏这个资源
DELETE ?favorite/{资源+签名} 从收藏里删除这个资源

评论相关
POST ?comment/{资源+签名} BODY(评论内容) 提交评论
GET ?comments 获取评论

付款相关
POST ?payment/{签名} 创建一条付款记录
GET ?payments/{签名} 拉取付款记录

COMMENT);


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
	//资源
	function get_resources(string $tag = NULL, int $page = 1, int $size = 1000)
	{
		$cond = ['WHERE FIND_IN_SET(?i,sites) AND checked=1', $this->site];
		if ($tag)
		{
			$cond[0] .= ' AND FIND_IN_SET(?s,tags)';
			$cond[] = $tag;
		}
		$resources = $this->mysql->resources(...$cond)->paging($page, $size);
		$this->app->xml->setattr($resources->paging);
		foreach ($resources as $resource)
		{
			$this->app->xml->append('resource', [
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
			])->cdata($resource['name']);
		}
	}
	//标签
	function tag_xml(array $tag)
	{
		$this->app->xml->append('tag', [
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
	function account(string $signature):array
	{
		return $this->authorize($signature, fn(string $uid, string $pwd):array
			=> $this->mysql->accounts('WHERE uid=?s AND site=?i AND pwd=?s LIMIT 1', $uid, $this->site, $pwd)->array());
	}
	function account_xml(array $account, string $signature = NULL)
	{
		$this->app->xml->append('account', [
			'uid' => $account['uid'],
			'signature' => $signature ?? $this->signature($account['uid'], $account['pwd']),
			'expire' => $account['expire'],
			'balance' => $account['balance'],
			'name' => $account['name'],
			//'gender' => $account['gender']
		])->cdata($account['favorites']);
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
			'pwd' => random_int(100000, 999999),
			'name' => $this->hash($rand),
			'gender' => ['男', '女'][intval(ord($rand) > 10)],
			'favorites' => ''])) {
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
		if ($account = $this->account($signature))
		{
			$this->account_xml($account, $signature);
		}
	}
	function get_play(string $resource_signature)
	{
		if ($account = $this->account($signature = substr($resource_signature, 12)))
		{
			$require = $this->mysql->resources('WHERE hash=?s LIMIT 1', $resource = substr($resource_signature, 0, 12))->array()['require'] ?? 0;
			if ($require > 0 && $this->mysql->accounts('WHERE uid=?s LIMIT 1', $account['uid'])->update('balance=balance-?i', $require) === 1)
			{
				$this->app->xml->append('play', ['signature' => $signature, 'resource' => $resource, 'balance' => $account['balance'] - $require]);
			}
		}
	}
	function post_favorite(string $resource_signature)
	{
		if (($account = $this->account($signature = substr($resource_signature, 12)))
			&& $this->mysql->accounts('WHERE uid=?s LIMIT 1', $account['uid'])->update('favorites=?s', $favorites = $account['favorites']
				? join(array_unique(str_split(substr($account['favorites'], -384) . substr($resource_signature, 0, 12), 12)))
				: substr($resource_signature, 0, 12))) {
			$this->app->xml->append('favorite', ['signature' => $signature])->cdata($favorites);
		}
	}
	function delete_favorite(string $resource_signature)
	{
		if (($account = $this->account($signature = substr($resource_signature, 12)))
			&& ($resource = substr($resource_signature, 0, 12))
			&& $this->mysql->accounts('WHERE uid=?s LIMIT 1', $account['uid'])->update('favorites=?s',
				$favorites = $account['favorites'] ? join(array_filter(str_split($account['favorites'], 12), fn($v)=>$v&&$v!==$resource)) : '') === 1) {
			$this->app->xml->append('favorite', ['signature' => $signature])->cdata($favorites);
		}
	}

	//支付
	function post_payment(string $signature)
	{
		if ($account = $this->account($signature))
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
		if ($account = $this->account($signature))
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




	//评论
	function post_comment(string $resource_signature)
	{
		if (($account = $this->account(substr($resource_signature, 12)))
			&& $this->mysql->comments->insert([
				'hash' => $hash = $this->shorthash(
					$resource = substr($resource_signature, 0, 12), $account['uid'],
					$content = $this->request_content('text/plain'), TRUE),
				'site' => $this->site,
				'time' => $this->time,
				'resource' => $resource,
				'account' => $account['uid'],
				'content' => $content])) {
			$this->app->xml->append('comment', [
				'hash' => $hash,
				'time' => $this->time,
				'resource' => $resource,
				'account' => $account['uid']
			])->cdata($content);
		}
	}
	function get_comments(string $resource = NULL, int $page = 1, int $size = 1000)
	{
		$comments = $this->mysql->comments(...$resource
			? ['WHERE site=?i AND resource=?s ORDER by time asc,resource asc', $this->site, $resource]
			: ['WHERE site=?i ORDER by time asc,resource asc', $this->site])->paging($page, $size);
		$this->app->xml->setattr($comments->paging);
		foreach ($comments as $comment)
		{
			$this->app->xml->append('comment', [
				'hash' => $comment['hash'],
				'time' => $comment['time'],
				'resource' => $comment['resource'],
				'account' => $comment['account']
			])->cdata($comment['content']);
		}
	}









	//访问
	function visits(string $ip, int $increment):bool
	{
		$date = date('Ymd', $this->time);
		$hash = $this->hash("{$this->site}{$date}" . inet_pton($ip), TRUE);
		return $this->mysql->visits('WHERE hash=?s', $hash)->update('count=count+' . $increment)
			|| $this->mysql->visits->insert([
				'hash' => $hash,
				'site' => $this->site,
				'date' => $date,
				'iphex' => $this->iphex($ip),
				'count' => $increment]);
	}
	function post_visits()
	{
		if (is_array($input = $this->request_content()))
		{
			foreach ($input as $ip => $increment)
			{
				print_r($ip);
			}
		}
	}
	function get_visits(int $date = NULL)
	{
		//var_dump( $this->visits('127.0.0.1', 1) );
		// print_r($this->mysql);

		$this->app->xml['date'] = $date ??= date('Ymd');
		$site = NULL;
		foreach ($this->mysql->visits('WHERE date=?i ORDER BY site asc', $date) as $view)
		{
			if ($site !== $view['site'])
			{
				$node = $this->app->xml->append('site', ['id' => $site = $view['site'], 'uip' => 0, 'count' => 0]);
			}
			$node['count'] += $view['count'];
			$node['uip'] += 1;
			$node->append('view', [
				'hash' => $view['hash'],
				'ip' => $this->hexip($view['iphex']),
				'count' => $view['count']
			]);
		}
	}

};
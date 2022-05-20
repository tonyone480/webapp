<?php
class webapp_router_admin extends webapp_echo_html
{
	function __construct(news_master $webapp)
	{
		parent::__construct($webapp);
		if (!$webapp->admin)
		{
			if (str_ends_with($webapp->method, 't_home'))
			{
				if ($webapp->method === 'post_home'
					&& webapp_echo_html::form_sign_in($webapp)->fetch($admin)
					&& $webapp->admin($signature = $webapp->signature($admin['username'], $admin['password']))) {
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
		$this->title($this->webapp->site);
		$this->nav([
			['Admin', '?admin'],
			['Accounts', '?admin/accounts'],
			['Ads', '?admin/ads'],
			['Payments', '?admin/payments']
		])->ul->insert('li', 'first')->setattr(['style' => 'margin-left:1rem'])->select($this->webapp['app_site'])->selected($this->webapp->site)->setattr(['onchange' => 'location.reload(document.cookie=`app_site=${this.value}`)']);
		$this->xml->head->append('script', ['type' => 'text/javascript', 'src' => '/webapp/lib/news/functions.js']);
	}
	function warn(string $text)
	{
		$this->main->append('h4', $text);
	}
	function okay(string $goto):int
	{
		$this->webapp->response_location($goto);
		return 302;
	}
	function post_home()
	{
	}
	function get_home(string $ym = '')
	{
		[$y, $m] = preg_match('/^\d{4}(?=\-(\d{2}))/', $ym, $pattren) ? $pattren : explode(',', date('Y,m'));
		$fields = [
			'pv' => ['页面访问', '#F2D7D5'],
			'ua' => ['唯一地址', '#EBDEF0'],
			'lu' => ['登录用户', '#D4E6F1'],
			'ru' => ['注册用户', '#D4EFDF'],
			'dc' => ['下载数量', '#FCF3CF'],
			'ia' => ['激活数量', '#FDEBD0'],
			'oc' => ['订单数量', '#FAE5D3'],
			'op' => ['支付数量', '#F6DDCC'],
			'ov' => ['订单金额', '#F2F3F4'],
			'oi' => ['支付金额', '#E5E8E8']
		];
		$stats = ['汇总' => [$types = ['pv' => 0, 'ua' => 0, 'lu' => 0, 'ru' => 0, 'dc' => 0, 'ia' => 0, 'oc' => 0, 'op' => 0, 'ov' => 0, 'oi' => 0]]];
		foreach ($this->webapp->mysql->unitstats('where site=?i and year=?s and month=?s order by oi desc', $this->webapp->site, $y, $m) as $stat)
		{
			$stats['汇总'][$stat['day']] ??= $types;
			$stats[$stat['unit']][0] ??= $types;
			foreach ($stats[$stat['unit']][$stat['day']] = [
				'pv' => $stat['pv'],
				'ua' => $stat['ua'],
				'lu' => $stat['lu'],
				'ru' => $stat['ru'],
				'dc' => $stat['dc'],
				'ia' => $stat['ia'],
				'oc' => $stat['oc'],
				'op' => $stat['op'],
				'ov' => $stat['ov'],
				'oi' => $stat['oi']] as $k => $v) {
				$stats['汇总'][0][$k] += $v;
				$stats['汇总'][$stat['day']][$k] += $v;
				$stats[$stat['unit']][0][$k] += $v;
			}
		}
		// print_r($stats);
		// return;
		$t = (int)date('t', mktime(0, 0, 0, $m, 1, $y));
		$table = $this->main->table();
		$table->fieldset('单位', '统计', '总计', ...range(1, $t));
		$table->header->append('input', ['type' => 'month', 'value' => "{$y}-{$m}", 'onchange' => 'g({ym:this.value})']);
		foreach ($stats as $unit => $stat)
		{
			$row = $table->row();
			//$table->cell([$unit, 'rowspan' => 11])
			$row->append('td', [$unit, 'rowspan' => 11, 'style' => 'background:silver']);
			$node = [];
			foreach ($fields as $name => $ctx)
			{
				$row = $table->row()->setattr(['style' => "background:{$ctx[1]}"]);
				$row->append('td', $ctx[0]);
				for ($i = 0; $i <= $t; ++$i)
				{
					$node[$i][$name] = $row->append('td', 0);
				}
			}
			foreach ($stat as $day => $value)
			{
				foreach ($value as $field => $count)
				{
					$node[$day][$field][0] = number_format($count);
				}
			}
		}

	}
	function form_account($ctx):webapp_form
	{
		$form = new webapp_form($ctx);

		$form->fieldset('name / password');
		$form->field('name', 'text');
		$form->field('pwd', 'text');

		$form->fieldset('expire / balance');
		$form->field('expire', 'date', [],
			fn($v, $i)=>$i?strtotime($v):date('Y-m-d', $v));
		$form->field('balance', 'number', ['min' => 0]);

		$form->fieldset();
		$form->button('Submit', 'submit');
		return $form;
	}
	function post_account_update(string $uid)
	{
		$acc = $this->webapp->mysql->accounts('where uid=?s', $uid)->array();
		if ($acc
			&& $this->form_account($this->webapp)->fetch($acc)
			&& $this->webapp->mysql->accounts('where uid=?s', $uid)->update($acc)
			&& $this->webapp->call('saveUser', $this->webapp->account_xml($acc))) {
			return $this->okay('?admin/accounts');
		}
		$this->warn('账户更新失败！');
	}
	function get_account_update(string $uid)
	{
		$this->form_account($this->main)->echo($this->webapp->mysql->accounts('where uid=?s', $uid)->array());
	}
	function get_accounts($search = NULL, int $page = 1)
	{
		$cond = ['where site=?i', $this->webapp->site];
		if ($search)
		{
			$search = urldecode($search);
			$cond[0] .= ' and (uid=?s or phone=?s)';
			array_push($cond, $search, $search);
		}
		$cond[0] .= ' order by time desc';

		$table = $this->main->table($this->webapp->mysql
			->accounts(...$cond)
			//->select('uid,site,time,expire,balance,lasttime,device,name,gender')
			->paging($page)
			->result(), function($table, $acc) {
			$table->row();
			$table->cell()->append('a', [$acc['uid'], 'href' => "?admin/account-update,uid:{$acc['uid']}"]);
			$table->cell(date('Y-m-d', $acc['time']));
			$table->cell(date('Y-m-d', $acc['expire']));
			$table->cell(number_format($acc['balance']));
			$table->cell(date('Y-m-d', $acc['lasttime']));
			$table->cell($this->webapp->hexip($acc['lastip']));
			$table->cell($acc['device']);
			$table->cell($acc['phone']);
		});
		$table->fieldset('uid', 'time', 'expire', 'balance', 'lasttime', 'lastip', 'device', 'phone');
		$table->header('Found ' . $table->count() . ' item');
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'placeholder' => 'Type search keywords',
			'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null})']);
		$table->paging('?admin/accounts,page:');
	}
	function get_payments()
	{
	}



	//广告
	function form_ad($ctx):webapp_form
	{
		$form = new webapp_form($ctx);

		$form->fieldset('图片');
		$form->field('pic', 'file', ['accept' => 'image/*']);

		$form->fieldset('名称跳转');
		$form->field('name', 'text', ['placeholder' => '广告名称']);
		$form->field('goto', 'url', ['placeholder' => '跳转地址', 'required' => NULL]);

		$form->fieldset('有效时间段，每天展示时间段');
		$form->field('timestart', 'datetime-local', ['value' => date('Y-m-d\T00:00'), 'required' => NULL],
			fn($v,$i)=>$i?strtotime($v):date('Y-m-d\TH:i',$v));
		$form->field('timeend', 'datetime-local', ['value' => date('Y-m-d\T23:59'), 'required' => NULL],
			fn($v,$i)=>$i?strtotime($v):date('Y-m-d\TH:i',$v));

		$form->fieldset('每周几显示，空为时间内展示');
		$form->field('weekset', 'checkbox', ['options' => [
			'星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六']],
			fn($v,$i)=>$i?join(',',$v):explode(',',$v));
		$form->field('seat', 'checkbox', ['options' => [
			'位置0', '位置1', '位置2', '位置3', '位置4', '位置5', '位置7', '位置8', '位置9',]],
			fn($v,$i)=>$i?join(',',$v):explode(',',$v));

		$form->fieldset('展示方式：小于0点击次数，大于0展示次数');
		$form->field('count', 'number', ['value' => 0, 'required' => NULL]);

		

		$form->fieldset();
		$form->button('提交', 'submit');


		return $form;
	}

	function get_ads()
	{

		$table = $this->main->table($this->webapp->mysql->ads('where site=?i', $this->webapp->site), function($table, $ad)
		{
			$table->row();
			$table->cell()->append('a', ['delete', 'href' => "?admin/ad-delete,hash:{$ad['hash']}"]);
			$table->cell()->append('a', [$ad['hash'], 'href' => "?admin/ad-update,hash:{$ad['hash']}"]);
			$table->cell($ad['name']);
			$table->cell($ad['seat']);
			$table->cell(date('Y-m-d\TH:i:s', $ad['timestart']) . ' - ' . date('Y-m-d\TH:i:s', $ad['timeend']));
			$table->cell($ad['weekset']);
			$table->cell($ad['count']);
			$table->cell($ad['click']);
			$table->cell($ad['view']);
			$table->cell()->append('a', [$ad['goto'], 'href' => $ad['goto'], 'target' => '_blank']);
		});
		$table->fieldset('delete', 'hash', 'name', 'seat', 'timestart - timeend', 'weekset', 'count', 'click', 'view', 'goto');
		$table->header('Found ' . $this->webapp->mysql->ads->count() . ' item');
		$table->bar->append('button', ['Create Ad', 'onclick' => 'location.href="?admin/ad-create"']);
	}
	function post_ad_create()
	{
		if ($this->form_ad($this->webapp)->fetch($ad, $error)
			&& $this->webapp->mysql->ads->insert($ad += [
				'hash' => $this->webapp->randhash(),
				'site' => $this->webapp->site,
				'time' => $this->webapp->time,
				'click' => 0,
				'view' => 0])
			&& $this->webapp->call('saveAd', $this->webapp->ad_xml($ad))) {
			return $this->okay('?admin/ads');
		}
		$this->warn("广告新建失败, {$error}！");
	}
	function get_ad_create()
	{
		$this->form_ad($this->main);
	}
	function post_ad_update(string $hash)
	{
		$ad = $this->webapp->mysql->ads('where hash=?s', $hash)->array();
		if ($ad
			&& $this->form_ad($this->webapp)->fetch($ad, $error)
			&& $this->webapp->call('saveAd', $this->webapp->ad_xml($ad))
			&& $this->webapp->mysql->ads('where hash=?s', $ad['hash'])->update($ad)) {
			return $this->okay('?admin/ads');
		}
		$this->warn("广告跟新失败，{$error}！");
	}
	function get_ad_update(string $hash)
	{
		$this->form_ad($this->main)->echo($this->webapp->mysql->ads('WHERE hash=?s', $hash)->array());
	}
	function get_ad_delete(string $hash)
	{
		if ($this->webapp->call('delAd', $hash)
			&& $this->webapp->mysql->ads->delete('WHERE hash=?s', $hash)) {
			return $this->okay('?admin/ads');
		}
		$this->warn('广告删除失败！');
	}
}
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
	function account(string $signature):array
	{
		return $this->authorize($signature, fn(string $uid, string $pwd):array
			=> $this->mysql->accounts('WHERE uid=?s AND site=?i AND pwd=?s LIMIT 1', $uid, $this->site, $pwd)->array());
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
		$node->append('favorites')->cdata($account['favorites']);
		$node->append('historys')->cdata($account['historys']);
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
			'favorites' => '',
			'historys' => ''])) {
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










};
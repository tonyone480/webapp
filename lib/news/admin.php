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
			['Reports', '?admin/reports'],
			['Unitstats', '?admin/unitstats'],
			['Payments', '?admin/payments'],
			['Resources', '?admin/resources'],
			['Accounts', '?admin/accounts'],
			['Ads', '?admin/ads']
		])->ul->insert('li', 'first')->setattr(['style' => 'margin-left:1rem'])->select($this->webapp['app_site'])->selected($this->webapp->site)->setattr(['onchange' => 'location.reload(document.cookie=`app_site=${this.value}`)']);
		$this->xml->head->append('script', ['type' => 'text/javascript', 'src' => '/webapp/lib/news/admin.js']);
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
	function get_home(int $page = 1)
	{
		$cond = ['where site=?i order by time desc', $this->webapp->site];
		$table = $this->main->table($this->webapp->mysql->reports(...$cond)->paging($page), function($table, $rep)
		{
			$table->row();
			$table->cell($rep['hash']);
			$table->cell(date('Y-m-d\\TH:i:s', $rep['time']));
			$table->cell()->append('a', [$rep['account'], 'href' => "?admin/accounts,search:{$rep['account']}"]);
			$table->cell($rep['describe']);
		});
		$table->header('');
		$table->fieldset('hash', 'time', 'account', 'describe');
		$table->paging($this->webapp->at(['page' => '']));
	}
	//资源
	function form_resource($ctx):webapp_form
	{
		$form = new webapp_form($ctx);
		$form->fieldset('封面图片');
		$form->field('cover', 'file');
		$form->fieldset('name');
		$form->field('name', 'text', ['style' => 'width:42rem', 'required' => NULL]);
		$form->fieldset('tags');
		$form->field('tags', 'text', ['style' => 'width:42rem', 'required' => NULL]);
		$form->fieldset('require(免费0、会员1、金币大于1)');
		$form->field('require', 'number', ['min' => 0, 'required' => NULL]);
		$form->fieldset();
		$form->button('Update', 'submit');
		return $form;
	}
	function get_resource_update(string $hash)
	{
		if ($resource = $this->webapp->mysql->resources('where FIND_IN_SET(?s,sites) and hash=?s', $this->webapp->site, $hash)->array())
		{
			$this->form_resource($this->main)->echo($resource);
		}
	}
	function get_resources(string $search = NULL, int $page = 1)
	{
		$cond = ['WHERE FIND_IN_SET(?s,sites)', $this->webapp->site];
		if ($search)
		{
			$search = urldecode($search);
			$cond[0] .= ' AND (hash=?s or name like ?s)';
			array_push($cond, $search, "%$search%");
		}
		$cond[0] .= ' ORDER BY time DESC';
		
		$table = $this->main->table($this->webapp->mysql->resources(...$cond)->paging($page), function($table, $res)
		{
			$table->row();
			$table->cell()->append('a', [$res['hash'], 'href' => "?admin/resource-update,hash:{$res['hash']}"]);
			$table->cell(date('Y-m-d', $res['time']));
			$table->cell('-');
			$table->cell(date('G:i:s', $res['duration'] + 57600));
			$table->cell(number_format($res['favorite']));
			$table->cell(number_format($res['view']));
			$table->cell(number_format($res['like']));
			$table->cell()->append('a', [$res['name'], 'href' => 'javascript:;']);
		});
		$table->header('Found ' . $table->count() . ' item');
		$table->fieldset('hash', 'time', 'require', 'duration', 'favorite', 'view', 'like', 'name');
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'placeholder' => 'Type search keywords',
			'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})']);
		$table->paging($this->webapp->at(['page' => '']));
	}
	//统计
	function get_unitstats(string $ym = '')
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
	function get_payments()
	{
	}

	//账户
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
			'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})']);
		$table->paging($this->webapp->at(['page' => '']));
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
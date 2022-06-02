const xhr = new XMLHttpRequest;
function g(p,a)
{
	location.assign(((o,q)=>Object.keys(o).reduce((p,k)=>o[k]===null?p:`${p},${k}:${o[k]}`,q))
		(...typeof(p)==="string"?[{},p]:[
			Object.assign(Object.fromEntries(Array.from(location.search.matchAll(/\,(\w+)(?:\:([\%\+\-\.\/\=\w]*))?/g)).map(v=>v.slice(1))),p),
			a||location.search.replace(/\,.+/,"")]));
}
function urlencode(data)
{
	return encodeURIComponent(data).replace(/%20|[\!'\(\)\*\+\/@~]/g, (escape)=> ({
		'%20': '+',
		'!': '%21',
		"'": '%27',
		'(': '%28',
		')': '%29',
		'*': '%2A',
		'+': '%2B',
		'/': '%2F',
		'@': '%40',
		'~': '%7E'}[escape]));
}
function upres(e)
{
	const progress = Array.from(e.getElementsByTagName('progress'));
	xhr.open(e.method, e.action);
	xhr.setRequestHeader('Authorization', `Bearer ${e.dataset.auth}`);
	xhr.upload.onprogress = event => event.lengthComputable && progress.forEach(e => e.value = event.loaded / event.total);
	xhr.send(new FormData(e));
	xhr.responseType = 'json';
	xhr.onload = () => {
		if (Object.keys(xhr.response.errors))
		{
			alert(Object.values(xhr.response.errors).join("\n"));
		}
		else
		{
			if (xhr.response.goto)
			{
				location.href = xhr.response.goto;
			}
		}
		console.log(xhr.response)
	};
	return false;
}
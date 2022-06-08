function unpack(data, binary)
{
	const key = new Uint8Array(data.slice(0, 8));
	const buffer = new Uint8Array(data.slice(8));
	for (let i = 0; i < buffer.length; ++i)
	{
		buffer[i] = buffer[i] ^ key[i % 8];
	}
	return binary
		? URL.createObjectURL(new Blob([buffer.buffer]))
		: (new TextDecoder('utf-8')).decode(buffer);
}
function fetchpic(img)
{
	fetch(img.dataset.url).then(response => response.arrayBuffer()).then(function(data)
	{
		img.src = unpack(data, true);
	});
}
function request(method, url, body = null)
{
	return new Promise(function(resolve, reject)
	{
		const xhr = new XMLHttpRequest;
		xhr.open(method, url);
		xhr.responseType = 'arraybuffer';
		xhr.onload = () => resolve(JSON.parse(/json/.test(xhr.getResponseHeader('Content-Type'))
			? (new TextDecoder('utf-8')).decode(new Uint8Array(xhr.response))
			: unpack(xhr.response)));
		xhr.onerror = () => reject(xhr);
		xhr.send(body);
	});
}
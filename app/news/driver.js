function request(method, url, data = null)
{
    return new Promise(function(ok, no){
        const xhr = new XMLHttpRequest;
        xhr.open(method, url);
        xhr.onload = ok;
        xhr.onerror = no;
        xhr.send(data);
    });
}



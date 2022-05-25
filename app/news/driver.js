function p(method, url, send)
{
    return new Promise(function(ok, no){
        const xhr = new XMLHttpRequest;
        xhr.open(method, url);
        xhr.onload = ok;
        xhr.onerror = no;
    });
}
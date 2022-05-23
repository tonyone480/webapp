"use strict";//em...
import('./webkit.js').then(function({default: webapp})
{
	console.log(webapp.md5('dwdawdawd'))
	console.log(webapp('a[href]'))
});
"use strict";
import('./webkit.js').then(function({default: $})
{
	console.log($('footer'));
});
"use strict";
import('./webkit.js').then(function({default: $})
{
	globalThis.$ = $;


});
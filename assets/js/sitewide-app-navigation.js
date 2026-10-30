(function(){'use strict';if(document.querySelector('.bh-app-nav'))return;
const links=[['⌂','Home','/index.html'],['⌕','Find','/directory.html'],['▦','Plan','/planner.html'],['♡','Saved','/account/saved-activities.html'],['♧','My Hub','/my-hub.html']];
const path=location.pathname;const active=(url)=>path===url||(url==='/index.html'&&path==='/')||(url==='/my-hub.html'&&(path==='/account.html'||path.startsWith('/account/')));
const nav=document.createElement('nav');nav.className='bh-app-nav';nav.setAttribute('aria-label','Main app navigation');
nav.innerHTML=links.map(([icon,label,url])=>'<a href="'+url+'"'+(active(url)?' aria-current="page"':'')+'><span aria-hidden="true">'+icon+'</span><small>'+label+'</small></a>').join('');
document.body.appendChild(nav);
const footer=document.querySelector('.site-footer');if(footer&&!footer.querySelector('.bh-footer-extra')){const block=document.createElement('nav');block.className='bh-footer-extra';block.setAttribute('aria-label','About Bubba Hub and class leader access');block.innerHTML='<a href="/team/">Meet the Team</a><a href="/about/">About Bubba Hub</a><a href="/auth.html?context=leader">Class Leader login</a><a href="/sitemap.html">Site map</a>';footer.appendChild(block)}
})();
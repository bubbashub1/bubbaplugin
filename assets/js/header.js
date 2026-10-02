/* Bubba Hub master header loader.
   Header markup: components/header.html
   Header styling: assets/css/header.css
*/
(function(){
  const script=document.currentScript;
  const scriptUrl=script?.src||new URL("assets/js/header.js",document.baseURI).href;

  const appRoot=new URL("../../",scriptUrl);
  const appUrl=path=>new URL(path,appRoot).href;

  const loadHeader=async()=>{
    try{
      const existing=document.querySelector(".site-header");
      const headerUrl=new URL("../../components/header.html",scriptUrl);
      const cssUrl=new URL("../css/header.css",scriptUrl);

      if(!document.querySelector('link[data-bh-header-css]')){
        const link=document.createElement("link");
        link.rel="stylesheet";
        link.href=cssUrl.href;
        link.dataset.bhHeaderCss="true";
        document.head.appendChild(link);
      }

      const response=await fetch(headerUrl.href,{cache:"no-store"});
      if(!response.ok)throw new Error("Could not load shared header");
      const html=await response.text();
      const activityHeader=!!existing?.classList.contains("activity-reference-header");
      const activitySignIn=existing?.querySelector(".activity-sign-in")?.cloneNode(true);
      const activitySearchToggle=existing?.querySelector(".activity-search-toggle")?.cloneNode(true);
      const activitySearch=existing?.querySelector(".activity-header-search")?.cloneNode(true);

      const template=document.createElement("template");
      template.innerHTML=html.trim();
      const header=template.content.firstElementChild;
      if(!header)return;

      if(activityHeader){
        header.classList.add("activity-reference-header");
        const inner=header.querySelector(".bh-header-inner");
        if(activitySignIn) inner?.appendChild(activitySignIn);
        if(activitySearchToggle) header.appendChild(activitySearchToggle);
        if(activitySearch) header.appendChild(activitySearch);
      }

      if(existing) existing.replaceWith(header);
      else document.body.insertBefore(header,document.body.firstElementChild);

      // Make shared-header links work from root pages and nested pages alike.
      header.querySelectorAll('a[href]').forEach(link=>{
        const href=link.getAttribute("href");
        if(href && !href.startsWith("#") && !href.startsWith("mailto:") && !href.startsWith("http")){
          link.href=appUrl(href);
        }
      });
      const logo=header.querySelector(".bh-header-logo img");
      if(logo) logo.src=appUrl("images/logos/gemini_generated_image_t65ztnt65ztnt65z-20260930-185704-7e0755.jpeg");

      const searchForm=header.querySelector(".bh-header-search-bar");
      if(searchForm){
        searchForm.action=appUrl("directory.html");

        // Keep the shared header search in sync with the live directory filters.
        const populateSearchOptions=async()=>{
          try{
            const response=await fetch(appUrl("api/activities.php")+"?page=1&per_page=100",{cache:"no-store",headers:{Accept:"application/json"}});
            if(!response.ok)return;
            const payload=await response.json();
            const items=Array.isArray(payload.data)?payload.data:[];
            const categoryMap={"Baby classes":"Baby","Baby & toddler":"Toddler","Family activities":"Family"};
            const values={
              region:[...new Set(items.flatMap(a=>(a.venues||[]).map(v=>v.region||a.region)).filter(Boolean))].sort(),
              town:[...new Set(items.flatMap(a=>(a.venues||[]).map(v=>v.town||a.town)).filter(Boolean))].sort(),
              category:[...new Set(items.map(a=>categoryMap[a.category]||a.category).filter(Boolean))].sort()
            };

            Object.entries(values).forEach(([name,list])=>{
              const select=searchForm.querySelector('select[name="'+name+'"]');
              if(!select)return;
              const current=new URLSearchParams(location.search).get(name)||"";
              select.innerHTML="<option value=\"\">"+name.charAt(0).toUpperCase()+name.slice(1)+"</option>";
              list.forEach(value=>{
                const option=document.createElement("option");
                option.value=value;
                option.textContent=value;
                select.appendChild(option);
              });
              if(list.includes(current))select.value=current;
            });

            const params=new URLSearchParams(location.search);
            const keyword=searchForm.querySelector('[name="keyword"]');
            const day=searchForm.querySelector('[name="day"]');
            if(keyword)keyword.value=params.get("keyword")||params.get("search")||params.get("q")||"";
            if(day)day.value=params.get("day")||"";
          }catch(_){}
        };
        void populateSearchOptions();
      }

      const searchToggle=header.querySelector(".bh-header-search-toggle");
      const searchBar=header.querySelector(".bh-header-search-bar");
      if(searchToggle&&searchBar){
        searchToggle.addEventListener("click",event=>{
          event.preventDefault();
          event.stopPropagation();
          const open=!searchBar.classList.contains("is-open");
          searchBar.classList.toggle("is-open",open);
          searchToggle.setAttribute("aria-expanded",String(open));
          searchToggle.setAttribute("aria-label",open?"Close search":"Open search");
          if(open){
            nav?.classList.remove("is-open");
            menu?.setAttribute("aria-expanded","false");
            menu?.setAttribute("aria-label","Open menu");
          }
          if(open) setTimeout(()=>searchBar.querySelector("[name=keyword]")?.focus(),80);
        });
        document.addEventListener("click",event=>{
          if(!searchBar.contains(event.target)){
            searchBar.classList.remove("is-open");
            searchToggle.setAttribute("aria-expanded","false");
            searchToggle.setAttribute("aria-label","Open search");
          }
        });
      }

      const menu=header.querySelector(".bh-mobile-menu");
      const nav=header.querySelector(".bh-main-nav");
      if(!menu||!nav)return;

      const close=()=>{
        nav.classList.remove("is-open");
        menu.setAttribute("aria-expanded","false");
        menu.setAttribute("aria-label","Open menu");
      };
      menu.addEventListener("click",event=>{
        event.preventDefault();
        event.stopPropagation();
        const open=!nav.classList.contains("is-open");
        nav.classList.toggle("is-open",open);
        menu.setAttribute("aria-expanded",String(open));
        menu.setAttribute("aria-label",open?"Close menu":"Open menu");
        if(open){
          searchBar?.classList.remove("is-open");
          searchToggle?.setAttribute("aria-expanded","false");
          searchToggle?.setAttribute("aria-label","Open search");
        }
      });
      nav.addEventListener("click",event=>{if(event.target.closest("a"))close();});
      document.addEventListener("click",event=>{if(!header.contains(event.target))close();});
      document.addEventListener("keydown",event=>{if(event.key==="Escape")close();});
    }catch(error){console.warn("Bubba Hub shared header could not load.",error);}
  };

  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",loadHeader,{once:true});
  else void loadHeader();
})();
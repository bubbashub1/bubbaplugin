/* Bubba Hub master header loader.
   Header markup: components/header.html
   Header styling: assets/css/header.css
*/
(function(){
  const loadHeader=async()=>{
    try{
      const existing=document.querySelector(".site-header");
      const base=document.querySelector("base")?.href||document.baseURI;
      const headerUrl=new URL("components/header.html",base);
      const cssUrl=new URL("assets/css/header.css",base);

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
      const template=document.createElement("template");
      template.innerHTML=html.trim();
      const header=template.content.firstElementChild;
      if(!header)return;

      if(existing) existing.replaceWith(header);
      else document.body.insertBefore(header,document.body.firstElementChild);

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
      });
      nav.addEventListener("click",event=>{if(event.target.closest("a"))close();});
      document.addEventListener("click",event=>{if(!header.contains(event.target))close();});
      document.addEventListener("keydown",event=>{if(event.key==="Escape")close();});
    }catch(error){console.warn("Bubba Hub shared header could not load.",error);}
  };
  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",loadHeader,{once:true});
  else void loadHeader();
})();
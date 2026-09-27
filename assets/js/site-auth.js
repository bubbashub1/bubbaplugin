(function(){
  if(window.__bhSiteAuthLoaded)return;
  window.__bhSiteAuthLoaded=true;
  "use strict";

  const RESTRICTED_PAGES = new Set([
    "my-hub.html",
    "account.html",
    "account-profile.html",
    "account-planner.html",
    "preferences.html",
    "family.html",
    "notifications.html",
    "saved-activities.html",
    "planner.html",
    "choose.html",
    "consent.html",
    "privacy.html",
    "subscription.html"
  ]);
  const LEADER_PAGES = new Set(["leader.html", "booking-manager.html", "leader-account.html"]);

  const pageName = (location.pathname.split("/").filter(Boolean).pop() || "index.html").toLowerCase();
  const authUrl = new URL("auth.html", document.baseURI);
  const nextUrl = () => location.pathname + location.search + location.hash;

  function isRestricted(){
    return RESTRICTED_PAGES.has(pageName);
  }

  function isLeaderPage(){
    return LEADER_PAGES.has(pageName);
  }

  function leaderAuthTarget(){
    const url = new URL("leader-auth.html", document.baseURI);
    url.searchParams.set("next", nextUrl());
    return url.href;
  }

  function makeLink(textValue, href, className){
    const a = document.createElement("a");
    a.textContent = textValue;
    a.href = href;
    if(className) a.className = className;
    return a;
  }

  function buildAuthTarget(){
    const url = new URL(authUrl.href);
    const next = nextUrl();
    if(next && !/\/auth\.html$/i.test(location.pathname)) url.searchParams.set("next", next);
    return url.href;
  }

  function updateHeader(auth){
    document.querySelectorAll(".site-header").forEach(header => {
      const nav = header.querySelector(".main-nav");
      if(nav){
        // Keep the primary navigation identical across the site. Authentication
        // only changes the account/leader destinations and whether Log out is shown.
        nav.innerHTML = "";

        const links = [
          ["Find activities", new URL("directory.html", document.baseURI).href],
          ["My Hub", new URL("my-hub.html", document.baseURI).href],
          ["Support & Guidance", new URL("help-support.html", document.baseURI).href]
        ];
        links.forEach(([label, href]) => nav.appendChild(makeLink(label, href)));

        const leader = makeLink(
          "Class Leaders",
          auth.user && auth.user.role === "leader"
            ? new URL("leader.html", document.baseURI).href
            : leaderAuthTarget(),
          "button button-soft bh-leader-nav-button"
        );
        leader.setAttribute("data-bh-auth-link","leader");
        nav.appendChild(leader);

        const account = makeLink(
          auth.authenticated
            ? (auth.user && auth.user.role === "leader"
              ? "My Account"
              : "My Account")
            : "My Account",
          auth.authenticated
            ? (auth.user && auth.user.role === "leader"
              ? new URL("leader-account.html", document.baseURI).href
              : new URL("account.html", document.baseURI).href)
            : buildAuthTarget(),
          "bh-auth-link"
        );
        account.setAttribute("data-bh-auth-link","account");
        nav.appendChild(account);

        const admin = makeLink("Admin", new URL("admin.html", document.baseURI).href);
        nav.appendChild(admin);

        if(auth.authenticated){
          const logout = makeLink("Log out", "#", "bh-logout-link");
          logout.setAttribute("data-bh-auth-link","logout");
          nav.appendChild(logout);
        }
      }

      const existingActions = header.querySelector(".header-account-actions");
      if(existingActions){
        existingActions.querySelectorAll(".bh-logout-button, .bh-signin-button").forEach(el => el.remove());
        if(auth.authenticated){
          const button = document.createElement("button");
          button.type = "button";
          button.textContent = "Log out";
          button.className = "button button-soft bh-logout-button";
          existingActions.appendChild(button);
        } else {
          existingActions.appendChild(makeLink("Sign in", buildAuthTarget(), "button button-soft bh-signin-button"));
        }
      } else {
        const account = header.querySelector(":scope > a.button[href*='account']");
        if(account){
          account.textContent = auth.authenticated ? "My account" : "Sign in";
          account.href = auth.authenticated ? new URL("account.html",document.baseURI).href : buildAuthTarget();
          account.classList.add("bh-account-link");
        }
      }
    });
  }

  function updateFooter(auth){
    document.querySelectorAll(".site-footer").forEach(footer => {
      let nav = footer.querySelector(".site-footer-nav");
      if(!nav){
        nav = document.createElement("nav");
        nav.className = "site-footer-nav";
        nav.setAttribute("aria-label","Account");
        footer.appendChild(nav);
      }
      nav.innerHTML = "";
      if(auth.authenticated){
        const logout = document.createElement("a");
        logout.href = "#";
        logout.textContent = "Log out";
        logout.className = "bh-logout-link";
        logout.setAttribute("data-bh-auth-link","logout");
        nav.appendChild(logout);
      } else {
        nav.appendChild(makeLink("Sign in",buildAuthTarget(),"bh-auth-link"));
        nav.appendChild(makeLink("Register",buildAuthTarget()+"&mode=register","bh-auth-link"));
      }
    });
  }

  async function logout(){
    try{
      const response = await fetch(new URL("api/auth.php?action=me",document.baseURI),{
        cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}
      });
      const data = await response.json();
      if(!data.authenticated) { location.href = new URL("index.html",document.baseURI).href; return; }
      await fetch(new URL("api/auth.php?action=logout",document.baseURI),{
        method:"POST",
        credentials:"same-origin",
        headers:{"Content-Type":"application/json","Accept":"application/json"},
        body:JSON.stringify({csrf:data.csrf||""})
      });
    }catch(e){
      // Even if the network request fails, do not leave the user stranded on a
      // restricted page. The next page load will re-check the server session.
    }
    location.href = new URL("index.html",document.baseURI).href;
  }

  document.addEventListener("click", event => {
    const target = event.target.closest(".bh-logout-link, .bh-logout-button");
    if(!target) return;
    event.preventDefault();
    if(target.dataset.bhLoggingOut === "1") return;
    target.dataset.bhLoggingOut = "1";
    target.textContent = "Logging out…";
    void logout();
  });

  async function init(){
    if(pageName === "auth.html") return;

    let auth = {authenticated:false,is_admin:false};
    try{
      const response = await fetch(new URL("api/auth.php?action=me",document.baseURI),{
        cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}
      });
      if(response.ok) auth = await response.json();
    }catch(e){}

    if(isLeaderPage() && (!auth.authenticated || (auth.user && auth.user.role !== "leader" && !auth.is_admin))){
      location.replace(auth.authenticated ? leaderAuthTarget() : leaderAuthTarget());
      return;
    }

    if(isRestricted() && !auth.authenticated){
      const target = buildAuthTarget();
      location.replace(target);
      return;
    }

    updateHeader(auth);
    updateFooter(auth);
    window.bhSiteAuth = auth;
  }

  if(document.readyState === "loading") document.addEventListener("DOMContentLoaded",init,{once:true});
  else void init();
})();
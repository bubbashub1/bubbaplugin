window.OneSignalDeferred=window.OneSignalDeferred||[];
OneSignalDeferred.push(async function(OneSignal){
  await OneSignal.init({appId:"0c4e3bc3-2049-413a-b19c-cb7823a1c861",autoResubscribe:true,serviceWorkerPath:"OneSignalSDKWorker.js",serviceWorkerParam:{scope:"/"},notificationClickHandlerMatch:"origin",notificationClickHandlerAction:"navigate"});
  window.__bubbaOneSignalReady=OneSignal;
});

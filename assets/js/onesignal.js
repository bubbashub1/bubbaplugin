window.OneSignalDeferred = window.OneSignalDeferred || [];
window.__bubbaOneSignalPromise = new Promise((resolve, reject) => {
  OneSignalDeferred.push(async function(OneSignal) {
    try {
      await OneSignal.init({
        appId: "0c4e3bc3-2049-413a-b19c-cb7823a1c861",
        serviceWorkerPath: "/OneSignalSDKWorker.js",
        serviceWorkerParam: { scope: "/" }
      });
      resolve(OneSignal);
    } catch (error) {
      reject(error);
    }
  });
});

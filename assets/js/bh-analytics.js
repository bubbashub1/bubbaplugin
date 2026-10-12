/* Bubba Hub GA4: dormant until configured and consented. */
(function () {
  'use strict';
  var id = (window.BH_ANALYTICS_ID || '').trim();
  if (!/^G-[A-Z0-9]+$/i.test(id)) return;
  if (/^(staging\.|www\.staging\.|localhost$)/i.test(location.hostname)) return;
  if (window.__bhAnalyticsLoaded || document.querySelector('script[data-bh-analytics]')) return;
  // Do not track until explicit analytics consent has been recorded.
  if (localStorage.getItem('bh_analytics_consent') !== 'granted') return;
  window.__bhAnalyticsLoaded = true;
  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function(){window.dataLayer.push(arguments);};
  window.gtag('js', new Date());
  window.gtag('config', id, { send_page_view: true });
  var tag = document.createElement('script');
  tag.async = true;
  tag.dataset.bhAnalytics = '1';
  tag.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
  document.head.appendChild(tag);
})();

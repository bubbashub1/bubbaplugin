self.addEventListener('push', event => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (_) {
    data = { title: 'Bubba Hub', body: event.data ? event.data.text() : '' };
  }

  const title = data.title || 'Bubba Hub';
  const options = {
    body: data.body || 'You have a new Bubba Hub notification.',
    icon: data.icon || '/images/logos/mainlogo-20260924-221429-7e305d.jpg',
    badge: data.badge || '/images/logos/mainlogo-20260924-221429-7e305d.jpg',
    tag: data.tag || 'bubbahub',
    data: { url: data.url || '/my-hub.html' }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const target = event.notification.data && event.notification.data.url
    ? event.notification.data.url
    : '/my-hub.html';

  event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
    for (const client of list) {
      if ('focus' in client) {
        client.navigate(target);
        return client.focus();
      }
    }
    if (clients.openWindow) return clients.openWindow(target);
  }));
});

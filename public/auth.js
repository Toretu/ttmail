// Shared by every app page: redirects to the sign-in page when there is no session and shows the signed-in user.
(async () => {
  const res = await fetch('/api/me');
  if (res.status === 401) {
    location.href = '/login.html?next=' + encodeURIComponent(location.pathname + location.hash);
    return;
  }
  if (!res.ok) return;
  const me = (await res.json()).data;
  window.currentUser = me;

  const box = document.createElement('span');
  box.style.cssText = 'margin-left:auto;color:#fff;font:600 13px system-ui;display:flex;gap:10px;align-items:center';
  const nav = document.querySelector('.topnav');
  if (!nav) {
    box.style.cssText += ';position:fixed;top:8px;right:12px;z-index:50;background:#2b1d6b;padding:6px 12px;border-radius:8px';
  }
  const label = document.createElement('span');
  label.textContent = `${me.display_name || me.email} (${me.role})`;
  const out = document.createElement('button');
  out.textContent = 'Sign out';
  out.style.cssText = 'border:0;border-radius:6px;padding:3px 10px;cursor:pointer;font:inherit';
  out.addEventListener('click', async () => {
    await fetch('/auth/logout', { method: 'POST' });
    location.href = '/login.html';
  });
  box.append(label, out);
  (nav || document.body).append(box);
})();

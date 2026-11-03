const state = {
  view: "dashboard",
  companies: [
    { name: "Nordlys Elektro AS", category: "Local business", city: "Oslo", contact: "Kari Berg", email: "kari@nordlyselektro.no", status: "Active", tag: "Warm lead", initials: "NB", color: "avatar-blue" },
    { name: "Fjord & Fritid", category: "Sports & leisure", city: "Bærum", contact: "Morten Dahl", email: "morten@fjordfritid.no", status: "Active", tag: "Previous sponsor", initials: "FF", color: "avatar-green" },
    { name: "Lindbakken Eiendom", category: "Professional services", city: "Oslo", contact: "Anne Lind", email: "anne@lindbakken.no", status: "Active", tag: "New", initials: "LE", color: "avatar-orange" },
    { name: "Solgløtt Bakeri", category: "Retail", city: "Asker", contact: "Jonas Nymo", email: "jonas@solglott.no", status: "Do not contact", tag: "Opted out", initials: "SB", color: "avatar-pink" },
    { name: "Aasen & Sønner", category: "Local business", city: "Oslo", contact: "Ragnhild Aasen", email: "ragnhild@aasen.no", status: "Active", tag: "Potential", initials: "AS", color: "avatar-purple" }
  ],
  contacts: [
    { name: "Kari Berg", company: "Nordlys Elektro AS", role: "Marketing manager", email: "kari@nordlyselektro.no", stage: "Interested", initials: "KB", color: "avatar-blue" },
    { name: "Morten Dahl", company: "Fjord & Fritid", role: "Owner", email: "morten@fjordfritid.no", stage: "Proposal sent", initials: "MD", color: "avatar-green" },
    { name: "Anne Lind", company: "Lindbakken Eiendom", role: "Managing director", email: "anne@lindbakken.no", stage: "Email sent", initials: "AL", color: "avatar-orange" },
    { name: "Ragnhild Aasen", company: "Aasen & Sønner", role: "Sales", email: "ragnhild@aasen.no", stage: "Follow-up required", initials: "RA", color: "avatar-purple" }
  ],
  campaigns: [
    { name: "2027 Sports Catalog", type: "Main sponsor campaign", status: "Sending", sent: 186, total: 240, replies: 32, value: "68 400 kr", progress: 78 },
    { name: "Spring concert partners", type: "Local business outreach", status: "Follow-up", sent: 94, total: 120, replies: 21, value: "24 750 kr", progress: 64 },
    { name: "Previous sponsors", type: "Renewal campaign", status: "Draft", sent: 0, total: 58, replies: 0, value: "0 kr", progress: 0 }
  ],
  replies: [
    { company: "Nordlys Elektro AS", person: "Kari Berg", subject: "Re: 2027 Sports Catalog", stage: "Interested", due: "Today", value: "12 000 kr", initials: "KB", color: "avatar-blue" },
    { company: "Fjord & Fritid", person: "Morten Dahl", subject: "We would like to see the options", stage: "Proposal sent", due: "Tomorrow", value: "8 500 kr", initials: "MD", color: "avatar-green" },
    { company: "Lindbakken Eiendom", person: "Anne Lind", subject: "Question about middle page", stage: "Needs information", due: "Today", value: "6 500 kr", initials: "AL", color: "avatar-orange" },
    { company: "Aasen & Sønner", person: "Ragnhild Aasen", subject: "Catalog advertising", stage: "Follow-up required", due: "Overdue", value: "4 500 kr", initials: "RA", color: "avatar-purple" }
  ],
  products: [
    { name: "Full page", position: "Standard placement", price: "12 000 kr", available: 18, sold: 7, status: "Active" },
    { name: "Half page", position: "Standard placement", price: "6 500 kr", available: 30, sold: 14, status: "Active" },
    { name: "Middle page", position: "Premium placement", price: "8 500 kr", available: 8, sold: 3, status: "Active" },
    { name: "Last page", position: "Premium back page", price: "18 000 kr", available: 1, sold: 0, status: "Reserved" }
  ]
};

const viewTitles = { dashboard: "Dashboard", companies: "Companies", contacts: "Contacts", groups: "Groups", campaigns: "Campaigns", replies: "Replies & follow-up", products: "Advertising products", opportunities: "Opportunities", settings: "Settings" };
const $ = (selector) => document.querySelector(selector);

function initials(name) {
  return name.split(" ").map((part) => part[0]).slice(0, 2).join("").toUpperCase();
}

function render() {
  $("#breadcrumb").textContent = viewTitles[state.view];
  document.querySelectorAll(".nav-item[data-view]").forEach((item) => item.classList.toggle("active", item.dataset.view === state.view));
  $("#view-container").innerHTML = views[state.view]();
  bindViewEvents();
}

function page(title, subtitle, action = "") {
  return `<section class="page"><div class="page-heading"><div><span class="eyebrow">MAILINGS WORKSPACE</span><h1>${title}</h1><p class="subtitle">${subtitle}</p></div>${action}</div></section>`;
}

function dashboard() {
  return page("Good morning, Ingrid", "Here is what needs your attention today.", `<button class="button primary" data-view-action="campaigns">＋ New campaign</button>`).replace("</section>", `
    <div class="stats-grid">
      <div class="stat-card"><span class="stat-label">Active campaigns</span><strong class="stat-value">2</strong><span class="trend">↑ 1 from last month</span></div>
      <div class="stat-card"><span class="stat-label">Emails sent this month</span><strong class="stat-value">280</strong><span class="trend">↑ 18.4% from last month</span></div>
      <div class="stat-card"><span class="stat-label">Replies to process</span><strong class="stat-value">7</strong><span class="trend">3 due today</span></div>
      <div class="stat-card"><span class="stat-label">Confirmed value</span><strong class="stat-value">68 400 kr</strong><span class="trend">↑ 12.6% campaign total</span></div>
    </div>
    <div class="dashboard-grid">
      <div class="panel"><div class="panel-header"><h3>Campaign overview</h3><button class="button secondary small" data-view-action="campaigns">View all</button></div>
        ${state.campaigns.map((campaign) => `<div class="campaign-row"><div><div class="campaign-name">${campaign.name}</div><div class="campaign-meta">${campaign.type}</div></div><div><div class="progress-label"><span>${campaign.sent}/${campaign.total}</span></div><div class="progress-track"><div class="progress-fill" style="width:${campaign.progress}%"></div></div></div><span class="badge ${campaign.status === "Sending" ? "blue" : campaign.status === "Follow-up" ? "amber" : "green"}">${campaign.status}</span><strong class="campaign-name">${campaign.value}</strong></div>`).join("")}
      </div>
      <div class="panel"><div class="panel-header"><h3>Recent activity</h3><span>Last 7 days</span></div>
        <div class="activity"><div class="activity-icon">↩</div><div><strong>Reply from Nordlys Elektro AS</strong><p>Kari Berg moved to Interested · 24 min ago</p></div></div>
        <div class="activity"><div class="activity-icon">✦</div><div><strong>Campaign batch sent</strong><p>2027 Sports Catalog · 50 recipients · 2h ago</p></div></div>
        <div class="activity"><div class="activity-icon">◈</div><div><strong>Opportunity confirmed</strong><p>Fjord &amp; Fritid · 8 500 kr · Yesterday</p></div></div>
        <div class="activity"><div class="activity-icon">♙</div><div><strong>New contact added</strong><p>Lindbakken Eiendom · Yesterday</p></div></div>
      </div>
    </div>
  </section>`);
}

function companies() {
  return page("Companies", `${state.companies.length} companies in your customer database.`, `<a class="button secondary" href="/import.html">⇪ Import CSV/Excel</a> <a class="button secondary" href="/records.html">✎ Edit / delete real companies</a> <button class="button primary" data-open-company>＋ Add company</button>`).replace("</section>", `
    <div class="toolbar"><label class="search">⌕<input data-table-search placeholder="Search companies, cities, or contacts"></label><button class="button secondary small">Filter ▾</button></div>
    <div class="panel table-wrap"><table><thead><tr><th>Company</th><th>Category</th><th>Primary contact</th><th>Location</th><th>Status</th><th></th></tr></thead><tbody id="company-rows">${companyRows(state.companies)}</tbody></table></div>
  </section>`);
}

function companyRows(companies) {
  if (!companies.length) return `<tr><td colspan="6"><div class="empty">No companies match your search.</div></td></tr>`;
  return companies.map((company) => `<tr><td><div class="person"><div class="avatar ${company.color}">${company.initials}</div><div><strong>${company.name}</strong><small>${company.tag}</small></div></div></td><td>${company.category}</td><td><strong>${company.contact}</strong><small>${company.email}</small></td><td>${company.city}</td><td><span class="badge ${company.status === "Active" ? "green" : "red"}">${company.status}</span></td><td>•••</td></tr>`).join("");
}

function contacts() {
  return page("Contacts", `${state.contacts.length} contacts with campaign history.`, `<a class="button primary" href="/records.html#contacts">✎ Manage real contacts</a>`).replace("</section>", `
    <div class="toolbar"><label class="search">⌕<input data-contact-search placeholder="Search contacts or companies"></label><button class="button secondary small">All stages ▾</button></div>
    <div class="panel table-wrap"><table><thead><tr><th>Contact</th><th>Company</th><th>Role</th><th>Email</th><th>Current stage</th><th></th></tr></thead><tbody id="contact-rows">${contactRows(state.contacts)}</tbody></table></div>
  </section>`);
}

function contactRows(contacts) {
  return contacts.map((contact) => `<tr><td><div class="person"><div class="avatar ${contact.color}">${contact.initials}</div><strong>${contact.name}</strong></div></td><td>${contact.company}</td><td>${contact.role}</td><td>${contact.email}</td><td><span class="badge blue">${contact.stage}</span></td><td>•••</td></tr>`).join("");
}

function groups() {
  const groups = [{ name: "Potential sponsors", members: 148, description: "Active businesses not contacted in the current campaign", color: "blue" }, { name: "Previous advertisers", members: 62, description: "Companies with a confirmed advertising sale", color: "green" }, { name: "Local sports organizations", members: 31, description: "Organizations in Oslo, Bærum and Asker", color: "amber" }];
  return page("Groups", "Build reusable audiences for campaigns.", `<button class="button primary" data-toast="Group creation will be saved in the MariaDB database.">＋ New group</button>`).replace("</section>", `<div class="stats-grid"><div class="stat-card"><span class="stat-label">Active groups</span><strong class="stat-value">8</strong><span class="trend">3 smart groups</span></div><div class="stat-card"><span class="stat-label">Total members</span><strong class="stat-value">386</strong><span class="trend">Across all groups</span></div></div><div class="dashboard-grid"><div class="panel"><div class="panel-header"><h3>Your groups</h3><span>Updated today</span></div>${groups.map((group) => `<div class="activity"><div class="activity-icon">${group.color === "blue" ? "◎" : group.color === "green" ? "✓" : "⌖"}</div><div style="flex:1"><strong>${group.name}</strong><p>${group.description}</p></div><strong>${group.members}</strong></div>`).join("")}</div><div class="panel"><div class="panel-header"><h3>How groups work</h3></div><p class="subtitle" style="line-height:1.7">Groups can be selected when creating a campaign. Unsubscribed and duplicate contacts are automatically excluded before sending.</p><button class="button secondary small" data-toast="The group builder will use database filters.">View group rules</button></div></div></section>`);
}

function campaigns() {
  return page("Campaigns", "Create projects, prepare audiences, and track results.", `<a class="button primary" href="/campaigns.html">＋ New campaign</a>`).replace("</section>", `<div class="panel table-wrap"><div class="panel-header"><h3>Your campaigns (live data)</h3></div><div id="live-campaigns" class="empty">Loading…</div></div><div class="panel table-wrap"><div class="panel-header"><h3>Sample campaigns (demo data only)</h3></div><table><thead><tr><th>Campaign</th><th>Status</th><th>Recipients</th><th>Replies</th><th>Confirmed value</th><th></th></tr></thead><tbody>${state.campaigns.map((campaign) => `<tr><td><strong>${campaign.name}</strong><small>${campaign.type}</small></td><td><span class="badge ${campaign.status === "Sending" ? "blue" : campaign.status === "Follow-up" ? "amber" : "green"}">${campaign.status}</span></td><td>${campaign.sent} / ${campaign.total}</td><td>${campaign.replies}</td><td><strong>${campaign.value}</strong></td><td><button class="button secondary small" data-toast="Campaign detail will open here.">Open</button></td></tr>`).join("")}</tbody></table></div></section>`);
}

function replies() {
  const columns = ["Replied", "Interested", "Proposal sent", "Follow-up required"];
  return page("Replies & follow-up", "Process replies and keep every opportunity moving.", `<button class="button secondary" data-toast="Follow-up export will be available from the live app.">Export queue</button>`).replace("</section>", `<div class="toolbar"><span class="subtitle">7 open replies · 3 due today · 1 overdue</span><button class="button secondary small">My follow-ups ▾</button></div><div class="kanban">${columns.map((column) => `<div class="kanban-column"><div class="kanban-title">${column}<span>${state.replies.filter((reply) => reply.stage === column).length}</span></div>${state.replies.filter((reply) => reply.stage === column).map((reply) => `<div class="deal-card"><strong>${reply.company}</strong><small>${reply.person}</small><small>${reply.subject}</small><div class="deal-foot"><span class="${reply.due === "Overdue" ? "badge red" : reply.due === "Today" ? "badge amber" : ""}">${reply.due}</span><b>${reply.value}</b></div></div>`).join("") || `<div class="empty">No replies here</div>`}</div>`).join("")}</div></section>`);
}

function products() {
  return page("Advertising products", "Manage page positions, prices, and available inventory.", `<a class="button primary" href="/campaigns.html">＋ Add product</a>`).replace("</section>", `<div class="panel table-wrap"><table><thead><tr><th>Product</th><th>Position</th><th>Standard price</th><th>Available</th><th>Sold</th><th>Status</th></tr></thead><tbody>${state.products.map((product) => `<tr><td><strong>${product.name}</strong></td><td>${product.position}</td><td><strong>${product.price}</strong></td><td>${product.available}</td><td>${product.sold}</td><td><span class="badge ${product.status === "Active" ? "green" : "amber"}">${product.status}</span></td></tr>`).join("")}</tbody></table></div></section>`);
}

function opportunities() {
  return page("Opportunities", "Track advertising sales from proposal to payment.", `<button class="button primary" data-toast="Opportunity creation will be saved in the MariaDB database.">＋ New opportunity</button>`).replace("</section>", `<div class="stats-grid"><div class="stat-card"><span class="stat-label">Open opportunities</span><strong class="stat-value">18</strong><span class="trend">7 follow-ups due</span></div><div class="stat-card"><span class="stat-label">Expected value</span><strong class="stat-value">142 500 kr</strong><span class="trend">Across 2 campaigns</span></div><div class="stat-card"><span class="stat-label">Confirmed</span><strong class="stat-value">68 400 kr</strong><span class="trend">12 confirmed sales</span></div></div><div class="panel table-wrap"><table><thead><tr><th>Company</th><th>Campaign</th><th>Product</th><th>Value</th><th>Stage</th><th>Payment</th></tr></thead><tbody>${state.replies.map((reply, index) => `<tr><td><strong>${reply.company}</strong><small>${reply.person}</small></td><td>2027 Sports Catalog</td><td>${index % 2 ? "Half page" : "Full page"}</td><td><strong>${reply.value}</strong></td><td><span class="badge blue">${reply.stage}</span></td><td><span class="badge ${index === 1 ? "green" : "amber"}">${index === 1 ? "Paid" : "Not invoiced"}</span></td></tr>`).join("")}</tbody></table></div></section>`);
}

function settings() {
  return page("Settings", "Configuration visible to administrators.", `<a class="button primary" href="/settings.html">⚙ Office 365 connection (live)</a>`).replace("</section>", `<div class="dashboard-grid"><div class="panel"><div class="panel-header"><h3>Workspace configuration</h3></div><label>Organization name<input value="Paradisskolekorps" disabled></label><label>Campaign mailbox<input value="mailings@paradisskolekorps.no" disabled></label><label>Default batch size<input value="50" disabled></label></div><div class="panel"><div class="panel-header"><h3>Access and security</h3></div><div class="activity"><div class="activity-icon">✓</div><div><strong>Microsoft Entra ID</strong><p>Connected in the planned production setup</p></div></div><div class="activity"><div class="activity-icon">✓</div><div><strong>Secrets protection</strong><p>No secrets are stored in the mockup</p></div></div></div></div></section>`);
}

const views = { dashboard, companies, contacts, groups, campaigns, replies, products, opportunities, settings };

function showToast(message) {
  const toast = $("#toast");
  toast.textContent = message;
  toast.classList.add("visible");
  window.clearTimeout(showToast.timeout);
  showToast.timeout = window.setTimeout(() => toast.classList.remove("visible"), 2800);
}

async function loadLiveCampaigns() {
  const box = $("#live-campaigns");
  if (!box) return;
  try {
    const res = await fetch("/api/campaigns?limit=200");
    if (!res.ok) throw new Error(String(res.status));
    const list = (await res.json()).data;
    if (!list.length) { box.textContent = "No campaigns yet. Use “New campaign”."; return; }
    const summaries = await Promise.all(list.map((c) => fetch(`/api/campaigns/${c.id}/summary`).then((r) => r.json()).then((j) => j.data)));
    const kr = (v) => Number(v).toLocaleString("nb-NO", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = (v) => String(v).replace(/[&<>"']/g, (ch) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[ch]);
    box.className = "";
    box.innerHTML = `<table><thead><tr><th>Campaign</th><th>Status</th><th>Products</th><th>Reserved</th><th>Sold</th><th>Sold value</th><th></th></tr></thead><tbody>${list.map((c, i) => {
      const t = summaries[i].totals;
      return `<tr><td><strong>${esc(c.name)}</strong></td><td><span class="badge ${c.status === "active" ? "green" : c.status === "draft" ? "amber" : "blue"}">${esc(c.status)}</span></td><td>${summaries[i].products.length}</td><td>${t.reserved}</td><td>${t.sold}</td><td><strong>${kr(t.revenue_sold)}</strong></td><td><a class="button secondary small" href="/campaigns.html#${c.id}">Open</a></td></tr>`;
    }).join("")}</tbody></table>`;
  } catch (e) {
    box.textContent = "Live data is only available when this page is opened from the app server (http://127.0.0.1:8081/app/).";
  }
}

function bindViewEvents() {
  if (state.view === "campaigns") loadLiveCampaigns();
  document.querySelectorAll("[data-view-action]").forEach((button) => button.addEventListener("click", () => { state.view = button.dataset.viewAction; render(); }));
  document.querySelectorAll("[data-toast]").forEach((button) => button.addEventListener("click", () => showToast(button.dataset.toast)));
  document.querySelectorAll("[data-open-company]").forEach((button) => button.addEventListener("click", () => { $("#company-modal").hidden = false; }));
  const search = $("[data-table-search]");
  if (search) search.addEventListener("input", () => {
    const query = search.value.toLowerCase();
    $("#company-rows").innerHTML = companyRows(state.companies.filter((company) => Object.values(company).join(" ").toLowerCase().includes(query)));
  });
  const contactSearch = $("[data-contact-search]");
  if (contactSearch) contactSearch.addEventListener("input", () => {
    const query = contactSearch.value.toLowerCase();
    $("#contact-rows").innerHTML = contactRows(state.contacts.filter((contact) => Object.values(contact).join(" ").toLowerCase().includes(query)));
  });
}

document.querySelectorAll(".nav-item[data-view]").forEach((item) => item.addEventListener("click", () => { state.view = item.dataset.view; render(); }));
document.addEventListener("click", (event) => {
  if (event.target.matches("[data-close-modal]")) $("#company-modal").hidden = true;
});
$("#company-form").addEventListener("submit", (event) => {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  const name = data.get("name");
  state.companies.unshift({ name, category: data.get("category"), city: data.get("city"), contact: data.get("contact"), email: data.get("email"), status: "Active", tag: "New", initials: initials(name), color: "avatar-purple" });
  event.currentTarget.reset();
  $("#company-modal").hidden = true;
  state.view = "companies";
  render();
  showToast(`${name} was added to the customer database.`);
});

render();

<script>
let selectedUser = null;
let userRowIndex = {{ count($userPermissions) }};

const searchInput = document.getElementById('user-search');
const resultsBox = document.getElementById('user-search-results');
const addBtn = document.getElementById('add-user-btn');

searchInput.addEventListener('input', async function() {
    const q = this.value.trim();
    if (q.length < 2) { resultsBox.style.display = 'none'; return; }
    const res = await fetch('/habau/users/search?q=' + encodeURIComponent(q));
    const users = await res.json();
    if (users.length === 0) { resultsBox.style.display = 'none'; return; }
    resultsBox.innerHTML = users.map(u => `
        <div style="padding:10px 14px; cursor:pointer; border-bottom:1px solid var(--color-border); display:flex; align-items:center; gap:10px;"
             onmousedown="selectUser(${u.id}, '${u.name.replace(/'/g, "\\'")}', '${u.email.replace(/'/g, "\\'")}', '${u.avatar}')">
            <img src="${u.avatar}" style="width:32px;height:32px;border-radius:50%;flex-shrink:0;">
            <div><strong>${u.name}</strong><br><small style="color:var(--color-text-muted)">${u.email}</small></div>
        </div>
    `).join('');
    resultsBox.style.display = 'block';
});

function selectUser(id, name, email, avatar) {
    selectedUser = {id, name, email, avatar};
    searchInput.value = name;
    resultsBox.style.display = 'none';
    addBtn.style.display = 'inline-flex';
}

addBtn.addEventListener('click', function() {
    if (!selectedUser) return;
    const list = document.getElementById('user-permissions-list');
    const i = userRowIndex++;
    const div = document.createElement('div');
    div.className = 'item-list-row flex-container-row justify-space-between wrap items-center user-perm-row';
    div.innerHTML = `
        <div class="flex px-l py-m flex-container-row items-center gap-m">
            <img src="${selectedUser.avatar}" style="width:32px;height:32px;border-radius:50%;">
            <div><strong>${selectedUser.name}</strong><br><small class="text-muted">${selectedUser.email}</small></div>
            <input type="hidden" name="user_permissions[${i}][user_id]" value="${selectedUser.id}">
        </div>
        <div class="flex-container-row justify-space-between gap-x-xl wrap items-center">
            <div class="px-l"><label class="flex-container-row gap-xs items-center"><input type="checkbox" name="user_permissions[${i}][view]" value="1" checked><span>Anzeigen</span></label></div>
            <div class="px-l"><label class="flex-container-row gap-xs items-center"><input type="checkbox" name="user_permissions[${i}][create]" value="1"><span>Erstellen</span></label></div>
            <div class="px-l"><label class="flex-container-row gap-xs items-center"><input type="checkbox" name="user_permissions[${i}][update]" value="1"><span>Bearbeiten</span></label></div>
            <div class="px-l"><label class="flex-container-row gap-xs items-center"><input type="checkbox" name="user_permissions[${i}][delete]" value="1"><span>Löschen</span></label></div>
            <div class="px-m"><button type="button" class="text-neg p-m icon-button" onclick="removeUserRow(this)">✕</button></div>
        </div>
    `;
    list.appendChild(div);
    selectedUser = null;
    searchInput.value = '';
    addBtn.style.display = 'none';
});

function removeUserRow(btn) {
    btn.closest('.user-perm-row').remove();
}
</script>
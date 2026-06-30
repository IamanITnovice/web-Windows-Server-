/**
 * WatchDog 主题的主脚本，所有全局功能都在这了
 */

// 封装了一下 AJAX 调用，省得每次写 fetch 写到手酸
const WD_API = {
    async ajax(action, params = {}, method = 'GET') {
        const base = { action, nonce: WD.nonce };
        if (method === 'GET') {
            const qs = new URLSearchParams({ ...base, ...params }).toString();
            const r  = await fetch(WD.ajax_url + '?' + qs);
            return r.json();
        }
        const body = new URLSearchParams({ ...base, ...params });
        const r    = await fetch(WD.ajax_url, { method: 'POST', body });
        return r.json();
    },
};

// 把工具方法合并到 WD 对象上，全局调用方便
Object.assign(WD, {
    ajax: WD_API.ajax.bind(WD_API),
});

// Toast 弹 toast，提示成功还是失败就靠它了
WD.toast = function(msg, type = 'success', duration = 3500) {
    const container = document.getElementById('wd-toasts') || (() => {
        const el = document.createElement('div');
        el.id = 'wd-toasts';
        el.className = 'wd-toast-container';
        document.body.appendChild(el);
        return el;
    })();

    const el  = document.createElement('div');
    el.className = `wd-toast wd-toast--${type}`;
    el.innerHTML = `<span class="wd-toast-icon">${type === 'error' ? '✕' : '✓'}</span><span>${msg}</span>`;
    container.appendChild(el);
    requestAnimationFrame(() => el.classList.add('wd-toast--show'));

    setTimeout(() => {
        el.classList.remove('wd-toast--show');
        el.addEventListener('transitionend', () => el.remove(), { once: true });
    }, duration);
};

// 确认框，"你确定要删吗？"——不，我不确定，但你还是得点一下
WD.confirm = function(msg, onConfirm, title = '确认操作') {
    const modal = document.getElementById('wd-confirm-modal');
    document.getElementById('wd-confirm-title').textContent = title;
    document.getElementById('wd-confirm-msg').textContent   = '';
    document.getElementById('wd-confirm-msg').innerHTML     = msg;
    modal.style.display = 'flex';

    const okBtn     = document.getElementById('wd-confirm-ok');
    const cancelBtn = document.getElementById('wd-confirm-cancel');

    const cleanup = () => { modal.style.display = 'none'; };
    okBtn.onclick = () => { cleanup(); onConfirm(); };
    cancelBtn.onclick = cleanup;
};

// Modal 弹窗辅助，打开关闭就两行，省心
window.openModal  = (id) => { document.getElementById(id).style.display = 'flex'; };
window.closeModal = (id) => { document.getElementById(id).style.display = 'none'; };

// HTML 转义，防止 XSS，能防一点是一点
window.escHtml = function(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
};

// 分页组件，总页数、当前页传进来就能画，带省略号那种
window.renderPagination = function(total, currentPage, perPage, loadFn, containerId) {
    const totalPages = Math.ceil(total / perPage);
    const el = document.getElementById(containerId);
    if (!el) return;
    if (totalPages <= 1) { el.innerHTML = ''; return; }

    const pages = [];
    pages.push({ label: '‹', page: currentPage - 1, disabled: currentPage === 1 });

    let s = Math.max(1, currentPage - 2), e = Math.min(totalPages, s + 4);
    if (e - s < 4) s = Math.max(1, e - 4);

    if (s > 1)          pages.push({ label: '1', page: 1 });
    if (s > 2)          pages.push({ label: '…', page: null });
    for (let p = s; p <= e; p++) pages.push({ label: String(p), page: p });
    if (e < totalPages - 1) pages.push({ label: '…', page: null });
    if (e < totalPages) pages.push({ label: String(totalPages), page: totalPages });

    pages.push({ label: '›', page: currentPage + 1, disabled: currentPage === totalPages });

    el.innerHTML = pages.map(({ label, page, disabled }) => {
        if (page === null) return `<span class="wd-page-ellipsis">${label}</span>`;
        const active = page === currentPage ? ' wd-page-btn--active' : '';
        const dis    = disabled ? ' disabled' : '';
        const onclick = disabled ? '' : `onclick="${loadFn.name}(${page})"`;
        return `<button class="wd-page-btn${active}${dis}" ${disabled ? 'disabled' : ''} ${onclick}>${label}</button>`;
    }).join('');
};

// 顶栏走个时钟，实时显示时间，用户瞄一眼就知道几点了
(function initClock() {
    const el = document.getElementById('wd-topbar-time');
    if (!el) return;
    function tick() { el.textContent = new Date().toLocaleTimeString('zh-CN', { hour12: false }); }
    tick();
    setInterval(tick, 1000);
})();

// 每 30 秒拉一次在线主机数，首页那个数字就是它刷新的
(async function refreshOnlineCount() {
    async function fetch() {
        const res = await WD.ajax('wd_get_hosts', { page: 1, status: 'online' });
        if (!res.success) return;
        const el = document.getElementById('wd-online-count');
        if (el) el.textContent = res.data.total;
    }
    fetch();
    setInterval(fetch, 30000);
})();

// 移动端点遮罩关侧边栏，不然点外面也关不掉，体验太差了
document.addEventListener('click', (e) => {
    const sidebar = document.getElementById('wd-sidebar');
    if (!sidebar) return;
    if (sidebar.classList.contains('wd-sidebar--open') &&
        !sidebar.contains(e.target) &&
        !e.target.closest('.wd-menu-toggle')) {
        sidebar.classList.remove('wd-sidebar--open');
    }
});

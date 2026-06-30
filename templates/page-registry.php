<?php
defined('ABSPATH') || exit;
include WD_THEME_DIR . '/templates/partials/layout-open.php';
$host_id = (int)($_GET['host_id'] ?? 0);

$roots = ['HKEY_LOCAL_MACHINE','HKEY_CURRENT_USER','HKEY_CLASSES_ROOT','HKEY_USERS','HKEY_CURRENT_CONFIG'];

// Same deterministic ID function used in JS's pathId()
function wd_reg_id(string $path): string {
    return 'rk-' . preg_replace('/[^a-zA-Z0-9_]/', '-', $path);
}
?>

<div class="wd-page">
  <div class="wd-page-header">
    <h1 class="wd-page-title">注册表管理</h1>
    <div class="wd-header-right">
      <select id="reg-host" class="wd-select" title="选择主机" aria-label="选择主机" onchange="selectRegHost(this.value)">
        <option value="">选择主机...</option>
      </select>
      <button class="wd-btn wd-btn--ghost"    id="btn-pull-tree" style="display:none" onclick="pullTree()">⟳ 拉取树</button>
      <button class="wd-btn wd-btn--primary"  id="btn-reg-add"   style="display:none" onclick="openModal('reg-add-modal')">+ 新建值</button>
    </div>
  </div>

  <div class="wd-two-col wd-two-col--30-70">

    <!-- 树形面板 -->
    <div class="wd-card" style="height:600px;overflow:hidden;display:flex;flex-direction:column">
      <div class="wd-card-header"><h2>键树</h2></div>
      <div class="wd-reg-tree" id="reg-tree" style="overflow-y:auto;flex:1;padding:8px 0">
        <?php foreach ($roots as $r):
            $rid = wd_reg_id($r); ?>
          <div class="wd-tree-node wd-tree-root" data-path="<?= $r ?>" onclick="loadRegKey('<?= $r ?>')">
            <span class="wd-tree-arrow" id="arrow-<?= $rid ?>">▶</span> <?= $r ?>
          </div>
          <div class="wd-tree-children" id="children-<?= $rid ?>" style="display:none"></div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 值面板 -->
    <div class="wd-card">
      <div class="wd-card-header">
        <h2 class="wd-mono wd-sm" id="reg-current-key">选择键</h2>
        <span class="wd-sm wd-muted" id="reg-host-hint">请先选择主机</span>
      </div>
      <div class="wd-card-body wd-p0">
        <table class="wd-table wd-table--hover">
          <thead><tr><th>值名称</th><th>类型</th><th>数据</th><th>操作</th></tr></thead>
          <tbody id="reg-values-tbody"><tr><td colspan="4" class="wd-center wd-muted">请展开左侧树选择键</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- 添加/编辑弹窗 -->
<div class="wd-modal" id="reg-add-modal" style="display:none">
  <div class="wd-modal-backdrop" onclick="closeModal('reg-add-modal')"></div>
  <div class="wd-modal-dialog">
    <div class="wd-modal-header"><h3 id="reg-modal-title">新建注册表值</h3><button class="wd-modal-close" onclick="closeModal('reg-add-modal')">✕</button></div>
    <div class="wd-modal-body">
      <input type="hidden" id="reg-edit-path">
      <div class="wd-form-group"><label class="wd-label">值名称</label><input type="text" class="wd-input" id="reg-val-name" placeholder="留空 = (默认值)"></div>
      <div class="wd-form-group">
        <label class="wd-label">类型</label>
        <select class="wd-select" id="reg-val-type" style="width:100%">
          <option value="REG_SZ">REG_SZ（字符串）</option>
          <option value="REG_DWORD">REG_DWORD（32位整数）</option>
          <option value="REG_QWORD">REG_QWORD（64位整数）</option>
          <option value="REG_EXPAND_SZ">REG_EXPAND_SZ（可扩展字符串）</option>
          <option value="REG_MULTI_SZ">REG_MULTI_SZ（多字符串）</option>
        </select>
      </div>
      <div class="wd-form-group"><label class="wd-label">数据</label><input type="text" class="wd-input wd-mono" id="reg-val-data" placeholder="值内容"></div>
    </div>
    <div class="wd-modal-footer">
      <button class="wd-btn wd-btn--ghost" onclick="closeModal('reg-add-modal')">取消</button>
      <button class="wd-btn wd-btn--primary" onclick="saveRegValue()">保存</button>
    </div>
  </div>
</div>

<script>
document.getElementById('wd-topbar-title').textContent = '注册表管理';
const INIT_HOST = <?= $host_id ?>;
let regHostId = INIT_HOST, currentRegKey = '';

// 与 PHP wd_reg_id() 完全一致：将路径转为合法 HTML id
function pathId(path) { return 'rk-' + path.replace(/[^a-zA-Z0-9_]/g, '-'); }

async function initHosts() {
    const res = await WD.ajax('wd_get_hosts', { page: 1 });
    if (!res.success) return;
    const sel = document.getElementById('reg-host');
    res.data.items.forEach(h => {
        const o = document.createElement('option');
        o.value = h.id; o.textContent = h.name;
        if (h.id == INIT_HOST) o.selected = true;
        sel.appendChild(o);
    });
    if (INIT_HOST) selectRegHost(INIT_HOST);
}

function selectRegHost(id) {
    regHostId = parseInt(id)||0;
    document.getElementById('reg-host-hint').textContent = regHostId ? '已选主机' : '请先选择主机';
    document.getElementById('btn-reg-add').style.display   = regHostId ? '' : 'none';
    document.getElementById('btn-pull-tree').style.display = regHostId ? '' : 'none';
    if (regHostId) pullTree();
}

// 一次性拉取所有根键的第一层子键
async function pullTree() {
    if (!regHostId) return WD.toast('请先选择主机','error');
    WD.toast('正在拉取注册表结构...','success',2000);
    const psCmd = `$o=@{};`
        + `@('HKEY_LOCAL_MACHINE','HKEY_CURRENT_USER','HKEY_CLASSES_ROOT','HKEY_USERS','HKEY_CURRENT_CONFIG')`
        + `|%{$r=$_;try{$o[$r]=@(Get-ChildItem "Registry::$r" -EA SilentlyContinue|%{$_.PSChildName})}catch{$o[$r]=@()}};`
        + `$o|ConvertTo-Json -Compress -Depth 2`;
    const res = await WD.ajax('wd_send_command', { host_id: regHostId, cmd_type: 'powershell', payload: psCmd }, 'POST');
    if (!res.success) return WD.toast('指令失败','error');
    pollCmdResult(res.data.cmd_id, result => {
        try {
            const tree = JSON.parse(result || '{}');
            Object.keys(tree).forEach(rootKey => {
                const subs = tree[rootKey];
                renderSubKeys(rootKey, Array.isArray(subs) ? subs : (subs ? [subs] : []));
            });
            WD.toast('注册表树加载完成');
        } catch(e) { WD.toast('解析失败: ' + e.message,'error'); }
    });
}

async function loadRegKey(path) {
    if (!regHostId) return WD.toast('请先选择主机','error');
    currentRegKey = path;
    document.getElementById('reg-current-key').textContent = path;
    document.getElementById('reg-values-tbody').innerHTML = '<tr><td colspan="4" class="wd-center wd-muted">加载中...</td></tr>';

    const psRegPath = 'Registry::' + path;
    const isRoot = path.indexOf('\\') === -1;

    let psCmd;
    if (isRoot) {
        psCmd = `$s=@(Get-ChildItem -LiteralPath '${psRegPath}' -EA SilentlyContinue|%{$_.PSChildName});`
              + `[PSCustomObject]@{subkeys=$s;values=@()}|ConvertTo-Json -Compress -Depth 3`;
    } else {
        psCmd = `try{`
              + `$item=Get-Item -LiteralPath '${psRegPath}';`
              + `$v=@($item.GetValueNames()|%{$n=$_;[PSCustomObject]@{name=$n;type=$item.GetValueKind($n).ToString();data=[string]$item.GetValue($n)}});`
              + `$s=@(Get-ChildItem -LiteralPath '${psRegPath}' -EA SilentlyContinue|%{$_.PSChildName});`
              + `[PSCustomObject]@{values=$v;subkeys=$s}|ConvertTo-Json -Compress -Depth 3`
              + `}catch{[PSCustomObject]@{values=@();subkeys=@();error=$_.Exception.Message}|ConvertTo-Json -Compress}`;
    }

    const res = await WD.ajax('wd_send_command', { host_id: regHostId, cmd_type: 'powershell', payload: psCmd }, 'POST');
    if (!res.success) return WD.toast('指令失败','error');

    pollCmdResult(res.data.cmd_id, result => {
        try {
            const data = JSON.parse(result || '{}');
            if (data.error) return WD.toast('注册表错误: ' + data.error, 'error');
            renderRegValues(data.values || []);
            renderSubKeys(path, data.subkeys || []);
        } catch(e) { WD.toast('解析失败: ' + e.message,'error'); }
    });
}

function renderRegValues(values) {
    const tbody = document.getElementById('reg-values-tbody');
    tbody.innerHTML = values.length ? values.map(v => `
        <tr>
          <td class="wd-mono wd-sm">${escHtml(v.name||'(默认)')}</td>
          <td><span class="wd-badge">${escHtml(v.type)}</span></td>
          <td class="wd-mono wd-sm" style="max-width:300px;overflow:hidden;text-overflow:ellipsis">${escHtml(String(v.data).slice(0,200))}</td>
          <td>
            <button class="wd-btn wd-btn--xs wd-btn--ghost" onclick="editRegValue('${escHtml(v.name||'').replace(/'/g,"\\'")}','${escHtml(v.type)}','${escHtml(String(v.data)).replace(/'/g,"\\'")}')">编辑</button>
            <button class="wd-btn wd-btn--xs wd-btn--danger" onclick="deleteRegValue('${escHtml(v.name||'').replace(/'/g,"\\'")}')">删除</button>
          </td>
        </tr>`).join('')
      : '<tr><td colspan="4" class="wd-center wd-muted">（无值）</td></tr>';
}

function renderSubKeys(parentPath, subkeys) {
    const pid = pathId(parentPath);
    let el = document.getElementById('children-' + pid);
    if (!el) {
        // 动态创建（根键的 children div 已在 PHP 中预渲染）
        el = document.createElement('div');
        el.id = 'children-' + pid;
        el.className = 'wd-tree-children';
        const parentNode = document.querySelector(`[data-path="${parentPath.replace(/\\/g,'\\\\').replace(/"/g,'\\"')}"]`);
        if (parentNode && parentNode.nextElementSibling) {
            parentNode.parentNode.insertBefore(el, parentNode.nextElementSibling);
        } else {
            document.getElementById('reg-tree').appendChild(el);
        }
    }
    const indent = (parentPath.split('\\').length) * 14;
    el.innerHTML = subkeys.map(k => {
        const childPath = parentPath + '\\' + k;
        const cid = pathId(childPath);
        const safeOnclick = childPath.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
        return `<div class="wd-tree-node wd-tree-child" style="padding-left:${indent}px"
            data-path="${escHtml(childPath)}" onclick="loadRegKey('${safeOnclick}')">
            <span class="wd-tree-arrow" id="arrow-${cid}">▶</span> ${escHtml(k)}
        </div>
        <div class="wd-tree-children" id="children-${cid}" style="display:none"></div>`;
    }).join('');
    el.style.display = 'block';
    const arrow = document.getElementById('arrow-' + pid);
    if (arrow) arrow.textContent = '▼';
}

function editRegValue(name, type, data) {
    document.getElementById('reg-modal-title').textContent = '编辑注册表值';
    document.getElementById('reg-edit-path').value  = currentRegKey;
    document.getElementById('reg-val-name').value   = name;
    document.getElementById('reg-val-type').value   = type;
    document.getElementById('reg-val-data').value   = data;
    openModal('reg-add-modal');
}

async function saveRegValue() {
    const regPath = document.getElementById('reg-edit-path').value || currentRegKey;
    const name    = document.getElementById('reg-val-name').value.trim();
    const type    = document.getElementById('reg-val-type').value;
    const data    = document.getElementById('reg-val-data').value;
    const psPath  = 'Registry::' + regPath;
    const typeMap = { REG_SZ:'String', REG_DWORD:'DWord', REG_QWORD:'QWord', REG_EXPAND_SZ:'ExpandString', REG_MULTI_SZ:'MultiString' };
    const psType  = typeMap[type] || 'String';
    const psValue = (type === 'REG_DWORD' || type === 'REG_QWORD') ? data : `'${data.replace(/'/g,"''")}'`;
    const psCmd   = `Set-ItemProperty -LiteralPath '${psPath}' -Name '${name.replace(/'/g,"''")}' -Value ${psValue} -Type ${psType}; Write-Output "OK"`;
    const res = await WD.ajax('wd_send_command', { host_id: regHostId, cmd_type: 'powershell', payload: psCmd }, 'POST');
    closeModal('reg-add-modal');
    if (res.success) { WD.toast('写入指令已发送'); setTimeout(()=>loadRegKey(currentRegKey), 3000); }
    else WD.toast('失败','error');
}

async function deleteRegValue(name) {
    WD.confirm('确定删除值 "' + (name||'(默认)') + '"？', async () => {
        const psCmd = `Remove-ItemProperty -LiteralPath 'Registry::${currentRegKey}' -Name '${name.replace(/'/g,"''")}' -Force; Write-Output "OK"`;
        const res = await WD.ajax('wd_send_command', { host_id: regHostId, cmd_type: 'powershell', payload: psCmd }, 'POST');
        if (res.success) { WD.toast('删除指令已发送'); setTimeout(()=>loadRegKey(currentRegKey), 3000); }
        else WD.toast('失败','error');
    });
}

function pollCmdResult(cmdId, cb, n=0) {
    if (n>30) return WD.toast('响应超时（客户端可能离线）','error');
    setTimeout(async () => {
        const r = await WD.ajax('wd_get_cmd_result',{cmd_id:cmdId});
        if (r.success && r.data.status==='ack') cb(r.data.result);
        else if (r.success && ['pending','sent'].includes(r.data.status)) pollCmdResult(cmdId,cb,n+1);
        else if (r.success && r.data.status==='failed') WD.toast('客户端执行失败: ' + (r.data.result||''), 'error');
        else WD.toast('指令失败', 'error');
    }, 1500);
}

initHosts();

window.wdTermPaste = function(cmd) {
    navigator.clipboard?.writeText(cmd).then(() => WD.toast('命令已复制到剪贴板')).catch(() => alert('命令：\n' + cmd));
};
</script>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>

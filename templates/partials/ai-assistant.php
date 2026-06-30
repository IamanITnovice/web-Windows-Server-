<?php
defined('ABSPATH') || exit;
// AI 助手悬浮窗 - 通用片段
// 使用前设置 $ai_action，如 'wd_ai_chat' 或 'watchdog_ai_chat'
$ai_action = $ai_action ?? 'wd_ai_chat';
?>
<style>
/* ── AI 整体容器：按钮 + 面板一起拖动 ── */
#wd-ai-wrap {
    position: fixed; bottom: 24px; right: 24px;
    z-index: 9000; display: flex; flex-direction: column; align-items: flex-end;
}

#wd-ai-btn {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #6e40c9 0%, #0f52ba 100%);
    border: none; cursor: grab; font-size: 18px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(110,64,201,0.55);
    transition: transform 0.18s, box-shadow 0.18s;
    color: #fff; flex-shrink: 0;
}
#wd-ai-btn:hover { transform: scale(1.12); box-shadow: 0 6px 24px rgba(110,64,201,0.75); }
#wd-ai-btn.open  { background: linear-gradient(135deg, #8b5cf6 0%, #3b82f6 100%); }
#wd-ai-btn:active { cursor: grabbing; }

#wd-ai-panel {
    margin-bottom: 10px;
    width: 320px; max-height: 460px;
    background: #13131f; border: 1px solid rgba(110,64,201,0.4);
    border-radius: 14px; display: none; flex-direction: column;
    box-shadow: 0 12px 40px rgba(0,0,0,0.65);
    overflow: hidden;
}
#wd-ai-panel.open { display: flex; }

.wd-ai-hd {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 14px;
    background: linear-gradient(135deg, rgba(110,64,201,0.28), rgba(15,82,186,0.28));
    border-bottom: 1px solid rgba(255,255,255,0.08); flex-shrink: 0;
    -webkit-user-select: none;
    user-select: none;
}
.wd-ai-hd-title { font-size: 13px; font-weight: 700; color: #d4c8ff; }
.wd-ai-hd-close {
    background: none; border: none; color: #888; cursor: pointer;
    font-size: 14px; padding: 0 4px; line-height: 1;
}
.wd-ai-hd-close:hover { color: #fff; }

.wd-ai-chips {
    padding: 7px 10px 5px; display: flex; flex-wrap: wrap; gap: 5px;
    flex-shrink: 0; border-bottom: 1px solid rgba(255,255,255,0.06);
}
.wd-ai-chip {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px; padding: 2px 10px; font-size: 11px;
    cursor: pointer; color: #7a8eaa; transition: all 0.15s; white-space: nowrap;
}
.wd-ai-chip:hover { background: rgba(110,64,201,0.25); color: #c8b8ff; border-color: #6e40c9; }

#wd-ai-msgs {
    flex: 1; overflow-y: auto; padding: 10px;
    display: flex; flex-direction: column; gap: 8px; min-height: 100px;
}
#wd-ai-msgs::-webkit-scrollbar { width: 4px; }
#wd-ai-msgs::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }

.wd-ai-msg {
    padding: 7px 10px; border-radius: 8px; font-size: 12px;
    line-height: 1.55; max-width: 94%; word-break: break-word;
}
.wd-ai-msg--user      { background: rgba(110,64,201,0.28); align-self: flex-end; color: #e0d4ff; white-space: pre-wrap; }
.wd-ai-msg--assistant { background: rgba(255,255,255,0.06); align-self: flex-start; color: #c4d8f0; }
.wd-ai-msg--error     { background: rgba(239,68,68,0.18); align-self: flex-start; color: #fca5a5; white-space: pre-wrap; }
.wd-ai-msg--info      { background: rgba(16,185,129,0.14); align-self: flex-start; color: #6ee7b7; white-space: pre-wrap; }

/* AI 代码块 */
.wd-ai-cmd {
    background: rgba(0,0,0,0.45); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px; padding: 7px 8px; margin: 4px 0;
    display: flex; align-items: flex-start; gap: 7px;
}
.wd-ai-cmd code {
    flex: 1; font-family: 'Cascadia Code','Consolas','Courier New',monospace;
    font-size: 11px; color: #a8e6a8; white-space: pre-wrap; word-break: break-all;
}
.wd-ai-use-btn {
    flex-shrink: 0; background: rgba(110,64,201,0.35);
    border: 1px solid rgba(110,64,201,0.5); border-radius: 4px;
    color: #c8b8ff; cursor: pointer; font-size: 10px;
    padding: 2px 7px; white-space: nowrap; transition: background 0.15s;
}
.wd-ai-use-btn:hover { background: rgba(110,64,201,0.7); }

@keyframes wdAiDots { 0%,80%,100%{opacity:.3} 40%{opacity:1} }
.wd-ai-typing { opacity: .7; }
.wd-ai-typing::after { content:' · · ·'; animation: wdAiDots 1.2s infinite; }

.wd-ai-input-row {
    display: flex; padding: 8px 10px; gap: 7px;
    border-top: 1px solid rgba(255,255,255,0.08); flex-shrink: 0;
}
#wd-ai-input {
    flex: 1; background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1); border-radius: 7px;
    color: #dde4f0; padding: 6px 10px; font-size: 12px;
    outline: none; font-family: inherit;
}
#wd-ai-input::placeholder { color: #3a4a5e; }
#wd-ai-input:focus { border-color: rgba(110,64,201,0.65); }
.wd-ai-send {
    padding: 6px 12px; background: #6e40c9; border: none;
    border-radius: 7px; color: #fff; cursor: pointer;
    font-size: 12px; font-weight: 600; transition: background 0.15s;
}
.wd-ai-send:hover { background: #8b5cf6; }
.wd-ai-send:disabled { background: #3a2f5e; cursor: not-allowed; }
</style>

<!-- AI 整体容器（按钮 + 面板，整体可拖动） -->
<div id="wd-ai-wrap">
<!-- AI 聊天面板 -->
<div id="wd-ai-panel">
  <div class="wd-ai-hd">
    <span class="wd-ai-hd-title">🤖 AI 终端助手 · DeepSeek</span>
    <div style="display:flex;gap:6px;align-items:center">
      <button class="wd-ai-hd-close" style="font-size:11px;padding:2px 7px;border-radius:4px;border:1px solid rgba(255,255,255,0.15)" onclick="wdAI.clearHistory()" title="清除对话上下文">清除</button>
      <button class="wd-ai-hd-close" onclick="wdAI.toggle()">✕</button>
    </div>
  </div>
  <div class="wd-ai-chips">
    <span class="wd-ai-chip" onclick="wdAI.quickAsk('列出目录文件')">列目录</span>
    <span class="wd-ai-chip" onclick="wdAI.quickAsk('上传文件到远端')">上传文件</span>
    <span class="wd-ai-chip" onclick="wdAI.quickAsk('结束指定进程')">结束进程</span>
    <span class="wd-ai-chip" onclick="wdAI.quickAsk('读取注册表键值')">注册表</span>
    <span class="wd-ai-chip" onclick="wdAI.quickAsk('查看系统信息')">系统信息</span>
    <span class="wd-ai-chip" onclick="wdAI.quickAsk('下载远端文件')">下载文件</span>
  </div>
  <div id="wd-ai-msgs"></div>
  <div class="wd-ai-input-row">
    <input id="wd-ai-input" type="text" placeholder="询问终端命令…" autocomplete="off"/>
    <button class="wd-ai-send" id="wd-ai-send-btn" onclick="wdAI.send()">发送</button>
  </div>
</div>
<!-- AI 悬浮按钮（在面板下方，整体随容器移动） -->
<button id="wd-ai-btn" onclick="wdAI.toggle()" title="AI 终端助手 (DeepSeek)">🤖</button>
</div>

<script>
const wdAI = {
    history: [],
    action: '<?= esc_js($ai_action) ?>',
    _open: false,

    toggle() {
        this._open = !this._open;
        const panel = document.getElementById('wd-ai-panel');
        const btn   = document.getElementById('wd-ai-btn');
        panel.classList.toggle('open', this._open);
        btn.classList.toggle('open', this._open);
        // 面板打开时隐藏按钮，关闭时恢复（自适应）
        btn.style.display = this._open ? 'none' : '';
        if (this._open) {
            document.getElementById('wd-ai-input').focus();
            if (!document.getElementById('wd-ai-msgs').firstChild) {
                this.addMsg('info', '你好！我是 AI 终端助手，由 DeepSeek 驱动。\n可以帮你生成 PowerShell / CMD 命令。\n点击上方标签快速提问，或直接输入需求。');
            }
        }
    },

    quickAsk(text) {
        const inp = document.getElementById('wd-ai-input');
        inp.value = '怎么用 PowerShell ' + text + '？给出命令示例';
        inp.focus();
    },

    renderText(container, text) {
        container.innerHTML = '';
        // Split by code fences (```...```)
        const parts = text.split(/(```[\s\S]*?```)/g);
        parts.forEach(part => {
            if (part.startsWith('```')) {
                const code = part.replace(/^```[^\n]*\n?/, '').replace(/```$/, '').trim();
                if (!code) return;
                const wrap = document.createElement('div');
                wrap.className = 'wd-ai-cmd';
                const pre = document.createElement('code');
                pre.textContent = code;
                const btn = document.createElement('button');
                btn.className = 'wd-ai-use-btn';
                btn.textContent = '使用此命令';
                btn.onclick = function() {
                    if (typeof window.wdTermPaste === 'function') {
                        window.wdTermPaste(code);
                        btn.textContent = '✓ 已放入';
                    } else {
                        navigator.clipboard?.writeText(code).then(() => { btn.textContent = '✓ 已复制'; });
                    }
                    setTimeout(() => { btn.textContent = '使用此命令'; }, 2000);
                };
                wrap.appendChild(pre);
                wrap.appendChild(btn);
                container.appendChild(wrap);
            } else if (part.trim()) {
                const span = document.createElement('span');
                span.style.whiteSpace = 'pre-wrap';
                span.textContent = part;
                container.appendChild(span);
            }
        });
    },

    addMsg(role, text) {
        const msgs = document.getElementById('wd-ai-msgs');
        const div  = document.createElement('div');
        div.className = 'wd-ai-msg wd-ai-msg--' + role;
        if (role === 'assistant') {
            this.renderText(div, text);
        } else {
            div.textContent = text;
        }
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
        return div;
    },

    async send() {
        const input = document.getElementById('wd-ai-input');
        const btn   = document.getElementById('wd-ai-send-btn');
        const msg   = input.value.trim();
        if (!msg || btn.disabled) return;

        input.value = '';
        btn.disabled = true;
        this.addMsg('user', msg);

        const typing = this.addMsg('assistant', '');
        typing.classList.add('wd-ai-typing');

        // 60 秒前端超时保险
        const controller = new AbortController();
        const tid = setTimeout(() => controller.abort(), 60000);

        try {
            const fd = new FormData();
            fd.append('action', this.action);
            fd.append('nonce',  WD.nonce);
            fd.append('message', msg);
            this.history.slice(-20).forEach((h, i) => {
                fd.append('history[' + i + '][role]',    h.role);
                fd.append('history[' + i + '][content]', h.content);
            });

            const res  = await fetch(WD.ajax_url, { method: 'POST', body: fd, signal: controller.signal });
            clearTimeout(tid);
            const data = await res.json();
            typing.remove();

            if (data.success) {
                const reply = data.data.reply;
                this.addMsg('assistant', reply);
                this.history.push({ role: 'user',      content: msg   });
                this.history.push({ role: 'assistant', content: reply });
                if (this.history.length > 40) this.history = this.history.slice(-40);
            } else {
                this.addMsg('error', '⚠️ ' + (data.data?.message || '请求失败，请重试'));
            }
        } catch (e) {
            clearTimeout(tid);
            typing.remove();
            if (e.name === 'AbortError') {
                this.addMsg('error', '⏱ 请求超时（>60s），DeepSeek 响应过慢，请稍后重试');
            } else {
                this.addMsg('error', '网络错误: ' + e.message);
            }
        }

        btn.disabled = false;
        document.getElementById('wd-ai-input').focus();
    },

    clearHistory() {
        this.history = [];
        const msgs = document.getElementById('wd-ai-msgs');
        msgs.innerHTML = '';
        this.addMsg('info', '对话已清除，上下文已重置。');
    }
};

document.getElementById('wd-ai-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); wdAI.send(); }
});

// ── AI 整体容器拖动（按钮 + 面板一起移动）────────────────────────
(function() {
    const wrap   = document.getElementById('wd-ai-wrap');
    const btn    = document.getElementById('wd-ai-btn');
    const header = document.querySelector('.wd-ai-hd');
    let dragging = false, ox = 0, oy = 0, didDrag = false;

    function startDrag(e) {
        if (e.target.classList.contains('wd-ai-hd-close')) return;
        dragging = true; didDrag = false;
        const r = wrap.getBoundingClientRect();
        ox = e.clientX - r.left;
        oy = e.clientY - r.top;
        // 切换为绝对定位，解除 right/bottom 锚点
        wrap.style.right  = 'auto';
        wrap.style.bottom = 'auto';
        wrap.style.left   = r.left + 'px';
        wrap.style.top    = r.top  + 'px';
        e.preventDefault();
    }

    btn.addEventListener('mousedown', startDrag);
    header.addEventListener('mousedown', startDrag);

    document.addEventListener('mousemove', function(e) {
        if (!dragging) return;
        didDrag = true;
        let left = e.clientX - ox;
        let top  = e.clientY - oy;
        left = Math.max(0, Math.min(left, window.innerWidth  - wrap.offsetWidth));
        top  = Math.max(0, Math.min(top,  window.innerHeight - wrap.offsetHeight));
        wrap.style.left = left + 'px';
        wrap.style.top  = top  + 'px';
    });
    document.addEventListener('mouseup', function() {
        dragging = false;
    });

    // 拖动结束后阻止 click 触发 toggle
    btn.addEventListener('click', function(e) {
        if (didDrag) { e.stopImmediatePropagation(); didDrag = false; }
    }, true);
})();
</script>

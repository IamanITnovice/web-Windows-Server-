<?php defined('ABSPATH') || exit; ?>
    </div><!-- .wd-content -->
  </main><!-- .wd-main -->
</div><!-- .wd-app -->

<!-- 全局 Toast 容器 -->
<div class="wd-toast-container" id="wd-toasts"></div>

<!-- 全局确认弹窗 -->
<div class="wd-modal" id="wd-confirm-modal" style="display:none">
  <div class="wd-modal-backdrop"></div>
  <div class="wd-modal-dialog wd-modal-dialog--sm">
    <div class="wd-modal-header">
      <h3 id="wd-confirm-title">确认操作</h3>
    </div>
    <div class="wd-modal-body">
      <p id="wd-confirm-msg"></p>
    </div>
    <div class="wd-modal-footer">
      <button class="wd-btn wd-btn--ghost" id="wd-confirm-cancel">取消</button>
      <button class="wd-btn wd-btn--danger" id="wd-confirm-ok">确认</button>
    </div>
  </div>
</div>

<?php
$ai_action = 'wd_ai_chat';
include WD_THEME_DIR . '/templates/partials/ai-assistant.php';
?>

<?php wp_footer(); ?>
</body>
</html>

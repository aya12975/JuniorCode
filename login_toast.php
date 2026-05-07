<?php if (!empty($_SESSION["login_success"])): unset($_SESSION["login_success"]); ?>
<div id="loginToast" style="
  position: fixed;
  top: 24px;
  right: 24px;
  z-index: 9999;
  background: #dcfce7;
  color: #166534;
  border: 1px solid #bbf7d0;
  border-radius: 16px;
  padding: 16px 22px;
  font-weight: 800;
  font-size: 0.97rem;
  box-shadow: 0 12px 32px rgba(22,101,52,0.13);
  display: flex;
  align-items: center;
  gap: 10px;
  animation: toastIn 0.4s ease;
">
  <i class="fas fa-circle-check" style="font-size:1.2rem;"></i>
  Login successful! Welcome back.
  <button onclick="document.getElementById('loginToast').style.display='none'" style="
    margin-left:10px; background:none; border:none; cursor:pointer;
    color:#166534; font-size:1.1rem; line-height:1;
  ">&times;</button>
</div>
<style>
@keyframes toastIn {
  from { opacity:0; transform: translateY(-16px); }
  to   { opacity:1; transform: translateY(0); }
}
</style>
<script>
setTimeout(function() {
  var t = document.getElementById('loginToast');
  if (t) { t.style.transition = 'opacity 0.5s'; t.style.opacity = '0'; setTimeout(function(){ t.style.display='none'; }, 500); }
}, 3500);
</script>
<?php endif; ?>

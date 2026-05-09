(function () {
  const css = `
#jc-logout-overlay {
  display:none; position:fixed; inset:0; z-index:99999;
  background:rgba(5,10,24,0.72); backdrop-filter:blur(6px);
  align-items:center; justify-content:center;
}
#jc-logout-overlay.show { display:flex; }
#jc-logout-box {
  background:linear-gradient(160deg,#0f172a 0%,#1e293b 100%);
  border:1px solid rgba(255,255,255,0.1);
  border-radius:28px; padding:44px 40px 36px;
  box-shadow:0 32px 80px rgba(0,0,0,0.55);
  width:100%; max-width:400px; text-align:center;
  animation:jcPop .24s cubic-bezier(.34,1.56,.64,1);
}
@keyframes jcPop { from{transform:scale(.88) translateY(12px);opacity:0} to{transform:scale(1) translateY(0);opacity:1} }
#jc-logout-box .jc-icon {
  width:72px; height:72px; border-radius:20px; margin:0 auto 22px;
  background:linear-gradient(135deg,#dc2626,#7f1d1d);
  box-shadow:0 12px 32px rgba(220,38,38,0.45);
  display:flex; align-items:center; justify-content:center;
  font-size:1.8rem; color:#fff;
}
#jc-logout-box h3 {
  font-size:1.4rem; font-weight:900; color:#f8fafc; margin:0 0 10px;
  letter-spacing:-0.3px;
}
#jc-logout-box p { color:rgba(255,255,255,0.5); font-size:0.93rem; margin:0 0 32px; line-height:1.6; }
#jc-logout-box .jc-divider { border:none; border-top:1px solid rgba(255,255,255,0.08); margin:0 0 24px; }
#jc-logout-box .jc-btns { display:flex; gap:12px; }
#jc-logout-box .jc-btn-stay {
  flex:1; padding:13px; border-radius:14px; font-weight:800; font-size:0.95rem;
  background:rgba(255,255,255,0.08); color:rgba(255,255,255,0.75);
  border:1px solid rgba(255,255,255,0.12); cursor:pointer;
  transition:all .2s; letter-spacing:0.2px;
}
#jc-logout-box .jc-btn-stay:hover { background:rgba(255,255,255,0.14); color:#fff; }
#jc-logout-box .jc-btn-out {
  flex:1; padding:13px; border-radius:14px; font-weight:900; font-size:0.95rem;
  background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff;
  border:none; cursor:pointer;
  box-shadow:0 8px 22px rgba(220,38,38,0.4);
  transition:opacity .2s, box-shadow .2s; letter-spacing:0.2px;
}
#jc-logout-box .jc-btn-out:hover { opacity:.9; box-shadow:0 12px 28px rgba(220,38,38,0.55); }
`;

  const styleEl = document.createElement('style');
  styleEl.textContent = css;
  document.head.appendChild(styleEl);

  const modal = document.createElement('div');
  modal.id = 'jc-logout-overlay';
  modal.innerHTML = `
    <div id="jc-logout-box">
      <div class="jc-icon"><i class="fas fa-right-from-bracket"></i></div>
      <h3>Log out?</h3>
      <p>You're about to end your session.<br>Any unsaved changes will be lost.</p>
      <hr class="jc-divider">
      <div class="jc-btns">
        <button class="jc-btn-stay" onclick="document.getElementById('jc-logout-overlay').classList.remove('show')">
          <i class="fas fa-arrow-left"></i>&nbsp; Stay
        </button>
        <button class="jc-btn-out" onclick="window.location.href='logout.php'">
          <i class="fas fa-right-from-bracket"></i>&nbsp; Log out
        </button>
      </div>
    </div>`;
  document.body.appendChild(modal);

  // Close on backdrop click
  modal.addEventListener('click', function (e) {
    if (e.target === modal) modal.classList.remove('show');
  });

  // Intercept all logout links
  document.querySelectorAll('a[href="logout.php"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      document.getElementById('jc-logout-overlay').classList.add('show');
    });
  });
})();

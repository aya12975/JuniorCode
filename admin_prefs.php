<?php
/*
 * admin_prefs.php
 * Include AFTER session_start() and $conn is available.
 * Loads theme + language, provides t() translation helper and dark-mode CSS.
 */

function getAdminSetting($conn, $key, $default = '') {
    $conn->query("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NOT NULL
    )");
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) return $default;
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $r = $stmt->get_result();
    return ($r && ($row = $r->fetch_assoc())) ? $row['setting_value'] : $default;
}

function saveAdminSetting($conn, $key, $value) {
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    if (!$stmt) return false;
    $stmt->bind_param("ss", $key, $value);
    return $stmt->execute();
}

if (!isset($_SESSION['admin_theme'])) {
    $_SESSION['admin_theme'] = getAdminSetting($conn, 'theme', 'light');
}
if (!isset($_SESSION['admin_lang'])) {
    $_SESSION['admin_lang'] = getAdminSetting($conn, 'lang', 'en');
}

$adminTheme = $_SESSION['admin_theme'];
$adminLang  = $_SESSION['admin_lang'];
$adminDir   = ($adminLang === 'ar') ? 'rtl' : 'ltr';

$LANG = [
  'en' => [
    'nav_dashboard'  => 'Dashboard',          'nav_users'     => 'Manage Users',
    'nav_classes'    => 'Manage Classes',      'nav_earnings'  => 'Teacher Earnings',
    'nav_slots'      => 'Available Slots',     'nav_courses'   => 'Courses',
    'nav_reports'    => 'Reports',             'nav_settings'  => 'Settings',
    'nav_logout'     => 'Logout',              'hello'         => 'Hello',
    'save_changes'   => 'Save Changes',
    'dash_title'     => 'Admin Dashboard',
    'dash_sub'       => 'Welcome back, Admin. Here is your full system overview.',
    'total_users'    => 'Total Users',         'students'      => 'Students',
    'teachers'       => 'Teachers',            'classes'       => 'Classes',
    'all_accounts'   => 'All registered accounts',
    'student_accs'   => 'Student accounts',    'teacher_accs'  => 'Teacher accounts',
    'total_classes'  => 'Total classes in system',
    'teacher_earn'   => 'Teacher Earnings',    'avail_slots'   => 'Available Slots',
    'today_slots'    => "Today's Available Slots",
    'recent_users'   => 'Recent Users',        'recent_earn'   => 'Recent Earnings',
    'upcoming_avail' => 'Upcoming Availability','view_all'     => 'View All',
    'username'       => 'Username',            'role'          => 'Role',
    'lesson'         => 'Lesson',              'amount'        => 'Amount',
    'teacher'        => 'Teacher',             'date'          => 'Date',
    'time'           => 'Time',
    'settings_title' => 'Settings',
    'settings_sub'   => 'Manage appearance, language, account, and platform preferences.',
    'tab_appearance' => 'Appearance & Language',
    'tab_account'    => 'Account & Security',
    'tab_platform'   => 'Platform Settings',
    'appearance'     => 'Appearance',          'theme_label'   => 'Theme',
    'light_mode'     => 'Light Mode',          'dark_mode'     => 'Dark Mode',
    'language'       => 'Language',
    'lang_en'        => 'English',             'lang_ar'       => 'Arabic (عربي)',
    'lang_fr'        => 'French (Français)',
    'account'        => 'Account & Security',
    'admin_email'    => 'Admin Email',         'current_pwd'   => 'Current Password',
    'new_pwd'        => 'New Password',        'confirm_pwd'   => 'Confirm New Password',
    'leave_blank'    => 'Leave blank to keep your current password',
    'platform'       => 'Platform Settings',
    'academy_name'   => 'Academy Name',        'whatsapp'      => 'WhatsApp Number',
    'currency'       => 'Currency',            'timezone'      => 'Timezone',
    'system_status'  => 'System Status',       'active'        => 'Active',
    'maintenance'    => 'Maintenance',
    'class_duration' => 'Default Class Duration (min)',
    'session_timeout'=> 'Session Timeout (min)',
    'welcome_msg'    => 'Welcome Message',     'trial_msg'     => 'Trial Success Message',
    'admin_panel'    => 'Admin Panel',         'main_label'    => 'Main',
  ],
  'ar' => [
    'nav_dashboard'  => 'لوحة التحكم',         'nav_users'     => 'إدارة المستخدمين',
    'nav_classes'    => 'إدارة الفصول',         'nav_earnings'  => 'أرباح المعلمين',
    'nav_slots'      => 'المواعيد المتاحة',     'nav_courses'   => 'الدورات',
    'nav_reports'    => 'التقارير',              'nav_settings'  => 'الإعدادات',
    'nav_logout'     => 'تسجيل الخروج',          'hello'         => 'مرحباً',
    'save_changes'   => 'حفظ التغييرات',
    'dash_title'     => 'لوحة تحكم المسؤول',
    'dash_sub'       => 'مرحباً بعودتك. إليك نظرة عامة كاملة على النظام.',
    'total_users'    => 'إجمالي المستخدمين',   'students'      => 'الطلاب',
    'teachers'       => 'المعلمون',             'classes'       => 'الفصول',
    'all_accounts'   => 'جميع الحسابات المسجلة',
    'student_accs'   => 'حسابات الطلاب',        'teacher_accs'  => 'حسابات المعلمين',
    'total_classes'  => 'إجمالي الفصول في النظام',
    'teacher_earn'   => 'أرباح المعلمين',       'avail_slots'   => 'المواعيد المتاحة',
    'today_slots'    => 'مواعيد اليوم المتاحة',
    'recent_users'   => 'المستخدمون الأخيرون', 'recent_earn'   => 'الأرباح الأخيرة',
    'upcoming_avail' => 'المواعيد القادمة',      'view_all'      => 'عرض الكل',
    'username'       => 'اسم المستخدم',         'role'          => 'الدور',
    'lesson'         => 'الدرس',                'amount'        => 'المبلغ',
    'teacher'        => 'المعلم',               'date'          => 'التاريخ',
    'time'           => 'الوقت',
    'settings_title' => 'الإعدادات',
    'settings_sub'   => 'إدارة المظهر واللغة والحساب وتفضيلات المنصة.',
    'tab_appearance' => 'المظهر واللغة',
    'tab_account'    => 'الحساب والأمان',
    'tab_platform'   => 'إعدادات المنصة',
    'appearance'     => 'المظهر',               'theme_label'   => 'السمة',
    'light_mode'     => 'الوضع الفاتح',         'dark_mode'     => 'الوضع الداكن',
    'language'       => 'اللغة',
    'lang_en'        => 'English (الإنجليزية)',  'lang_ar'       => 'العربية',
    'lang_fr'        => 'Français (الفرنسية)',
    'account'        => 'الحساب والأمان',
    'admin_email'    => 'البريد الإلكتروني',    'current_pwd'   => 'كلمة المرور الحالية',
    'new_pwd'        => 'كلمة المرور الجديدة',  'confirm_pwd'   => 'تأكيد كلمة المرور',
    'leave_blank'    => 'اتركه فارغاً للاحتفاظ بكلمة المرور الحالية',
    'platform'       => 'إعدادات المنصة',
    'academy_name'   => 'اسم الأكاديمية',       'whatsapp'      => 'رقم واتساب',
    'currency'       => 'العملة',               'timezone'      => 'المنطقة الزمنية',
    'system_status'  => 'حالة النظام',           'active'        => 'نشط',
    'maintenance'    => 'صيانة',
    'class_duration' => 'مدة الفصل الافتراضية (دقيقة)',
    'session_timeout'=> 'مهلة الجلسة (دقيقة)',
    'welcome_msg'    => 'رسالة الترحيب',        'trial_msg'     => 'رسالة نجاح التجربة',
    'admin_panel'    => 'لوحة المسؤول',         'main_label'    => 'الرئيسية',
  ],
  'fr' => [
    'nav_dashboard'  => 'Tableau de bord',      'nav_users'     => 'Gérer les utilisateurs',
    'nav_classes'    => 'Gérer les classes',     'nav_earnings'  => 'Revenus des enseignants',
    'nav_slots'      => 'Créneaux disponibles',  'nav_courses'   => 'Cours',
    'nav_reports'    => 'Rapports',              'nav_settings'  => 'Paramètres',
    'nav_logout'     => 'Déconnexion',            'hello'         => 'Bonjour',
    'save_changes'   => 'Enregistrer',
    'dash_title'     => 'Tableau de bord admin',
    'dash_sub'       => 'Bon retour, Admin. Voici une vue complète du système.',
    'total_users'    => 'Utilisateurs totaux',  'students'      => 'Étudiants',
    'teachers'       => 'Enseignants',           'classes'       => 'Classes',
    'all_accounts'   => 'Tous les comptes enregistrés',
    'student_accs'   => 'Comptes étudiants',     'teacher_accs'  => 'Comptes enseignants',
    'total_classes'  => 'Total des classes dans le système',
    'teacher_earn'   => 'Revenus des enseignants','avail_slots'  => 'Créneaux disponibles',
    'today_slots'    => "Créneaux disponibles aujourd'hui",
    'recent_users'   => 'Utilisateurs récents',  'recent_earn'   => 'Revenus récents',
    'upcoming_avail' => 'Disponibilité à venir',  'view_all'      => 'Voir tout',
    'username'       => "Nom d'utilisateur",     'role'          => 'Rôle',
    'lesson'         => 'Leçon',                 'amount'        => 'Montant',
    'teacher'        => 'Enseignant',            'date'          => 'Date',
    'time'           => 'Heure',
    'settings_title' => 'Paramètres',
    'settings_sub'   => "Gérez l'apparence, la langue, le compte et les préférences.",
    'tab_appearance' => 'Apparence & Langue',
    'tab_account'    => 'Compte & Sécurité',
    'tab_platform'   => 'Paramètres plateforme',
    'appearance'     => 'Apparence',             'theme_label'   => 'Thème',
    'light_mode'     => 'Mode clair',            'dark_mode'     => 'Mode sombre',
    'language'       => 'Langue',
    'lang_en'        => 'English (Anglais)',      'lang_ar'       => 'Arabic (Arabe)',
    'lang_fr'        => 'Français',
    'account'        => 'Compte & Sécurité',
    'admin_email'    => 'E-mail admin',          'current_pwd'   => 'Mot de passe actuel',
    'new_pwd'        => 'Nouveau mot de passe',  'confirm_pwd'   => 'Confirmer le mot de passe',
    'leave_blank'    => 'Laisser vide pour conserver le mot de passe actuel',
    'platform'       => 'Paramètres de la plateforme',
    'academy_name'   => "Nom de l'académie",     'whatsapp'      => 'Numéro WhatsApp',
    'currency'       => 'Devise',                'timezone'      => 'Fuseau horaire',
    'system_status'  => 'État du système',        'active'        => 'Actif',
    'maintenance'    => 'Maintenance',
    'class_duration' => 'Durée de classe par défaut (min)',
    'session_timeout'=> 'Délai de session (min)',
    'welcome_msg'    => 'Message de bienvenue',  'trial_msg'     => "Message de succès d'essai",
    'admin_panel'    => 'Panneau admin',          'main_label'    => 'Principal',
  ],
];

function t($key) {
    global $LANG, $adminLang;
    return htmlspecialchars($LANG[$adminLang][$key] ?? $LANG['en'][$key] ?? $key, ENT_QUOTES, 'UTF-8');
}

function darkModeCSS() {
    return '
<script>
(function(){
  var s=localStorage.getItem("jc-theme");
  if(s==="dark") document.documentElement.classList.add("dark");
})();
</script>
<style>
html.dark body{background:#0f172a!important;color:#e2e8f0!important}
html.dark .sidebar{background:linear-gradient(180deg,#020817 0%,#0c1226 100%)!important}
html.dark .panel-card,html.dark .mini-stat-card{background:#1e293b!important;border-color:#334155!important;color:#e2e8f0}
html.dark .stat-card{background:#1e293b!important;border-color:#334155!important}
html.dark .stat-label{color:#94a3b8!important}
html.dark .stat-value{color:#f1f5f9!important}
html.dark .stat-note{color:#64748b!important}
html.dark .stat-icon{background:#0f172a!important}
html.dark .panel-title{color:#f1f5f9!important}
html.dark .table thead th{background:#1e293b!important;color:#94a3b8!important;border-color:#334155!important}
html.dark .table td{color:#cbd5e1!important;border-color:#334155!important}
html.dark .empty-box{background:#1e293b!important;border-color:#334155!important;color:#64748b!important}
html.dark .form-control,html.dark .form-select,html.dark textarea{background:#1e293b!important;border-color:#475569!important;color:#e2e8f0!important}
html.dark .form-control::placeholder{color:#64748b!important}
html.dark .form-label{color:#cbd5e1!important}
html.dark .mini-stat-title{color:#94a3b8!important}
html.dark .mini-stat-value{color:#f1f5f9!important}
html.dark .topbar{background:linear-gradient(135deg,#1e3a6e 0%,#0f2456 100%)!important}
html.dark .theme-opt{background:#334155!important;border-color:#475569!important;color:#e2e8f0!important}
html.dark .theme-opt.selected{border-color:#3b82f6!important;background:rgba(59,130,246,.15)!important}
html.dark .settings-tab-btn{color:#94a3b8!important;border-color:#334155!important}
html.dark .settings-tab-btn.active{background:#1e40af!important;color:#fff!important;border-color:#1e40af!important}
html.dark .section-divider{border-color:#334155!important}
html.dark .lang-opt{background:#334155!important;border-color:#475569!important;color:#e2e8f0!important}
html.dark .lang-opt.selected{border-color:#3b82f6!important;background:rgba(59,130,246,.15)!important}
html.dark .admin-badge{background:rgba(255,255,255,.1)!important}
</style>';
}
?>

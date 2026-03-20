<?php
// Google OAuth configuration for Admin login
// NOTE: Keep these values private. Rotate if exposed.

$googleClientId = '94308954091-7455n2q7jfjqqflj5tlhe498ignv0qhm.apps.googleusercontent.com';
$googleClientSecret = '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';
$googleAdminRedirectUri  = 'https://neu-libraryvisitorlog.page.gd/admin_dashboard/google_callback.php';
$googlePublicRedirectUri = 'https://neu-libraryvisitorlog.page.gd/entrynavpages/google_callback.php';


// Allowed admin emails
$googleAllowedAdmins = [
    'jcesperanza@neu.edu.ph',
    'clarkkent.zuniga@neu.edu.ph',
    'jrpitpay@neu.edu.ph',
    'dsalcantara@neu.edu.ph',
    'ncgaspar@neu.edu.ph',
];

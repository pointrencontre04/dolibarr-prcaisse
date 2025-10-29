<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

if (isModEnabled('prbonalim')) {
    require_once DOL_DOCUMENT_ROOT . '/custom/prbonalim/class/bonalim.class.php';
}

$langs->load("admin");
$langs->load("companies");

// Sécurité : l'utilisateur doit être connecté
if (empty($user->id)) {
    http_response_code(403);
    exit('Accès refusé : utilisateur non connecté.');
}

if (!$user->hasRight('takepos', 'run')) {
	accessforbidden('Access to TakePOS forbidden');
}

if (!$user->rights->facture->lire) {
    accessforbidden('Access to Facture forbidden');
}

/*
// Vérifier qu’un client est sélectionné
if (empty($_SESSION['takepos_customer_id'])) {
    http_response_code(400);
    exit('Client non sélectionné');
}
*/

// Retrieve Facture object to handle
$invoice_id = GETPOST('invoice_id', 'int');
$invoice = new Facture($db);
$res = $invoice->fetch($invoice_id);
if ($res <= 0) {
    http_response_code(404);
    exit('Facture invalide.');
}

$thirdparty_id = $invoice->socid;

if (empty($thirdparty_id) || $thirdparty_id <= 0) {
    http_response_code(400);
    exit('Paramètre manquant ou invalide.');
}

$pos_source = (int) $_SESSION['takeposterminal'];

// Exclude any Generic user from this script
$numberofterminals = getDolGlobalString('TAKEPOS_NUM_TERMINALS', '1');
for ($terminal = 1; $terminal <= $numberofterminals; $terminal++) {
    $is_thirdparty_generic = ($thirdparty_id == getDolGlobalInt('CASHDESK_ID_THIRDPARTY'.$terminal));
    if ($is_thirdparty_generic) {
        exit;
    }
}

// Récupération du tiers
$thirdparty = new Societe($db);
$res = $thirdparty->fetch($thirdparty_id);
if ($res <= 0) {
    http_response_code(404);
    exit('Client introuvable.');
}

// Autorisation : l'utilisateur doit être admin ou avoir droit de voir ce tiers
$authorized = false;

if ($user->admin) {
    $authorized = true;
} elseif ($user->rights->societe->lire) {
    // Gestion commerciale activée ?
    if (!empty($conf->global->SOCIETE_USE_COMMERCIAL_CONTACTS)) {
        // L'utilisateur est-il commercial de ce tiers ?
        if ($thirdparty->isACompanyOfUser($user->id)) {
            $authorized = true;
        }
    } else {
        // Pas de restriction commerciale → accès global autorisé
        $authorized = true;
    }
}

if (!$authorized) {
    accessforbidden('You are not permitted to load user details');
}

$results = [];

if ($invoice->status != Facture::STATUS_DRAFT) {
    echo json_encode(['status' => 'error', 'error' => 'Cannot operate on invoice that is not draft']);
    exit();
}

if ($invoice->type == Facture::TYPE_STANDARD) {
    $invoice->type = Facture::TYPE_DEPOSIT;
    $invoice->update($user);
    echo json_encode(['status' => 'ok', 'action' => 'set_deposit']);
    exit();
} elseif ($invoice->type == Facture::TYPE_DEPOSIT) {
    $invoice->type = Facture::TYPE_STANDARD;
    $invoice->update($user);
    echo json_encode(['status' => 'ok', 'action' => 'set_standard']);
    exit();
} else {
    echo json_encode(['status' => 'error', 'error' => 'Wrong type for invoice']);
    exit();
}
